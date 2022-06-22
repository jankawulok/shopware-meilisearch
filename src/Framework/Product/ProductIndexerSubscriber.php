<?php declare(strict_types=1);

namespace Mdnr\Meilisearch\Framework\Product;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Product\ProductEntity;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;


class ProductIndexerSubscriber
{
  protected EntityRepositoryInterface $productRepository;

  protected EventDispatcherInterface $eventDispatcher;

  public function __construct(EntityRepositoryInterface $productRepository, EventDispatcherInterface $eventDispatcher)
  {
    $this->productRepository = $productRepository;
    $this->eventDispatcher = $eventDispatcher;
  }

  public function transform(ProductEntity $productEntity, Context $context)
  {
    
  }
  
}
