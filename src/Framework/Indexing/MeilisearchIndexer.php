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

        $index = $alias . '_' . $timestamp->getTimestamp();

        $this->indexCreator->createIndex($definition, $index, $alias, $context);

        $iterator = $this->iteratorFactory->createIterator($definition->getEntityDefinition());

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

  public function handle($message): void
  {
    if (!$message instanceof MeilisearchIndexingMessage) {
      return;
    }
    if (!$this->heleper->allowIndexing()) {
      return;
    }
  }
  public static function getHandledMessages(): iterable
  {
    return [
      MeilisearchIndexingMessage::class,
    ];
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
