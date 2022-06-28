<?php

declare(strict_types=1);

namespace Mdnr\Meilisearch\Test;

use PHPUnit\Framework\TestCase;
use Mdnr\Meilisearch\Framework\ClientFactory;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Shopware\Core\System\SystemConfig\SystemConfigLoader;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Doctrine\DBAL\Connection;

class ClientFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testCreateClient(): void
    {
        $config = new SystemConfigService(
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get('system_config.repository'),
            $this->getContainer()->get(ConfigReader::class),
            $this->getContainer()->get(SystemConfigLoader::class),
            $this->getContainer()->get('event_dispatcher')
        );

        $config->set('Meilisearch.config.urlEndpoint', 'http://127.0.0.1:7700');
        $config->set('Meilisearch.config.masterKey', 'MASTER_KEY');

        $clientFactory = new ClientFactory($config);
        $client = $clientFactory->createClient();
        static::assertSame('MeiliSearch\Client', get_class($client));
        static::assertNotNull($client->getKeys());
    }
}
