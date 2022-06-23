<?php

declare(strict_types=1);

namespace Mdnr\Meilisearch\Framework\Product;

use Mdnr\Meilisearch\Framework\Indexing\MeilisearchIndexer;
use Shopware\Core\Content\Product\Events\ProductIndexerEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

class ProductIndexerSubscriber
{
    protected MeilisearchIndexer $indexer;

    protected EntityDefinition $definition;

    public function __construct(EntityRepositoryInterface $productRepository, EventDispatcherInterface $eventDispatcher)
    {
        $this->productRepository = $productRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function update(ProductIndexerEvent $event): void
    {
        $this->indexer->updateIds(
            $this->definition,
            array_unique(array_merge($event->getIds(), $event->getChildrenIds()))
        );
    }
}
