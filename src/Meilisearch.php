<?php

declare(strict_types=1);

namespace Mdnr\Meilisearch;

use Composer\Autoload\ClassLoader;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Kernel;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Mdnr\Meilisearch\DependencyInjection\MeilisearchExtension;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;

class Meilisearch extends Plugin
{
    protected $name = 'Meilisearch';

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $this->buildConfig($container);
    }

    /**
     * @return ExtensionInterface
     */
    public function createContainerExtension()
    {
        return new MeilisearchExtension();
    }

    private function buildConfig(ContainerBuilder $container): void
    {
        $locator = new FileLocator('Resources/config');

        $resolver = new LoaderResolver([
            new XmlFileLoader($container, $locator),
            new YamlFileLoader($container, $locator),
            new IniFileLoader($container, $locator),
            new PhpFileLoader($container, $locator),
            new GlobFileLoader($container, $locator),
            new DirectoryLoader($container, $locator),
            new ClosureLoader($container),
        ]);

        $configLoader = new DelegatingLoader($resolver);

        $confDir = $this->getPath() . '/Resources/config';

        $configLoader->load($confDir . '/{packages}/*' . Kernel::CONFIG_EXTS, 'glob');

        $env = $container->getParameter('kernel.environment');
        if (!\is_string($env)) {
            throw new \RuntimeException('Container parameter "kernel.environment" needs to be a string');
        }
        $configLoader->load($confDir . '/{packages}/' . $env . '/*' . Kernel::CONFIG_EXTS, 'glob');
    }
}

// load vendor packages if plugin is not installed via composer
if (\file_exists(__DIR__ . '/../vendor/autoload.php')) {
    $loader = require_once __DIR__ . '/../vendor/autoload.php';
    if ($loader instanceof ClassLoader) {
        $loader->unregister();
        $loader->register(false);
    }
}
