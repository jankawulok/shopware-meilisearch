<?php

declare(strict_types=1);

namespace Mdnr\Meilisearch\Framework\DataAbstractionLayer;

use MeiliSearch\Client;
use Shopware\Core\Framework\Context;
use Mdnr\Meilisearch\Framework\Search;
use Mdnr\Meilisearch\Framework\MeilisearchHelper;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Mdnr\Meilisearch\Framework\DataAbstractionLayer\Event\MeilisearchEntitySearcherSearchEvent;

class MeilisearchEntitySearcher implements EntitySearcherInterface
{
    public const RESULT_STATE = 'loaded-by-meilisearch';
    private Client $client;

    private EntitySearcherInterface $decorated;

    private MeilisearchHelper $helper;

    private CriteriaParser $criteriaParser;

    private AbstractMeilisearchSearchHydrator $hydrator;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        Client $client,
        EntitySearcherInterface $decorated,
        MeilisearchHelper $helper,
        CriteriaParser $criteriaParser,
        AbstractMeilisearchSearchHydrator $hydrator,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->client = $client;
        $this->decorated = $decorated;
        $this->helper = $helper;
        $this->criteriaParser = $criteriaParser;
        $this->hydrator = $hydrator;
        $this->eventDispatcher = $eventDispatcher;
    }

    private function createSearch(EntityDefinition $definition, Criteria $criteria, Context $context): Search
    {
        $search = new Search();
        $this->helper->handleIds($definition, $criteria, $search, $context);
        $this->helper->addTerm($criteria, $search, $context, $definition);
        $this->helper->addFilters($definition, $criteria, $search, $context);
        $this->helper->addPostFilters($definition, $criteria, $search, $context);
        $this->helper->setLimit($criteria, $search);
        $this->helper->setOffset($criteria, $search);

        return $search;
    }

    public function search(EntityDefinition $definition, Criteria $criteria, Context $context): IdSearchResult
    {
        if (!$this->helper->allowSearch($definition, $context)) {
            return $this->decorated->search($definition, $criteria, $context);
        }
        if ($criteria->getLimit() === 0) {
            return new IdSearchResult(0, [], $criteria, $context);
        }

        $search = $this->createSearch($definition, $criteria, $context);
        $this->helper->addTerm($criteria, $search, $context, $definition);
        $this->eventDispatcher->dispatch(
            new MeilisearchEntitySearcherSearchEvent(
                $search,
                $definition,
                $criteria,
                $context
            )
        );

        try {
            $index = $this->helper->getIndexName($definition, $context->getLanguageId());
            $result = $this->client->index($index)->search(
                $search->getQuery(),
                $search->getParams()
            )->getRaw();
        } catch (\Throwable $e) {
            $this->helper->logAndThrowException($e);

            return $this->decorated->search($definition, $criteria, $context);
        }

        $result = $this->hydrator->hydrate($definition, $criteria, $context, $result);

        $result->addState(self::RESULT_STATE);

        return $result;

        return new IdSearchResult(0, [], $criteria, $context);
    }
}
