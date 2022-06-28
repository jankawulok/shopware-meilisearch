<?php
declare(strict_types=1);

namespace Mdnr\Meilisearch\Framework;

use MeiliSearch\Client;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ClientFactory
{
    private SystemConfigService $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public function createClient(): Client
    {
        return new Client(
            $this->systemConfigService->get('Meilisearch.config.urlEndpoint'),
            $this->systemConfigService->get('Meilisearch.config.masterKey')
        );
    }
}
