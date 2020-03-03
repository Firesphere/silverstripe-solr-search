# Fuzzyness

Fuzzyness can be set at query time, to search a range of similar query terms.

To use fuzzyness, here's an example:

```php
$query->addTerm('SearchTerm', [], [], 5);
```

This example would add a fuzzyness of 5 to the query. "Fuzzyness" refers to the number of edits
required to get from one search term to the next. As an example, 'SearchTerm' with fuzzyness 5 will
return the following:

- Search (four deletes)
- Starch (four deletes, one substitution)
- archers (four deletes, one insert)

And other combinations of five actions (insert, delete, substitute)
