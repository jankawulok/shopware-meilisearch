<?php

declare(strict_types=1);

namespace Mdnr\Meilisearch\Framework;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

abstract class AbstractMeilisearchDefinition
{
  abstract public function getEntityDefinition(): EntityDefinition;
  abstract public function getId(): string;
  abstract public function getSettingsObject(): array;
}
