<?php

declare(strict_types=1);

namespace Mdnr\Meilisearch\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;

class CriteriaParser
{
    public function __construct(EntityDefinitionQueryHelper $helper)
    {
        $this->helper = $helper;
    }

    public function buildAccessor(EntityDefinition $definition, string $fieldName, Context $context): string
    {
        $root = $definition->getEntityName();

        $parts = explode('.', $fieldName);
        if ($root === $parts[0]) {
            array_shift($parts);
        }

        $field = $this->helper->getField($fieldName, $definition, $root, false);

        if ($field instanceof TranslatedField) {
            $ordered = [];
            foreach ($parts as $part) {
                $ordered[] = $part;
            }
            $parts = $ordered;
        }

        if (!$field instanceof PriceField) {
            return implode('.', $parts);
        }

        return implode('.', $parts);
    }

    public function parseSorting(FieldSorting $sorting, EntityDefinition $entity, Context $context): array
    {
        $field = $sorting->getField();
        $direction = $sorting->getDirection();
        $field = $this->helper->getFieldName($entity, $field, $context);
        return [$field . ':' . $direction];
    }

    private function parseNotFilter(NotFilter $filter, EntityDefinition $definition, string $root, Context $context): string
    {
        return '';
    }

    private function parseOrMultiFilter(MultiFilter $filter, EntityDefinition $definition, string $root, Context $context): string
    {
        $orFilter = [];

        foreach ($filter->getQueries() as $nested) {
            $orFilter[] = $this->parseFilter($nested, $definition, $root, $context);
        }
        return '(' . implode(' OR ', $orFilter) . ')';
    }
    private function parseAndMultiFilter(MultiFilter $filter, EntityDefinition $definition, string $root, Context $context): string
    {
        $orFilter = [];

        foreach ($filter->getQueries() as $nested) {
            $orFilter[] = $this->parseFilter($nested, $definition, $root, $context);
        }
        return '(' . implode(' AND ', $orFilter) . ')';
    }

    private function parseMultiFilter(MultiFilter $filter, EntityDefinition $definition, string $root, Context $context): string
    {
        switch ($filter->getOperator()) {
            case MultiFilter::CONNECTION_OR:
                return $this->parseOrMultiFilter($filter, $definition, $root, $context);
            case MultiFilter::CONNECTION_AND:
                return $this->parseAndMultiFilter($filter, $definition, $root, $context);
        }

        // throw new \InvalidArgumentException('Operator ' . $filter->getOperator() . ' not allowed');
    }

    private function parseEqualsFilter(EqualsFilter $filter, EntityDefinition $definition, Context $context): string
    {
        $fieldName = $this->buildAccessor($definition, $filter->getField(), $context);
        $root = $definition->getEntityName();

        $parts = explode('.', $fieldName);
        if ($root === $parts[0]) {
            array_shift($parts);
        }
        $field = $this->helper->getField($fieldName, $definition, $root, false);
        // var_dump($field->getName());
        if (is_bool($filter->getValue())) {
            return $fieldName . '=' . ($filter->getValue() ? 'true' : 'false');
        }

        return "{$fieldName} = {$filter->getValue()}";
    }

    private function parseRangeFilter(RangeFilter $filter, EntityDefinition $definition, Context $context): string
    {
        //todo check if isCheapestPriceField

        $accessor = $this->buildAccessor($definition, $filter->getField(), $context);
        if ($filter->hasParameter(RangeFilter::GT)) {
            return "{$accessor} > {$filter->getParameter(RangeFilter::GT)}";
        }
        if ($filter->hasParameter(RangeFilter::GTE)) {
            return "{$accessor} >= {$filter->getParameter(RangeFilter::GTE)}";
        }
        if ($filter->hasParameter(RangeFilter::LT)) {
            return "{$accessor} < {$filter->getParameter(RangeFilter::LT)}";
        }
        if ($filter->hasParameter(RangeFilter::LTE)) {
            return "{$accessor} <= {$filter->getParameter(RangeFilter::LTE)}";
        }
    }

    private function parseEqualsAnyFilter(EqualsAnyFilter $filter, EntityDefinition $definition, Context $context): string
    {
        $fieldName = $this->buildAccessor($definition, $filter->getField(), $context);

        $f = array_map(function ($val) use ($fieldName) {
            return "{$fieldName} = {$val}";
        }, array_values($filter->getValue()));

        $glue = ' OR ';

        return '(' . implode($glue, $f) . ')';
    }
    private function parseContainsFilter(ContainsFilter $filter, EntityDefinition $definition, Context $context): string
    {
        return '';
    }

    private function parseProductAvailableFilter(ProductAvailableFilter $filter, EntityDefinition $definition, Context $context): string
    {
        $productAvailableFilters = [];
        $visibility = 30;
        $comparator = '=';
        $salesChannelId = null;
        foreach ($filter->getQueries() as $query) {
            if ($query->getField() === 'product.visibilities.visibility') {
                if ($query instanceof RangeFilter && $query->hasParameter(RangeFilter::GTE)) {
                    $comparator = '>=';
                    $visibility = $query->getParameter(RangeFilter::GTE);
                }
                if ($query instanceof RangeFilter && $query->hasParameter(RangeFilter::GT)) {
                    $comparator = '>';
                    $visibility = $query->getParameter(RangeFilter::GT);
                }
            } elseif ($query->getField() === 'product.visibilities.salesChannelId') {
                $salesChannelId =  $query->getValue();
            } else {
                $productAvailableFilters[] = $this->parseFilter($query, $definition, $definition->getEntityName(), $context);
            }
        }
        if ($salesChannelId) {
            $productAvailableFilters[] = "visibilities.{$salesChannelId} {$comparator} {$visibility}";
        }
        return '(' . implode(' ' . $filter->getOperator() . ' ', $productAvailableFilters) . ')';
    }

    private function parseSuffixFilter(SuffixFilter $filter, EntityDefinition $definition, Context $context): string
    {
        return '';
    }

    private function parsePrefixFilter(PrefixFilter $filter, EntityDefinition $definition, Context $context): string
    {
        return '';
    }


    public function parseFilter(Filter $filter, EntityDefinition $definition, string $root, Context $context)
    {
        switch (true) {
            case $filter instanceof ProductAvailableFilter:
                return $this->parseProductAvailableFilter($filter, $definition, $context);
            case $filter instanceof NotFilter:
                return $this->parseNotFilter($filter, $definition, $root, $context);

            case $filter instanceof MultiFilter:
                return $this->parseMultiFilter($filter, $definition, $root, $context);

            case $filter instanceof EqualsFilter:
                return $this->parseEqualsFilter($filter, $definition, $context);

            case $filter instanceof EqualsAnyFilter:
                return $this->parseEqualsAnyFilter($filter, $definition, $context);

            case $filter instanceof ContainsFilter:
                return $this->parseContainsFilter($filter, $definition, $context);

            case $filter instanceof PrefixFilter:
                return $this->parsePrefixFilter($filter, $definition, $context);

            case $filter instanceof SuffixFilter:
                return $this->parseSuffixFilter($filter, $definition, $context);

            case $filter instanceof RangeFilter:
                return $this->parseRangeFilter($filter, $definition, $context);

            default:
                throw new \RuntimeException(sprintf('Unsupported filter %s', \get_class($filter)));
        }
    }
}
