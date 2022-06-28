<?php

declare(strict_types=1);

namespace Mdnr\Meilisearch\Console\Command;

use MeiliSearch\Client;
use Symfony\Component\Console\Command\Command;
use Mdnr\Meilisearch\Framework\MeilisearchHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Mdnr\Meilisearch\Framework\Indexing\MeilisearchIndexer;

class MeilisearchIndexingCommand extends Command
{
    protected MeilisearchHelper $helper;

    protected Client $client;

    protected MeilisearchIndexer $indexer;

    public function __construct(Client $client, MeilisearchIndexer $indexer, MeilisearchHelper $helper)
    {
        parent::__construct();
        $this->indexer = $indexer;
        $this->helper = $helper;
        $this->client = $client;
    }

    protected function configure()
    {
        $this
            ->setName('meilisearch:index')
            ->setDescription('Index all entities into Meilisearch');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new ShopwareStyle($input, $output);
        $offset = null;
        while ($message = $this->indexer->iterate($offset)) {
            $offset = $message->getOffset();
            $this->indexer->handle($message);
        }
        return Command::SUCCESS;
    }
}
