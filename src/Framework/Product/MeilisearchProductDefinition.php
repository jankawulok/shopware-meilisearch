<?php

declare(strict_types=1);

namespace Mdnr\Meilisearch\Framework\Product;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Mdnr\Meilisearch\Framework\AbstractMeilisearchDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceFieldSerializer;

class MeilisearchProductDefinition extends AbstractMeilisearchDefinition
{
    private const PRODUCT_NAME_FIELDS = ['product_translation.translation.name', 'product_translation.translation.fallback_1.name', 'product_translation.translation.fallback_2.name'];
    private const PRODUCT_DESCRIPTION_FIELDS = ['product_translation.translation.description', 'product_translation.translation.fallback_1.description', 'product_translation.translation.fallback_2.description'];
    private const PRODUCT_CUSTOM_FIELDS = ['product_translation.translation.custom_fields', 'product_translation.translation.fallback_1.custom_fields', 'product_translation.translation.fallback_2.custom_fields'];

    protected ProductDefinition $definition;
    protected EntityRepositoryInterface $repository;
    private Connection $connection;
    private PriceFieldSerializer $priceFieldSerializer;
    private CashRounding $rounding;
    private ?array $customFieldsTypes = null;

    public function __construct(Connection $connection, ProductDefinition $definition, EntityRepositoryInterface $repository, PriceFieldSerializer $priceFieldSerializer, CashRounding $rounding)
    {
        $this->connection = $connection;
        $this->definition = $definition;
        $this->repository = $repository;
        $this->priceFieldSerializer = $priceFieldSerializer;
        $this->rounding = $rounding;
    }

    public function getId(): string
    {
        return 'id';
    }

    public function getEntityDefinition(): EntityDefinition
    {
        return $this->definition;
    }

