<?php
declare(strict_types=1);

namespace Mdnr\Meilisearch\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

abstract class AbstractMeilisearchSearchHydrator
{
    abstract public function getDecorated(): AbstractMeilisearchSearchHydrator;

    abstract public function hydrate(EntityDefinition $definition, Criteria $criteria, Context $context, array $result): IdSearchResult;
}
