# Debugging

To debug the executed query, the `BaseIndex` class has a method available to show you all the components of the 
executed query as an array. To get this data, execute the following after you have done your search:
- `$index->getQueryTerms()`

The Index also has a method to get the Query Factory and read data from there.
You can find the output of the factory by getting
- `$index->getQueryFactory()`. See the [API documentation](https://firesphere.github.io/solr-api/classes/Firesphere.SolrSearch.Factories.QueryComponentFactory.html) on how to address each part of the factory.

Through the use of an `Extension` on `BaseIndex`, you can get the Factory through the method `onBeforeSearch`

These two methods supply more information about the executed query.

For more thorough debugging, have a look at the [Solarium docs](https://solarium.readthedocs.io/en/stable/queries/select-query/building-a-select-query/components/debug-component/).

# Logging

Every error triggers the `SolrLogger`, which retrieves the errors from Solr and stores them in the database.

These error logs can only be deleted in dev mode or by administrators.

The logs can be found at the URL `/admin/searchadmin/Firesphere-SolrSearch-Models-SolrLog`

## Clearing out the logs

As an admin, you can truncate the log database via the dev task `dev/tasks/SolrClearErrorsTask`.

Use this with caution though, as it will completely wipe the errors logged and no data will remain at all.

It is strongly advised to only clear out the logs if they have all been reviewed and you are sure nothing serious is wrong.

## x:Unknown indexes

Up until Solr 5, the information about which core threw the error wasn't passed back to the webserver.
Log messages will contain `x:Unknown` in this circumstance. This means the log _is_ there, just that the core that
threw the error isn't known by the server.

## Colour codes

The Gridfield is colour-coded by type of error. This can be disabled by overriding the `GridFieldExtension` class.
