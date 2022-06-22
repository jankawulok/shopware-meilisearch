<?php

namespace Mdnr\Meilisearch\Framework\Indexing;

use MeiliSearch\Client;
use Shopware\Core\Framework\Context;
use Mdnr\Meilisearch\Framework\MeilisearchRegistry;
use Mdnr\Meilisearch\Framework\AbstractMeilisearchDefinition;

class IndexCreator
{

  private Client $client;

  public function __construct(Client $client, MeilisearchRegistry $registry)
  {
    $this->client = $client;
    $this->registry = $registry;
  }

  public function createIndex(AbstractMeilisearchDefinition $definition, string $index, string $alias, Context $context)
  {

    $this->client->createIndex($index, [
      'primaryKey' => $definition->getId(),
    ]);
    $this->client->updateIndex($index, [
      'searchableAttributes' => $definition->getSearchableAttributes(),
      'sortableAttributes' => $definition->getSortableAttributes(),
      'filterableAttributes' => $definition->getFilterableAttributes(),
    ]);
  }
}