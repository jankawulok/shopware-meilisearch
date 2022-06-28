<?php
declare(strict_types=1);

namespace Mdnr\Meilisearch\Framework;

class Search
{
    private string $query;

    private int $offset;

    private int $limit;

    private array $filters;

    private array $sort;

    private array $facetsDistribution;

    private array $attributesToRetrieve;

    private array $attributesToCrop;


    public function __construct($query = '', $parameters = [])
    {
        $this->query = $query;
        $this->filters = [];
    }

    public function setQuery($query)
    {
        $this->query = $query;
    }

    public function getQuery()
    {
        return $this->query;
    }
    
    public function addFilter($filter)
    {
        $this->filters[] = $filter;
    }

    public function getFilters()
    {
        return $this->filters;
    }
}
