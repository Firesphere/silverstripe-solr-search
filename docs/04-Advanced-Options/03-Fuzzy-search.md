# Fuzzyness

Fuzzyness can be set at query time, to search a range of similar query terms.

To use fuzzyness, here's an example:

```php
$query->addTerm('SearchTerm', [], [], 5);
```

This example would add a fuzzyness of 5 to the query.