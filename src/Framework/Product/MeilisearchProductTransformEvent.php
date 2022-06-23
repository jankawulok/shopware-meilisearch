<?php

declare(strict_types=1);

namespace Mdnr\Meilisearch\Framework\Product;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Contracts\EventDispatcher\Event;

class MeilisearchProductTransformEvent extends Event implements ShopwareEvent
{
    /**
     * @Event('Mdnr\Meilisearch\Framework\Event\MeilisearchProductTransformEvent')
     */
    public const MEILISEARCH_PRODUCT_TRANSFORM_EVENT = 'mdnr.meilisearch.product.transform.event';

    protected ProductEntity $productEntity;

    protected Context $context;

    protected array $document;

    public function __construct(array $document, ProductEntity $productEntity, Context $context)
    {
        $this->productEntity = $productEntity;
        $this->context = $context;
        $this->document = $document;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return ProductEntity
     */
    public function getProductEntity(): ProductEntity
    {
        return $this->productEntity;
    }

    /**
     * @param ProductEntity $productEntity
     */
    public function setProductEntity(ProductEntity $productEntity): void
    {
        $this->productEntity = $productEntity;
    }

    /**
     * @return array
     */
    public function getDocument(): array
    {
        return $this->document;
    }

    /**
     * @param array $document
     */
    public function setDocument(array $document): void
    {
        $this->document = $document;
    }
}
