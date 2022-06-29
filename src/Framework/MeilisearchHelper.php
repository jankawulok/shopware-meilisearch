<?php

declare(strict_types=1);

namespace Mdnr\Meilisearch\Framework;

use Exception;
use MeiliSearch\Client;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Mdnr\Meilisearch\Framework\DataAbstractionLayer\CriteriaParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;

class MeilisearchHelper
{
    private Client $client;

    private string $environment;

    private LoggerInterface $logger;

    private MeilisearchRegistry $registry;

    private CriteriaParser $parser;

    private bool $searchEnabled;

    private bool $indexingEnabled;

    private bool $throwException;

    public function __construct(
        string $environment,
        bool $searchEnabled,
        bool $indexingEnabled,
        string $prefix,
        bool $throwException,
        Client $client,
        MeilisearchRegistry $registry,
        CriteriaParser $parser,
        LoggerInterface $logger
    ) {
        $this->searchEnabled = $searchEnabled;
        $this->indexingEnabled = $indexingEnabled;
        $this->prefix = $prefix;
        $this->environment = $environment;
        $this->client = $client;
        $this->registry = $registry;
        $this->parser = $parser;
        $this->logger = $logger;
        $this->throwException = $throwException;
    }

    public function allowSearch(EntityDefinition $definition, Context $context): bool
    {
        $entityName = $definition->getEntityName();

        return $this->searchEnabled
            && $this->registry->has($entityName)
            && $context->hasState(Context::STATE_ELASTICSEARCH_AWARE);
    }

    public function allowIndexing(): bool
    {
        if (!$this->indexingEnabled) {
            return false;
        }

        if (!$this->client->getKeys()) {
            return $this->logAndThrowException(new \Exception('Meilisearch client is not configured'));
        }

        return true;
    }

    public function getIndexName(EntityDefinition $definition, string $languageId): string
    {
        return  'sw_' . $definition->getEntityName() . '_' . $languageId;
    }

    public function isSupported(EntityDefinition $definition): bool
    {
        $entityName = $definition->getEntityName();

        return $this->registry->has($entityName);
    }

    public function handleIds(EntityDefinition $definition, Criteria $criteria, Search $search, Context $context): void
    {
        $ids = $criteria->getIds();
        if (empty($ids)) {
            return;
        }

        $query = $this->parser->parseFilter(
            new EqualsAnyFilter('id', array_values($ids)),
            $definition,
            $definition->getEntityName(),
            $context
        );

        if ($query) {
            $search->addFilter($query);
        }
    }

    public function setLimit(Criteria $criteria, Search $search): void
    {
        $limit = $criteria->getLimit();
        if ($limit) {
            $search->setLimit($limit);
        }
    }

    public function setOffset(Criteria $criteria, Search $search): void
    {
        $offset = $criteria->getOffset();
        if ($offset) {
            $search->setOffset($offset);
        }
    }

    public function addTerm(Criteria $criteria, Search $search, Context $context, EntityDefinition $definition): void
    {
        if (!$criteria->getTerm()) {
            return;
        }

        $search->setQuery($criteria->getTerm());
    }

    public function addFilters(EntityDefinition $definition, Criteria $criteria, Search $search, Context $context): void
    {
        $filters = $criteria->getFilters();
        if (empty($filters)) {
            return;
        }

        foreach ($filters as $filter) {
            if ($f = $this->parser->parseFilter($filter, $definition, $definition->getEntityName(), $context)) {
                $search->addFilter($f);
            }
        }
    }

    public function addAggregations(EntityDefinition $definition, Criteria $criteria, Search $search, Context $context): void
    {
        $aggregations = $criteria->getAggregations();
        if (empty($aggregations)) {
            return;
        }

        // die(var_dump($aggregations));
        foreach ($aggregations as $aggregation) {
            $agg = $this->parser->parseAggregation($aggregation, $definition, $context);

            if (!$agg) {
                continue;
            }

            $search->addFacetsDistribution($agg);
            // die(var_dump($search->getFacetsDistributions()));
        }
    }

    public function addQueries(EntityDefinition $definition, Criteria $criteria, Search $search, Context $context): void
    {
        //
        $queries = $criteria->getQueries();
        if (empty($queries)) {
            return;
        }
        die(var_dump($criteria->getQueries()));
    }

    public function addPostFilters(EntityDefinition $definition, Criteria $criteria, Search $search, Context $context): void
    {
        $filters = $criteria->getPostFilters();
        if (empty($filters)) {
            return;
        }
        foreach ($filters as $filter) {
            if ($f = $this->parser->parseFilter($filter, $definition, $definition->getEntityName(), $context)) {
                $search->addFilter($f);
            }
        }
    }


    public function logAndThrowException(\Throwable $exception): bool
    {
        $this->logger->critical($exception->getMessage());

        if ($this->environment === 'test') {
            throw $exception;
        }

        if ($this->throwException) {
            throw $exception;
        }

        return false;
    }
}
