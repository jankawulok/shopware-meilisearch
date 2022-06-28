<?php

declare(strict_types=1);

namespace Mdnr\Meilisearch\Framework;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

abstract class AbstractMeilisearchDefinition
{
    abstract public function getEntityDefinition(): EntityDefinition;
    abstract public function getId(): string;
    abstract public function getSettingsObject(): array;
    public function fetch(array $ids, Context $context): array
    {
        return [];
    }
    protected function stripText(string $text): string
    {
        // Remove all html elements to save up space
        $text = strip_tags($text);

        if (mb_strlen($text) >= 32766) {
            return mb_substr($text, 0, 32766);
        }

        return $text;
    }
}
