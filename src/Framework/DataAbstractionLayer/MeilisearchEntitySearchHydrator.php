<?php
declare(strict_types=1);

namespace Mdnr\Meilisearch\Framework\DataAbstractionLayer;

use MeiliSearch\Search\SearchResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

class MeilisearchEntitySearchHydrator extends AbstractMeilisearchSearchHydrator
{
    public function getDecorated(): AbstractMeilisearchSearchHydrator
    {
        throw new DecorationPatternException(self::class);
    }

    public function hydrate(EntityDefinition $definition, Criteria $criteria, Context $context, $result): IdSearchResult
    {
        $data = [];
        foreach ($result->getHits() as $hit) {
            $id = $hit['id'];

            $data[$id] = [
                'primaryKey' => $id,
                'data' => array_merge(
                    $hit['_source'] ?? [],
                    ['id' => $id]
                ),
            ];
        }

        $total = $result->getNbHits();
        if ($criteria->useIdSorting()) {
            $data = $this->sortByIdArray($criteria->getIds(), $data);
        }

        return new IdSearchResult($total, $data, $criteria, $context);
    }
    private function sortByIdArray(array $ids, array $data): array
    {
        $sorted = [];

        foreach ($ids as $id) {
            if (\is_array($id)) {
                $id = implode('-', $id);
            }

            if (\array_key_exists($id, $data)) {
                $sorted[$id] = $data[$id];
            }
        }

        return $sorted;
    }
}
