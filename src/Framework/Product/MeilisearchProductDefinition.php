<?php

declare(strict_types=1);

namespace Mdnr\Meilisearch\Framework\Product;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Product\ProductDefinition;
use Mdnr\Meilisearch\Framework\AbstractMeilisearchDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

class MeilisearchProductDefinition extends AbstractMeilisearchDefinition
{
    protected ProductDefinition $definition;
    protected MeilisearchProductTransformer $transformer;

    public function __construct(ProductDefinition $definition, MeilisearchProductTransformer $transformer)
    {
        $this->definition = $definition;
        $this->transformer = $transformer;
    }

    public function getId(): string
    {
        return 'id';
    }

    public function getEntityDefinition(): EntityDefinition
    {
        return $this->definition;
    }

    public function fetch($ids, Context $context): array
    {
        $criteria = (new Criteria($ids))
        ->addAssociation('categoriesRo')
        ->addAssociation('prices')
        ->addAssociation('properties')
        ->addAssociation('properties.group')
        ->addAssociation('manufacturer')
        ->addAssociation('tags')
        ->addAssociation('configuratorSettings')
        ->addAssociation('options')
        ->addAssociation('options.group')
        ->addAssociation('visibilities')
        ->addAssociation('categories')
        ->addAssociation('cover.media')
        ->addAssociation('media')
        ->addAssociation('seoUrls');

        $context->setConsiderInheritance(true);
        $entities = $this->repository->search($criteria, $context);

        return array_map(function ($entity) use ($context) {
            return $this->transformer->transform($entity, $context);
        }, iterator_to_array($entities));
    }

    public function getSettingsObject(): array
    {
        return [
        'rankingRules' => [
        'exactness',
        'words',
        'typo',
        'proximity',
        'attribute',
        'sort',
        'release_date:desc',
        'rank:desc'
        ],
        'distinctAttribute' => 'objectID',
        'searchableAttributes' => [
        'objectID',
        'productNumber',
        'name',
        'ean',
        'manufacturer',
        'categoryName',
        'manufacturerNumber',
        'options',
        'price',
        'description'
        ],
        'displayedAttributes' => [
        'id',
        'productNumber',
        'name',
        'active',
        'stock',
        'availableStock',
        'price',
        'manufacturer',
        'categoryName',
        'variation',
        'options'
        ],
        'sortableAttributes' => [],
        'synonyms' => []
        ];
    }
}
