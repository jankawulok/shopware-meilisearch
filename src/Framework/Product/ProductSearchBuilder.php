<?php

declare(strict_types=1);

namespace Mdnr\Meilisearch\Framework\Product;

use Exception;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Mdnr\Meilisearch\Framework\MeilisearchHelper;
use Symfony\Component\HttpFoundation\Request;

class ProductSearchBuilder implements ProductSearchBuilderInterface
{
    private ProductSearchBuilderInterface $decorated;

    private MeilisearchHelper $helper;

    private ProductDefinition $productDefinition;

    public function __construct(
        ProductSearchBuilderInterface $decorated,
        MeilisearchHelper $helper,
        ProductDefinition $productDefinition
    ) {
        $this->decorated = $decorated;
        $this->helper = $helper;
        $this->productDefinition = $productDefinition;
    }

    public function build(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        if (!$this->helper->allowSearch($this->productDefinition, $context->getContext())) {
            $this->decorated->build($request, $criteria, $context);
            return;
        }
        $search = $request->get('search');
        if (\is_array($search)) {
            $term = implode(' ', $search);
        } else {
            $term = (string) $search;
        }

        $term = trim($term);

        if (empty($term)) {
            throw new Exception('search');
        }

        // reset queries and set term to criteria.
        $criteria->resetQueries();

        // elasticsearch will interpret this on demand
        $criteria->setTerm($term);
    }
}