    public function fetch(array $ids, Context $context): array
    {
        $data = $this->fetchProducts(Uuid::fromHexToBytesList($ids), $context);

        $groupIds = [];
        foreach ($data as $row) {
            foreach (json_decode($row['propertyIds'] ?? '[]', true, 512, \JSON_THROW_ON_ERROR) as $id) {
                $groupIds[$id] = true;
            }
            foreach (json_decode($row['optionIds'] ?? '[]', true, 512, \JSON_THROW_ON_ERROR) as $id) {
                $groupIds[$id] = true;
            }
        }

        $groups = $this->fetchPropertyGroups(\array_keys($groupIds));

        $currencies = $context->getExtension('currencies');

        if (!$currencies instanceof EntityCollection) {
            throw new \RuntimeException('Currencies are required for indexing process');
        }

        $tmpField = new PriceField('purchasePrices', 'purchasePrices');

        $documents = [];

        foreach ($data as $id => $item) {
            $visibilities = [];
            foreach (array_filter(explode('|', $item['visibilities'] ?? '')) as $salesChannelVisibility) {
                [$visibility, $salesChannelId] = explode(',', $salesChannelVisibility);
                $visibilities[$salesChannelId] = (int)$visibility;
            }
            $prices = [];
            $purchase = [];
            $purchasePrices = $this->priceFieldSerializer->decode($tmpField, $item['purchasePrices']);
            $price = $this->priceFieldSerializer->decode($tmpField, $item['price']);

            foreach ($currencies as $currency) {
                $key = 'c_' . $currency->getId();

                $prices[$key] = $this->getCurrencyPrice($id, $price, $currency);
                $purchase[$key] = $this->getCurrencyPurchasePrice($purchasePrices, $currency);
            }

            $optionIds = json_decode($item['optionIds'] ?? '[]', true);
            $propertyIds = json_decode($item['propertyIds'] ?? '[]', true);
            $tagIds = json_decode($item['tagIds'] ?? '[]', true);
            $categoriesRo = json_decode($item['categoryIds'] ?? '[]', true);

            $document = [
                'id' => $id,
                'name' => $this->stripText($item['name'] ?? ''),
                'ratingAverage' => (float) $item['ratingAverage'],
                'active' => (bool) $item['active'],
                'available' => (bool) $item['available'],
                'isCloseout' => (bool) $item['isCloseout'],
                'shippingFree' => (bool) $item['shippingFree'],
                'customFields' => $this->formatCustomFields($item['customFields'] ? json_decode($item['customFields'], true) : [], $context),
                'visibilities' => $visibilities,
                'availableStock' => (int) $item['availableStock'],
                'productNumber' => $item['productNumber'],
                'displayGroup' => $item['displayGroup'],
                'sales' => (int) $item['sales'],
                'stock' => (int) $item['stock'],
                'description' => $this->stripText((string) $item['description']),
                'weight' => (float) $item['weight'],
                'width' => (float) $item['width'],
                'length' => (float) $item['length'],
                'height' => (float) $item['height'],
                'price' => $prices,
                'purchasePrices' => $purchase,
                'manufacturerId' => $item['productManufacturerId'],
                'manufacturer' => [
                    'id' => $item['productManufacturerId'],
                    'name' => $item['productManufacturerName'],
                    '_count' => 1,
                ],
                'releaseDate' => isset($item['releaseDate']) ? (new \DateTime($item['releaseDate']))->format('c') : null,
                'createdAt' => isset($item['createdAt']) ? (new \DateTime($item['createdAt']))->format('c') : null,
                'optionIds' => $optionIds,
                'options' => array_map(fn (string $optionId) => ['id' => $optionId, 'groupId' => $groups[$optionId], '_count' => 1], $optionIds),
                'categoriesRo' => array_map(fn (string $categoryId) => ['id' => $categoryId, '_count' => 1], $categoriesRo),
                'properties' => array_map(fn (string $propertyId) => ['id' => $propertyId, 'groupId' => $groups[$propertyId], '_count' => 1], $propertyIds),
                'propertyIds' => $propertyIds,
                'taxId' => $item['taxId'],
                'tags' => array_map(fn (string $tagId) => ['id' => $tagId, '_count' => 1], $tagIds),
                'tagIds' => $tagIds,
                'parentId' => $item['parentId'],
                'childCount' => (int) $item['childCount'],
                'fullText' => $this->stripText(implode(' ', [$item['name'], $item['description'], $item['productNumber']])),
                'fullTextBoosted' => $this->stripText(implode(' ', [$item['name'], $item['description'], $item['productNumber']])),
            ];

            if ($item['cheapest_price_accessor']) {
                $cheapestPriceAccessor = json_decode($item['cheapest_price_accessor'], true);

                foreach ($cheapestPriceAccessor as $rule => $cheapestPriceCurrencies) {
                    foreach ($cheapestPriceCurrencies as $currency => $taxes) {
                        $key = 'cheapest_price_' . $rule . '_' . $currency . '_gross';
                        $document[$key] = $taxes['gross'];

                        $key = 'cheapest_price_' . $rule . '_' . $currency . '_net';
                        $document[$key] = $taxes['net'];
                    }
                }
            }

            $documents[$id] = $document;
        }

        return $documents;
    }
    private function fetchProducts(array $ids, Context $context): array
    {
        $sql = <<<'SQL'
SELECT
    LOWER(HEX(p.id)) AS id,
    IFNULL(p.active, pp.active) AS active,
    p.available AS available,
    :nameTranslated: AS name,
    :descriptionTranslated: AS description,
    :customFieldsTranslated: AS customFields,
    IFNULL(p.available_stock, pp.available_stock) AS availableStock,
    IFNULL(p.rating_average, pp.rating_average) AS ratingAverage,
    p.product_number as productNumber,
    p.sales,
    LOWER(HEX(IFNULL(p.product_manufacturer_id, pp.product_manufacturer_id))) AS productManufacturerId,
    pmt.name AS productManufacturerName,
    IFNULL(p.shipping_free, pp.shipping_free) AS shippingFree,
    IFNULL(p.is_closeout, pp.is_closeout) AS isCloseout,
    IFNULL(p.weight, pp.weight) AS weight,
    IFNULL(p.length, pp.length) AS length,
    IFNULL(p.height, pp.height) AS height,
    IFNULL(p.width, pp.width) AS width,
    IFNULL(p.release_date, pp.release_date) AS releaseDate,
    IFNULL(p.created_at, pp.created_at) AS createdAt,
    IFNULL(p.category_tree, pp.category_tree) AS categoryIds,
    IFNULL(p.option_ids, pp.option_ids) AS optionIds,
    IFNULL(p.property_ids, pp.property_ids) AS propertyIds,
    IFNULL(p.tag_ids, pp.tag_ids) AS tagIds,
    LOWER(HEX(IFNULL(p.tax_id, pp.tax_id))) AS taxId,
    IFNULL(p.stock, pp.stock) AS stock,
    p.purchase_prices as purchasePrices,
    p.price as price,
    p.auto_increment as autoIncrement,
    GROUP_CONCAT(CONCAT(product_visibility.visibility, ',', LOWER(HEX(product_visibility.sales_channel_id))) SEPARATOR '|') AS visibilities,
    p.display_group as displayGroup,
    IFNULL(p.cheapest_price_accessor, pp.cheapest_price_accessor) as cheapest_price_accessor,
    LOWER(HEX(p.parent_id)) as parentId,
    p.child_count as childCount

FROM product p
    LEFT JOIN product pp ON(p.parent_id = pp.id AND pp.version_id = :liveVersionId)
    LEFT JOIN product_manufacturer_translation pmt ON(IFNULL(p.product_manufacturer_id, pp.product_manufacturer_id) = pmt.product_manufacturer_id AND pmt.language_id = :languageId)
    LEFT JOIN product_visibility ON(product_visibility.product_id = p.visibilities AND product_visibility.product_version_id = :liveVersionId)

    LEFT JOIN (
        :productTranslationQuery:
    ) product_translation_main ON (product_translation_main.product_id = p.id)

    LEFT JOIN (
        :productTranslationQuery:
    ) product_translation_parent ON (product_translation_parent.product_id = p.parent_id)

WHERE p.id IN (:ids) AND p.version_id = :liveVersionId AND (p.child_count = 0 OR p.parent_id IS NOT NULL)

GROUP BY p.id
SQL;
        $translationQuery = $this->getTranslationQuery($context);

        $replacements = [
            ':productTranslationQuery:' => $translationQuery->getSQL(),
            ':nameTranslated:' => $this->buildCoalesce(self::PRODUCT_NAME_FIELDS, $context),
            ':descriptionTranslated:' => $this->buildCoalesce(self::PRODUCT_DESCRIPTION_FIELDS, $context),
            ':customFieldsTranslated:' => $this->buildCoalesce(self::PRODUCT_CUSTOM_FIELDS, $context),
        ];

        $data = $this->connection->fetchAll(
            str_replace(array_keys($replacements), $replacements, $sql),
            array_merge([
                'ids' => $ids,
                'liveVersionId' => Uuid::fromHexToBytes($context->getVersionId()),
            ], $translationQuery->getParameters()),
            [
                'ids' => Connection::PARAM_STR_ARRAY,
            ]
        );

        return FetchModeHelper::groupUnique($data);
    }

