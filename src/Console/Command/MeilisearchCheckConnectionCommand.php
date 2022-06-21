<?php

namespace Mdnr\Meilisearch\Console\Command;

use Symfony\Component\Console\Command\Command;
use Mdnr\Meilisearch\Framework\MeilisearchHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use MeiliSearch\Client;

class MeilisearchCheckConnectionCommand extends Command
{
  protected static $defaultName = 'meilisearch:check-connection';
  protected MeilisearchHelper $helper;

  public function __construct(MeilisearchHelper $helper)
  {
    parent::__construct();
    $this->helper = $helper;
  }

  protected function configure()
  {
    $this
      ->setName('meilisearch:check-connection')
      ->setDescription('Check if the Meilisearch connection is working');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $output->writeln('Checking if the Meilisearch connection is working...');
    try {
      $this->helper->getClient()->getKeys();;
    } catch (\Exception $e) {
      $output->writeln($e->getMessage());
      return Command::FAILURE;
    }
    $output->writeln('Connection status: OK');
    return Command::SUCCESS;
  }
}
