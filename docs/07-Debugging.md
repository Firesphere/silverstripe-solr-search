# Debugging

To debug the executed query, the `BaseIndex` has a method available to show you all the components of the executed query as an array. To get this data, execute the following after you have done your search:
`$index->getRawQuery()->getData()`
`$index->getQueryTerms()`

These two methods supply more information about the executed query.

For a more thorough debugging, have a look at the Solarium docs for getting the debugging information:
https://solarium.readthedocs.io/en/stable/queries/select-query/building-a-select-query/components/debug-component/