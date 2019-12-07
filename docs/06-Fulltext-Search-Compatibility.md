# Fulltext Search\* Compatibility

To minimise the impact of migrating, there is a compatibility module available.
[This module](https://github.com/Firesphere/silverstripe-solr-compatibility) will remove the need to completely update all your indexes.
A few small changes to your Search Controller and Results template may be needed however.

The primary difference between FTS and this module is the way it's configured. Therefore some stubs are available in the compatibility module.
You can find this compatibility module here: https://github.com/Firesphere/silverstripe-solr-compatibility

## Init to YML

To get the `init()` method to a YML, the module provides a helper function. This requires the [PHP module `php_yaml`](https://www.php.net/manual/en/book.yaml.php) to be installed.

Easiest to get the YML is to do in your `PageController::init()`

```php
$index = new MyIndexClass();
$index->initToYml();
```


----------

\* From here on called "FTS"