    private function formatCustomFields(array $customFields, Context $context): array
    {
        $types = $this->getCustomFieldTypes($context);

        foreach ($customFields as $name => $customField) {
            $type = $types[$name] ?? null;


            if ($type === CustomFieldTypes::BOOL) {
                $customFields[$name] = (bool) $customField;
            } elseif (\is_int($customField)) {
                $customFields[$name] = (float) $customField;
            }
        }

        return $customFields;
    }

    private function getTranslationQuery(Context $context): QueryBuilder
    {
        $query = new QueryBuilder($this->connection);

        $productAlias = 'p';
        $query->from('product ', $productAlias);
        $parentIdSelector = 'SELECT DISTINCT `p`.parent_id FROM `product` p WHERE p.id IN(:ids)';
        $query->select(EntityDefinitionQueryHelper::escape($productAlias) . '.id AS product_id');
        $query->where(EntityDefinitionQueryHelper::escape($productAlias) . '.id IN (:ids) OR '
            . EntityDefinitionQueryHelper::escape($productAlias) . '.id IN(' . $parentIdSelector . ')');

        $chain = $context->getLanguageIdChain();

        $firstAlias = 'product_translation.translation';

        foreach ($chain as $i => $language) {
            $languageQuery = new QueryBuilder($this->connection);

            $alias = 'pt';
            $outerAlias = 'product_translation.translation.fallback_' . $i;
            $languageParam = 'languageId' . $i;
            if ($i === 0) {
                $outerAlias = $firstAlias;
                $languageParam = 'languageId';
            }

            $languageQuery->from('product_translation ' . EntityDefinitionQueryHelper::escape($alias));
            $languageQuery->andWhere(EntityDefinitionQueryHelper::escape($alias) . '.language_id = :' . $languageParam);
            $languageQuery->addSelect(EntityDefinitionQueryHelper::escape($alias) . '.product_id');
            $languageQuery->addSelect(EntityDefinitionQueryHelper::escape($alias) . '.name');
            $languageQuery->addSelect(EntityDefinitionQueryHelper::escape($alias) . '.description');
            $languageQuery->addSelect(EntityDefinitionQueryHelper::escape($alias) . '.custom_fields');

            $query->addSelect(
                EntityDefinitionQueryHelper::escape($outerAlias) . '.name AS ' . EntityDefinitionQueryHelper::escape($outerAlias . '.name'),
                EntityDefinitionQueryHelper::escape($outerAlias) . '.description AS ' . EntityDefinitionQueryHelper::escape($outerAlias . '.description'),
                EntityDefinitionQueryHelper::escape($outerAlias) . '.custom_fields AS ' . EntityDefinitionQueryHelper::escape($outerAlias . '.custom_fields'),
            );

            $query->leftJoin(
                $productAlias,
                '(' . $languageQuery->getSQL() . ')',
                EntityDefinitionQueryHelper::escape($outerAlias),
                EntityDefinitionQueryHelper::escape($productAlias) . '.id = '
                    . EntityDefinitionQueryHelper::escape($outerAlias) . '.product_id'
            );
            $query->setParameter($languageParam, Uuid::fromHexToBytes($language));
        }

        return $query;
    }

