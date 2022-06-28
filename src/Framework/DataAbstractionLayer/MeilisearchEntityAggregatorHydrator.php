<?php

namespace Mdnr\Meilisearch\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;

class MeilisearchEntityAggregatorHydrator extends AbstractMeilisearchAggregationHydrator
{
    private DefinitionInstanceRegistry $registry;

    public function __construct(DefinitionInstanceRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function getDecorated(): AbstractMeilisearchAggregationHydrator
    {
        throw new DecorationPatternException(self::class);
    }

    public function hydrate(EntityDefinition $definition, Criteria $criteria, Context $context, array $result): AggregationResultCollection
    {
        if (!isset($result['aggregations'])) {
            return new AggregationResultCollection();
        }
    }
}
