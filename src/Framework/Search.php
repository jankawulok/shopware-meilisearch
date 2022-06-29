<?php

declare(strict_types=1);

namespace Mdnr\Meilisearch\Framework;

class Search
{
    private string $query;

    private int $offset = 0;

    private int $limit = 100;

    private array $filters;

    private array $sort;

    private array $facetsDistribution;

    private array $attributesToRetrieve;

    private array $attributesToCrop;


    public function __construct($query = '', $parameters = [])
    {
        $this->query = $query;
        $this->filters = [];
        $this->facetsDistribution = [];
    }

    public function setQuery($query)
    {
        $this->query = $query;
    }

    public function getQuery()
    {
        return $this->query;
    }

    public function setOffset($offset)
    {
        $this->offset = $offset;
    }

    public function getOffset()
    {
        return $this->offset;
    }

    public function setLimit($limit)
    {
        $this->limit = $limit;
    }

    public function getLimit()
    {
        return $this->limit;
    }
    
    public function addFilter($filter)
    {
        $this->filters[] = $filter;
    }
    public function addFacetsDistribution($facetsDistribution)
    {
        $this->facetsDistribution[] = $facetsDistribution;
    }
    public function getFacetsDistributions()
    {
        return $this->facetsDistribution;
    }

    public function getParams()
    {
        return [
            'filter' => $this->getFilters(),
            'facetsDistribution' => $this->getFacetsDistributions(),
            'offset' => $this->getOffset(),
            'limit' => $this->getLimit(),
        ];
    }

    public function getFilters()
    {
        return implode(' AND ', $this->filters);
    }
}
