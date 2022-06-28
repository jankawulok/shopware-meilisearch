<?php
declare(strict_types=1);

namespace Mdnr\Meilisearch\Framework\Product;

use Mdnr\Meilisearch\Framework\Indexing\MeilisearchIndexer;
use Shopware\Core\Content\Product\Events\ProductIndexerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

class ProductUpdater implements EventSubscriberInterface
{
    private MeilisearchIndexer $indexer;
    private EntityDefinition $definition;

    public function __construct(MeilisearchIndexer $indexer, EntityDefinition $definition)
    {
        $this->indexer = $indexer;
        $this->definition = $definition;
    }
  
    public static function getSubscribedEvents()
    {
        return [
        ProductIndexerEvent::class => 'upadte',
        ];
    }

    public function update(ProductIndexerEvent $event): void
    {
        $this->indexer->updateIds($this->definition, $event->getIds());
    }
}
