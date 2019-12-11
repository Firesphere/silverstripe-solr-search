# Solr Suggest

If you are using Solr5 or above, you can use the Solr Suggest option. This is enabled by default.

To set this up, have a look at [autosuggest proxy](https://github.com/Firesphere/silverstripe-solr-search/blob/master/client/proxy/autosuggest.php)

Copy this file to a convenient location, e.g. your `docroot/public` folder and edit the contents of your copy, to match your Solr core and location of the Solr instance.

Note that the proxy can not read from the Silverstripe config, thus it needs to be edited manually.

Once that's in place, you can use javascript to get Solr's suggestions, by querying the autosuggest.php file directly.

You need to query the file directly, to prevent a full execution of the whole Silverstripe stack, which is too slow for proper auto suggesting.
Also, make sure the file is accessible.

The output of the file is a JSON object, looking something like this:
```json
{

    "responseHeader": {
        "status": 0,
        "QTime": 0
    },
    "suggest": {
        "Suggester": {
            "home": {
                "numFound": 10,
                "suggestions": [
                    {
                        "term": "home",
                        "weight": 376,
                        "payload": ""
                    },
                    {
                        "term": "homeajaxaction",
                        "weight": 1,
                        "payload": ""
                    },
                    {
                        "term": "homebartonvillevpsbartonvilletccommysitecodememberextensionphp",
                        "weight": 1,
                        "payload": ""
                    },
                    {
                        "term": "homeblauwboomdomainsmyserverpublichtmlcompframeworksrci",
                        "weight": 1,
                        "payload": ""
                    },
                    {
                        "term": "homebrew",
                        "weight": 26,
                        "payload": ""
                    },
                    {
                        "term": "homebrewcurlother",
                        "weight": 1,
                        "payload": ""
                    },
                    {
                        "term": "homebrewhttpsbrewsh",
                        "weight": 1,
                        "payload": ""
                    },
                    {
                        "term": "homebrewing",
                        "weight": 1,
                        "payload": ""
                    },
                    {
                        "term": "homebridgewillstaginpublichtmlsilvershopcodecartordertotalcalculatorphp",
                        "weight": 1,
                        "payload": ""
                    },
                    {
                        "term": "homecategorycategory",
                        "weight": 1,
                        "payload": ""
                    }
                ]
            }
        }
    }
}
```

Which you can then use in your javascript to populate a dropdown.

## Security note

As the query is passed straight in to Solr, there is no option of database SQL injection.


[![Support us](https://enjoy.gitstore.app/repositories/badge-Firesphere/silverstripe-solr-search.svg)](https://enjoy.gitstore.app/repositories/badge-Firesphere/silverstripe-solr-search.svg)
