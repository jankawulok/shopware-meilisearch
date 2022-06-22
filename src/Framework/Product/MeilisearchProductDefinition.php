<?php

declare(strict_types=1);

namespace Mdnr\Meilisearch\Framework\Product;

use Shopware\Core\Content\Product\ProductDefinition;
use Mdnr\Meilisearch\Framework\AbstractMeilisearchDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

class MeilisearchProductDefinition extends AbstractMeilisearchDefinition
{
  protected ProductDefinition $definition;

  public function __construct(ProductDefinition $definition)
  {
    $this->definition = $definition;
  }

  public function getId(): string
  {
    return 'id';
  }

  public function getEntityDefinition(): EntityDefinition
  {
    return $this->definition;
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
