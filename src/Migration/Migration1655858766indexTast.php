<?php

declare(strict_types=1);

namespace Mdnr\Meilisearch\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1655858766indexTast extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1655858766;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            '
CREATE TABLE `meilisearch_index_task` (
`id` binary(16) NOT NULL,
`index` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
`alias` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
`entity` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
`status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`doc_count` int(11) NOT NULL,
`total_count` int(11) NOT NULL,
PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    '
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS meilisearch_index_task');
    }
}
