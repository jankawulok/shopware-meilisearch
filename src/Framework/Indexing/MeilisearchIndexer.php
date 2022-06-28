<?php

declare(strict_types=1);

namespace Mdnr\Meilisearch\Framework\Indexing;

use MeiliSearch\Client;
use Shopware\Core\Defaults;
use Psr\Log\LoggerInterface;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Mdnr\Meilisearch\Framework\MeilisearchHelper;
use Shopware\Core\System\Language\LanguageEntity;
use Mdnr\Meilisearch\Framework\MeilisearchRegistry;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\System\Language\LanguageCollection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Mdnr\Meilisearch\Framework\Indexing\MeilisearchIndexingMessage;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NandFilter;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class MeilisearchIndexer extends AbstractMessageHandler
{
    private Connection $connection;
    private MeilisearchHelper $helper;
    private MeilisearchRegistry $registry;
    private EntityRepositoryInterface $currencyRepository;
    private EntityRepositoryInterface $languageRepository;
    public function __construct(
        Connection $connection,
        IndexCreator $indexCreator,
        IteratorFactory $iteratorFactory,
        MeilisearchHelper $helper,
        MeilisearchRegistry $registry,
        Client $client,
        LoggerInterface $logger,
        EntityRepositoryInterface $currencyRepository,
        EntityRepositoryInterface $languageRepository,
        EventDispatcherInterface $eventDispatcher,
        int $indexingBatchSize
    ) {
        $this->connection = $connection;
        $this->helper = $helper;
        $this->registry = $registry;
        $this->indexCreator = $indexCreator;
        $this->iteratorFactory = $iteratorFactory;
        $this->client = $client;
        $this->logger = $logger;
        $this->currencyRepository = $currencyRepository;
        $this->languageRepository = $languageRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->indexingBatchSize = $indexingBatchSize;
    }

    public function init(): IndexerOffset
    {
        $this->connection->executeStatement('DELETE FROM meilisearch_index_task');

        $definitions = $this->registry->getDefinitions();
        $languages = $this->getLanguages();

        $currencies = $this->getCurrencies();

        $timestamp = new \DateTime();

        foreach ($languages as $language) {
            $context = $this->createLanguageContext($language);

            $context->addExtension('currencies', $currencies);

            foreach ($definitions as $definition) {
                $alias = $this->helper->getIndexName($definition->getEntityDefinition(), $language->getId());

                // $index = $alias . '_' . $timestamp->getTimestamp();
                $index = $alias; //TODO: meilisearch does not support aliases yet

                $this->indexCreator->createIndex($definition, $index, $alias, $context);

                $iterator = $this->iteratorFactory->createIterator($definition->getEntityDefinition(), null, 500);

                $this->connection->insert('meilisearch_index_task', [
                    'id' => Uuid::randomBytes(),
                    '`entity`' => $definition->getEntityDefinition()->getEntityName(),
                    '`index`' => $index,
                    '`alias`' => $alias,
                    '`doc_count`' => $iterator->fetchCount(),
                ]);
            }
        }

        return new IndexerOffset(
            $languages,
            $definitions,
            $timestamp->getTimestamp()
        );
    }

    /**
     * @param MeiliSearchIndexingMessage $message
     */
    public function handle($message): void
    {
        if (!$message instanceof MeilisearchIndexingMessage) {
            return;
        }
        if (!$this->helper->allowIndexing()) {
            return;
        }

        $task = $message->getData();

        $ids = $task->getIds();

        $index = $task->getIndex();
        $this->connection->executeStatement('UPDATE meilisearch_index_task SET `doc_count` = `doc_count` - :idCount WHERE `index` = :index', [
            'idCount' => \count($ids),
            'index' => $index,
        ]);

        if (!$this->client->index($index)->fetchRawInfo()) { // Check if index exists
            return;
        }
        $entity = $task->getEntity();
        $definition = $this->registry->get($entity);

        $context = $message->getContext();
        $context->addExtension('currencies', $this->getCurrencies());

        if (!$definition) {
            throw new \RuntimeException('Could not find definition for entity ' . $entity);
        }

        $data = $definition->fetch($ids, $context);

        $toDelete =  array_values(array_filter($ids, fn (string $id) => !isset($data[$id])));

        $documents = [];
        foreach ($data as $id => $document) {
            $documents[] = $document;
        }
   

        $this->client->index($index)->updateDocuments($documents);
        $this->client->index($index)->deleteDocuments($toDelete);
    }

    public static function getHandledMessages(): iterable
    {
        return [
            MeilisearchIndexingMessage::class,
        ];
    }

    public function iterate($offset)
    {
        if (!$this->helper->allowIndexing()) {
            return null;
        }

        if ($offset === null) {
            $offset = $this->init();
        }

        if ($offset->getLanguageId() === null) {
            return null;
        }

        $language = $this->getLanguageForId($offset->getLanguageId());

        if (!$language) {
            return null;
        }

        $context = $this->createLanguageContext($language);

         $message = $this->createIndexingMessage($offset, $context);
        if ($message) {
            return $message;
        }
 
        if (!$offset->hasNextLanguage()) {
            return null;
        }
 
         $offset->setNextLanguage();
         $offset->resetDefinitions();
         $offset->setLastId(null);
 
         return $this->iterate($offset);
    }

    private function createIndexingMessage(IndexerOffset $offset, Context $context): ?MeilisearchIndexingMessage
    {
        $definition = $this->registry->get((string) $offset->getDefinition());

        if (!$definition) {
            throw new \RuntimeException('Could not find definition for entity ' . $offset->getDefinition());
        }

        $entity = $definition->getEntityDefinition()->getEntityName();

        $iterator = $this->iteratorFactory->createIterator($definition->getEntityDefinition(), $offset->getLastId(), $this->indexingBatchSize);

        $ids = $iterator->fetch();

        if (!empty($ids)) {
            $offset->setLastId($iterator->getOffset());

            $alias = $this->helper->getIndexName($definition->getEntityDefinition(), (string) $offset->getLanguageId());

            // $index = $alias . '_' . $offset->getTimestamp();
            $index = $alias;

            return new MeilisearchIndexingMessage(new IndexingDto(array_values($ids), $index, $entity), $offset, $context);
        }

        if (!$offset->hasNextDefinition()) {
            return null;
        }
    }

    public function updateIds(EntityDefinition $definition, array $ids): void
    {

        if (!$this->helper->allowIndexing()) {
            return;
        }
        $messages = $this->generateMessages($definition, $ids);

        if (!$this->registry->has($definition->getEntityName())) {
            return;
        }

        foreach ($messages as $message) {
            $this->handle($message);
        }
    }

    private function createLanguageContext(LanguageEntity $language): Context
    {
        return new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            array_filter([$language->getId(), $language->getParentId(), Defaults::LANGUAGE_SYSTEM])
        );
    }

    private function generateMessages(EntityDefinition $definition, array $ids): array
    {
        $languages = $this->getLanguages();

        $messages = [];
        foreach ($languages as $language) {
            $context = $this->createLanguageContext($language);

            $alias = $this->helper->getIndexName($definition, $language->getId());

            $indexing = new IndexingDto($ids, $alias, $definition->getEntityName());

            $message = new MeilisearchIndexingMessage($indexing, null, $context);

            $messages[] = $message;
        }

        return $messages;
    }
    private function getLanguageForId(string $languageId): ?LanguageEntity
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria([$languageId]);

        /** @var LanguageCollection $languages */
        $languages = $this->languageRepository
            ->search($criteria, $context);

        return $languages->get($languageId);
    }

    private function getCurrencies(): EntitySearchResult
    {
        return $this->currencyRepository->search(new Criteria(), Context::createDefaultContext());
    }

    private function getLanguages(): LanguageCollection
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new NandFilter([new EqualsFilter('salesChannels.id', null)]));
        $criteria->addSorting(new FieldSorting('id'));

        /** @var LanguageCollection $languages */
        $languages = $this->languageRepository
            ->search($criteria, $context)
            ->getEntities();

        return $languages;
    }
}