    private function buildCoalesce(array $fields, Context $context): string
    {
        $fields = array_splice($fields, 0, \count($context->getLanguageIdChain()));

        $coalesce = 'COALESCE(';

        foreach ($fields as $field) {
            foreach (['product_translation_main', 'product_translation_parent'] as $join) {
                $coalesce .= sprintf('%s.`%s`', $join, $field) . ',';
            }
        }

        return substr($coalesce, 0, -1) . ')';
    }

    private function getCurrencyPrice(string $id, ?PriceCollection $prices, CurrencyEntity $currency): array
    {
        if ($prices === null) {
            return [];
        }

        $origin = $prices->getCurrencyPrice($currency->getId());

        if (!$origin) {
            throw new \RuntimeException(sprintf('Missing default price for product %s', $id));
        }

        return $this->getPrice($origin, $currency);
    }

    private function getCurrencyPurchasePrice(?PriceCollection $prices, CurrencyEntity $currency): array
    {
        if ($prices === null) {
            return [];
        }

        if ($prices->count() === 0) {
            return [];
        }

        $origin = $prices->getCurrencyPrice($currency->getId());

        if (!$origin) {
            return [];
        }

        return $this->getPrice(clone $origin, $currency);
    }

    private function getPrice(Price $origin, CurrencyEntity $currency): array
    {
        $price = clone $origin;

        // fallback price returned?
        if ($price->getCurrencyId() !== $currency->getId()) {
            $price->setGross($price->getGross() * $currency->getFactor());
            $price->setNet($price->getNet() * $currency->getFactor());
        }

        $config = $currency->getItemRounding();

        $price->setGross(
            $this->rounding->cashRound($price->getGross(), $config)
        );

        if ($config->roundForNet()) {
            $price->setNet(
                $this->rounding->cashRound($price->getNet(), $config)
            );
        }

        return json_decode(JsonFieldSerializer::encodeJson($price), true);
    }

    private function fetchPropertyGroups(array $propertyIds = []): array
    {
        $sql = 'SELECT LOWER(HEX(id)), LOWER(HEX(property_group_id)) FROM property_group_option WHERE id in (?)';

        return $this->connection->fetchAllKeyValue($sql, [Uuid::fromHexToBytesList($propertyIds)], [Connection::PARAM_STR_ARRAY]);
    }

    private function getCustomFieldTypes(Context $context): array
    {
        if ($this->customFieldsTypes !== null) {
            return $this->customFieldsTypes;
        }

        /** @var array<string, string> $mappings */
        $mappings = $this->connection->fetchAllKeyValue('
SELECT
    custom_field.`name`,
    custom_field.type
FROM custom_field_set_relation
    INNER JOIN custom_field ON(custom_field.set_id = custom_field_set_relation.set_id)
WHERE custom_field_set_relation.entity_name = "product"
');


        return $mappings;
    }

    public function getSettingsObject(): array
    {
        $config =  [
            'distinctAttribute' => 'id',
            'rankingRules' => [
                "words",
                "typo",
                "attribute",
                "proximity",
                "sort",
                "exactness",
                "ratingAverage:desc",
                "sales:desc",
                "availableStock:desc",
                "createdAt:desc"
            ],
            'filterableAttributes' => [
                '*',
                '*.*',
                'categoriesRo.id',
                'manufacturerId',
                'active',
                'available',
                'shippingFree',
                'visibilities.salesChannelId',
                'visibilities.visibility',
                'id',
                'parentId',
                'propertyIds',
                'optionIds',
                'ratingAverage',
                'productNumber',
                'categoryName'
            ],
            'searchableAttributes' => ['productNumber', 'ean', 'name', 'manufacturer.name',  'categoryName', 'description',],
            'sortableAttributes' => ['name'],
        ];

        $channelIds = $this->connection->fetchFirstColumn(
            "SELECT DISTINCT LOWER(HEX(id)) FROM sales_channel WHERE active=1"
        );

        foreach ($channelIds as $id) {
            $config['filterableAttributes'][] = "visibilities.{$id}";
        }

        $currencyIds = $this->connection->fetchFirstColumn(
            "SELECT DISTINCT LOWER(HEX(currency_id)) FROM sales_channel_currency"
        );

        foreach ($currencyIds as $id) {
            $config['filterableAttributes'][] = "price.c_{$id}.gross";
            $config['filterableAttributes'][] = "price.c_{$id}.net";
        }

        return $config;


    }
}
