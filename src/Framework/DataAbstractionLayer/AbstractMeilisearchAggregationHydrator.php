<?php

declare(strict_types=1);

namespace Mdnr\Meilisearch\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

abstract class AbstractMeilisearchAggregationHydrator
{
    abstract public function getDecorated(): AbstractMeilisearchAggregationHydrator;

    abstract public function hydrate(EntityDefinition $definition, Criteria $criteria, Context $context, array $result): AggregationResultCollection;
}
