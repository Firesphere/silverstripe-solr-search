# Faceting

## What are facets?

Facets are related topics, e.g. if you have blog posts that are in categories, faceting allows
you to show data about the categories of the blog posts returned in your search results.

For example, say you have 10 posts in "Rants" and 20 posts in "Recipes". If you perform a search where
there are 15 results, 8 of which are in the "Rants" category and 12 of which are in the "Recipes"
category, you can show a list like so:
- Rants (8)
- Recipes (12)

This helps your visitors to narrow their search by filtering for a specific category.

Facets are applied as a combined `AND` query. For example, facet filtering by UserID 1,2 plus Parent 5 
will result in `UserID:1 AND UserID:2 AND Parent:5`

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

This will add filters so that only applicable categories are searched through.

Make sure your Facet Fields are set correctly, as per the documentation.

Any variable from your request that is _not_ a valid Facet will be ignored.
