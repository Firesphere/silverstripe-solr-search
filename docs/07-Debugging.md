# Debugging

To debug the executed query, the `BaseIndex` has a method available to show you all the components of the executed query as an array. To get this data, execute the following after you have done your search:
`$index->getRawQuery()->getData()`
`$index->getQueryTerms()`

These two methods supply more information about the executed query.

For a more thorough debugging, have a look at the Solarium docs for getting the debugging information:
https://solarium.readthedocs.io/en/stable/queries/select-query/building-a-select-query/components/debug-component/

# Logging

Every error triggers the SolrLogger, to retrieve the errors from Solr and store them in the database.

These error logs are deletable only in dev mode or by administrators.

The logs can be found at `admin/searchadmin/Firesphere-SolrSearch-Models-SolrLog` for checking what is wrong.

## Clearing out the logs

As an admin, you should have access to `dev/tasks`, where an option to truncate the log database is available at
`dev/tasks/SolrClearErrorsTask`. Use this with caution though, as it will truncate the errors logged and no data remains
at all.

It is strongly advised to only clear out the logs if they have all been reviewed and you are sure nothing serious is wrong.

## x:Unknown indexes

Because Solr 5 and lower don't return the actual core that threw the error, logging will say `x:Unknown`. This does not mean
the log is not there, it simply means the core that threw the error isn't known by the server.

## Sorting

By default, SilverStripe sorts by ID, causing the oldest errors to show first. Advised is to sort by Timestamp before looking at the actual error.

## Colour codes

The gridfield is colour coded for the type of error. This can be disabled by overriding the GridFieldExtension class and removing
any classes that are unwished for.


[![Support us](https://enjoy.gitstore.app/repositories/badge-Firesphere/silverstripe-solr-search.svg)](https://enjoy.gitstore.app/repositories/badge-Firesphere/silverstripe-solr-search.svg)
