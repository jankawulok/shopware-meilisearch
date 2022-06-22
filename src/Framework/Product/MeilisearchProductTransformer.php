<?php

namespace Mdnr\Meilisearch\Framework\Product;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Product\ProductEntity;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

class MeilisearchProductTransformer
{
  protected EntityRepositoryInterface $productRepository;
  protected EventDispatcherInterface $eventDispatcher;

  public function __construct(EntityRepositoryInterface $productRepository, EventDispatcherInterface $eventDispatcher)
  {
    $this->productRepository = $productRepository;
    $this->eventDispatcher = $eventDispatcher;
  }

  public function transform(ProductEntity $productEntity, Context $context): array
  {
    $productDTO = [];
    $this->eventDispatcher->dispatch(
      new MeilisearchProductTransformEvent(
        $productDTO,
        $productEntity,
        $context
      )
    );
    return $productDTO;
  }
}
