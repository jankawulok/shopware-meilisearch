# Meilisearch plugin for Shopware 6

Meilisearch plugin is meilisearch adapter for shopware/core.
It contains the indexing of entities and an adapter for the entity search.

## Installation
```bash
composer require madonair/shopware-meilisearch
bin/console plugin:refresh
bin/console plugin:install --activate Meilisearch
bin/console cache:clear
```

## Commands

### `meilisearch:index` - Listing of all environment variables