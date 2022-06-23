<?php

declare(strict_types=1);

namespace Mdnr\Meilisearch\Framework\Indexing;

use Shopware\Core\Framework\Context;

class MeilisearchIndexingMessage
{
    private IndexingDto $data;

    private ?IndexerOffset $offset;

    private Context $context;

    /**
     * @internal
     */
    public function __construct(IndexingDto $data, ?IndexerOffset $offset, Context $context)
    {
        $this->data = $data;
        $this->offset = $offset;
        $this->context = $context;
    }

    public function getData(): IndexingDto
    {
        return $this->data;
    }

    public function getOffset(): ?IndexerOffset
    {
        return $this->offset;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
