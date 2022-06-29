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

## Requirements
MeiliSearch v0.27.0 or newer

## Configuration


### Add variables in your .env

| Variable | Possible values | Description |
| ---------|-----------------|-------------|
| `MDNR_MS_HOST`| `http://127.0.0.1:7700` | A MeiliSearch server address|
| `MDNR_MS_INDEXING_ENABLED`| `0` / `1` |  This variable activates the indexing to MeiliSearch|
| `MDNR_MS_MASTER_KEY`| `MASTER_KEY` | The master key of the MeiliSearch server|  
| `MDNR_MS_ENABLED`| `0` / `1` | This variable activates the usage of MeiliSearch for your shop|
| `MDNR_MS_INDEX_PREFIX`| `sw` | This variable defines the prefix for the MeiliSearch indices|
| `MDNR_MS_THROW_EXCEPTION`| `0` / `1` | This variable activates the debug mode, without this variable as = 1 you will get a fallback to mysql without any error message when MeiliSearch is not working|

## Indexing

You should clear your cache before indexing with `bin/console cache:clear` so the changes from .env can be processed.

### Reindex all entities

You can index by executing the command `./bin/console meilisearch:index`, from the prompt of your shell.
