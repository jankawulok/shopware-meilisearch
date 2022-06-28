<?php

declare(strict_types=1);

namespace Mdnr\Meilisearch\Framework;

use MeiliSearch\Client;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;

class MeilisearchHelper
{
    private Client $client;

    private string $environment;

    private LoggerInterface $logger;
    
    private MeilisearchRegistry $registry;

    private bool $throwException;

    public function __construct(string $environment, Client $client, MeilisearchRegistry $registry, LoggerInterface $logger)
    {
        $this->environment = $environment;
        $this->client = $client;
        $this->registry = $registry;
        $this->logger = $logger;
        $this->throwException = true;
    }

    public function isSearchEnabled(Context $context): bool
    {
        return true;
        // return (bool)$this->systemConfigService->get('Meilisearch.config.enabled', $context->getSalesChannelId());
    }

    public function allowIndexing(): bool
    {
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

    public function addTerm(Criteria $criteria, Search $search, Context $context, EntityDefinition $definition): void
    {
        if (!$criteria->getTerm()) {
            return;
        }

        $search->setQuery($criteria->getTerm());
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

    public function allowSearch(EntityDefinition $definition, Context $context): bool
    {
        if (!$this->isSearchEnabled($context)) {
            return false;
        }

        if (!$this->isSupported($definition)) {
            return false;
        }

        if (!$context->hasState(Context::STATE_ELASTICSEARCH_AWARE)) {
            return false;
        }

        return true;
    }
}
