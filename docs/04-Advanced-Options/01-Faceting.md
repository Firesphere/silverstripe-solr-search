# Faceting

## What are facets

Facets are related topics, e.g. when you have categories in your blogposts, faceting allows
you to show the categories that are in your search results.

For example, if you have 10 posts in "Example category" and 20 posts in "Example category 2",
and the search returns 15 posts, 8 of those are in the "Example category" and 12 of those are (also)
in the "Example category 2", you can show a list like so:
- Example category (8)
- Example category 2 (12)

This helps your visitors to narrow their search, for only a specific category.

## Applying facets

To configure Facets, have a look at the [usage](../03-Usage.md) documentation.

To use them, this example should get you started:

```php
    $data = Controller::curr()->getRequest()->getVars();
    $index = Injector::inst()->get(MyIndex::class);
    $query = Injector::inst()->get(BaseQuery::class);
    $facetedFields = $index->getFacetFields();
    foreach ($facetedFields as $className => $field) {
        // Title of your field, as defined in the FacetFields
        if (!empty($data[$field['Title']])) {
            // Add a facet filter with its title and the value from the request data
            $query->addFacetFilter($field['Title'], $data[$field['Title']]);
        }
    }
```

This will add filters, so only applicable categories are searched through.

Make sure your Facet Fields are set correctly, as per the documentation.

Any variable from your request, that is not a valid Facet will be ignored.