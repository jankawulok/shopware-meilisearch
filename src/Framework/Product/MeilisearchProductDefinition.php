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
    return $this->definition->getPrimaryKeys()[0];
  }

  public function getEntityDefinition(): EntityDefinition
  {
    return $this->definition;
  }

  public function getSearchableAttributes(): array
  {
    return [];
  }

  public function getFilterableAttributes(): array
  {
    return [];
  }

  public function getSortableAttributes(): array
  {
    return [];
  }

}
