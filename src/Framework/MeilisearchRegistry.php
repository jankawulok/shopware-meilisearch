<?php

declare(strict_types=1);

namespace Mdnr\Meilisearch\Framework;

class MeilisearchRegistry
{
  private iterable $definitions;

  public function __construct(iterable $definitions)
  {
    $this->definitions = $definitions;
  }

  public function getDefinitions(): iterable
  {
    return $this->definitions;
  }

  public function get(string $entityName): ?AbstractMeilisearchDefinition
    {
        foreach ($this->getDefinitions() as $definition) {
            if ($definition->getEntityDefinition()->getEntityName() === $entityName) {
                return $definition;
            }
        }

        return null;
    }

    public function has(string $entityName): bool
    {
        foreach ($this->getDefinitions() as $definition) {
            if ($definition->getEntityDefinition()->getEntityName() === $entityName) {
                return true;
            }
        }

        return false;
    }

}
