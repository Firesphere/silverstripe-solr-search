# Customisation

All steps of the process, from index to searching, have extension points.

These extension points can be used to alter or update the respective steps

Available extension points:

| Method | Used for | Available on |
|-|-|-|
| `onBeforeSolrConfigureTask` | Alter the Configuration before uploading the configuration | `SolrConfigureTask` |
| `onConfigureIndex` | Operates after an index is added | `SolrConfigureTask` |
| `onAfterSolrConfigureTask` | Executes after Solr is configured. Can be used to check if the configuration is added for example | `SolrConfigureTask` |
| `onBeforeConfig` | Operates before a configuration is uploaded | `SolrConfigureTask` |
| `onBeforeInit` | Update initialisation features | `BaseIndex` |
| `onAfterInit` | Update initialisation features | `BaseIndex` |
| `onBeforeSearch` | Before executing the search, update the query | `BaseIndex` |
| `onAfterSearch` | Manipulate the results | `BaseIndex` |
| `updateSearchResults` | Manipulate the returned result object | `BaseIndex` |
