<?php

declare(strict_types=1);

namespace Mdnr\Meilisearch;

use Shopware\Core\Framework\Plugin;
use Composer\Autoload\ClassLoader;

class Meilisearch extends Plugin
{
}

// load vendor packages if plugin is not installed via composer
if (\file_exists(__DIR__ . '/../vendor/autoload.php')) {
    $loader = require_once __DIR__ . '/../vendor/autoload.php';
    if ($loader instanceof ClassLoader) {
        $loader->unregister();
        $loader->register(false);
    }
}
