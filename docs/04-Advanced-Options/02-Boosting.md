# Boost queries

Boosting can be done at both Index time if the configuration supports it, or at Query time.

To boost a certain query at Query time (easiest), use the following syntax:

```php
    $query->addBoostedField('Field_Name', $value);
```

Where value is the boosting value.

## Boosting a single term out of a set

To boost a single term specifically, or on a specific field, you can use the following:

```php
$query->addTerm('My search terms', ['Fields', 'To', 'Boost', 'On'], 2);
```

Where the array of fields should not be empty. The number `2` is the amount of boosting that should be applied
to the fields in the array.

This executes a global search for the term, followed by a boosting for each field in the
field array with a boost status of 2.

Note that the boosted fields do need to be added as a boosted field at Configure time.