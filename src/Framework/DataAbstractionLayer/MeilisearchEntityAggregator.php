<?php

namespace Mdnr\Meilisearch\Framework\DataAbstractionLayer;

use MeiliSearch\Client;
use Shopware\Core\Framework\Context;
use Mdnr\Meilisearch\Framework\Search;
use Mdnr\Meilisearch\Framework\MeilisearchHelper;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Mdnr\Meilisearch\Framework\DataAbstractionLayer\Event\MeilisearchEntityAggregatorSearchEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;

class MeilisearchEntityAggregator implements EntityAggregatorInterface
{
    public const RESULT_STATE = 'loaded-by-meilisearch';

    private MeilisearchHelper $helper;

    private Client $client;

    private EntityAggregatorInterface $decorated;

    private AbstractMeilisearchAggregationHydrator $hydrator;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        Client $client,
        EntityAggregatorInterface $decorated,
        MeilisearchHelper $helper,
        AbstractMeilisearchAggregationHydrator $hydrator,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->client = $client;
        $this->decorated = $decorated;
        $this->helper = $helper;
        $this->hydrator = $hydrator;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function aggregate(EntityDefinition $definition, Criteria $criteria, Context $context): AggregationResultCollection
    {
        if (!$this->helper->allowSearch($definition, $context)) {
            return $this->decorated->aggregate($definition, $criteria, $context);
        }

        $search = $this->createSearch($definition, $criteria, $context);
        $this->eventDispatcher->dispatch(
            new MeilisearchEntityAggregatorSearchEvent($search, $context, $definition, $criteria)
        );
        try {
            $index = $this->helper->getIndexName($definition, $context->getLanguageId());
            $result = $this->client->index($index)->search(
                $search->getQuery(),
                $search->getParams()
            )->getRaw();
        } catch (\Throwable $e) {
            $this->helper->logAndThrowException($e);

            return $this->decorated->aggregate($definition, $criteria, $context);
        }

        $result = $this->hydrator->hydrate($definition, $criteria, $context, $result);
        $result->addState(self::RESULT_STATE);

        return $result;
    }

    private function createSearch(EntityDefinition $definition, Criteria $criteria, Context $context): Search
    {
        $search = new Search();
        $this->helper->handleIds($definition, $criteria, $search, $context);
        $this->helper->addTerm($criteria, $search, $context, $definition);
        $this->helper->addQueries($definition, $criteria, $search, $context);
        $this->helper->addFilters($definition, $criteria, $search, $context);
        $this->helper->addAggregations($definition, $criteria, $search, $context);
        $search->setLimit(0);

        return $search;
    }
}
