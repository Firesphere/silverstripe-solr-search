# [Fulltext Search (FTS) Compatibility](https://github.com/Firesphere/silverstripe-solr-compatibility)

To minimise the impact of migrating, there is an
[FTS compatibility module](https://github.com/Firesphere/silverstripe-solr-compatibility) available.
This module will remove the need to completely update all your indexes. A few small changes to your
Search Controller and Results templates are all that may be required.

The primary difference between FTS and this module is the way it's configured, and so the compatibility
module contains some stubs to bridge the two.

## Init to YML

To translate the `init()` method to a YML, the module provides a helper function. This requires the [PHP module `php_yaml`](https://www.php.net/manual/en/book.yaml.php) to be installed.

The easiest way to get the YML is to do the following in your `PageController::init()`

```php
$index = new MyIndexClass();
$index->initToYml();
```

## Stubs

| Method | Stub for | Calls | Purpose |
| ------ | -------- | ----- | ------- |
| `FulltextSearchExtension::search()` | `doSearch()` | `BaseIndex::doSearch()` | Prevent errors from calling the old method |
| `FulltextSearchExtension::updateSearchResults()` | None | None | Return an `ArrayData::class` instead of a `SearchResult::class` |
| `SearchQuery::addSearchTerm()` | `addTerm()` | `BaseQuery::addTerm()` | Stub for old `addSearchTerm` method |
| `SearchQuery::setLimit()` | `setRows()` | `BaseQuery::setRows()` | Help prevent errors moving from `Limit` to `Rows` | 
| `SearchQuery::getLimit()` | `getRows()` | `BaseQuery::getRows()` | Help prevent errors moving from `Limit` to `Rows` |
| `Solr::configure_server()` | Configuration | None | Old way of configuring support |
| `SolrIndex::getIndexName()` | New naming convention | None | Prevent errors moving from the old automated naming to the required naming |

#### Note

When upgrading your Solr instance from Solr4 to Solr5+, a full reindex will be needed regardless.
