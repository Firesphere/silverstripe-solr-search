# Advanced filters & excludes

When performing a search, in addition to simple single-value and array-of-value filters, it’s possible
to build more complex filter/exclude criteria. Some examples of this include:

- Range filters (greater than/less than)
- Geospatial search
- Partial match filters (starts with/ends with)

## Usage

Advanced search/exclude filters are constructed from `Criteria` objects, from the [MinimalCode Solr
Search Criteria](https://github.com/minimalcode-org/search) package. More information and usage 
examples are available [here](https://github.com/minimalcode-org/minimalcode-parent/wiki/4.1-Solr-Search-%28Php%29).

When passing a `Criteria` object to `addFilter` or `addExclude`, the first argument (usually the
field name) can be set to any string value.

```php
$query = new BaseQuery();

// Simple date filter - exclude any pages which have an embargo date in the future
$criteria = Criteria::where('SiteTree_Embargo')
    ->greaterThanEqual('NOW');
$query->addExclude('embargo', $criteria);

// Starts with/ends with filter
$criteria = Criteria::where('SiteTree_Title')
    ->startsWith('prefix')
    ->endsWith('suffix');
$query->addFilter('title-partial-match', $criteria);

// Nested criteria
$topLevel = Criteria::where('SiteTree_ParentID')
    ->is(0);
$criteria = Criteria::where('SiteTree_Title')
    ->startsWith('test')
    ->andWhere($topLevel);
$query->addFilter('top-level-test-pages', $criteria);
```
