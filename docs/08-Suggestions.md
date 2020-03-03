# Solr Suggest

If you are using Solr 5 or above, you can use the Solr Suggest option. This is enabled by default.

To set this up, have a look at [autosuggest proxy](https://github.com/Firesphere/silverstripe-solr-search/blob/master/client/proxy/autosuggest.php)

Copy this file to a convenient location, e.g. your `docroot/public` folder and edit the contents of your copy, to match your Solr core and location of the Solr instance.

Note that the proxy can not read from the Silverstripe config, which is why we need to edit it manually.

Once that's in place, you can use Javascript to get Solr's suggestions by querying the `autosuggest.php` file directly.
Doing so will prevent a full execution of the whole Silverstripe application stack,
which is too slow for real-time auto-suggesting.
Also, make sure the file is accessible by the web user.

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

You can then use this data in your Javascript to populate a dropdown.

## Security note

As the query is passed straight in to Solr, there is no possibility of database SQL injection.
