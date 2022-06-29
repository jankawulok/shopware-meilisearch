<?php

declare(strict_types=1);

namespace Mdnr\Meilisearch\Framework;

use MeiliSearch\Client;

class ClientFactory
{
    private string $host;
    
    private string $masterKey;

    public function __construct(string $host, string $masterKey)
    {
        $this->host = $host;
        $this->masterKey = $masterKey;
    }

    public function createClient(): Client
    {
        return new Client(
            $this->host,
            $this->masterKey
        );
    }
}
