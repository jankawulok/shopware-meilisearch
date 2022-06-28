<?php
declare(strict_types=1);

namespace Mdnr\Meilisearch\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Context;
use Mdnr\Meilisearch\Framework\Search;
use Symfony\Contracts\EventDispatcher\Event;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

class MeilisearchEntityAggregatorSearchEvent extends Event implements ShopwareEvent
{
    private Search $search;

    private Context $context;

    private EntityDefinition $definition;

    private Criteria $criteria;

    public function __construct(Search $search, Context $context, EntityDefinition $definition, Criteria $criteria)
    {
        $this->search = $search;
        $this->context = $context;
        $this->definition = $definition;
        $this->criteria = $criteria;
    }

    public function getSearch(): Search
    {
        return $this->search;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getDefinition(): EntityDefinition
    {
        return $this->definition;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }
}
