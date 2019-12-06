# Usage

## Getting started

First, create an index extending the `BaseIndex` class. This will require a `getIndexName` method
which is used to determine the name of the index to query Solr.

## Configuration

Configuring Solr is done via YML:
```yml
Firesphere\SolrSearch\Services\SolrCoreService:
  config:
    endpoint:
      myhostname:
        host: myhost.com
        port: 8983
        timeout: 10
        # set up timeouts
        index_timeout: 10
        optimize_timeout: 100
        finalize_timeout: 300
        http_method: 'AUTO'
        # commit within 60ms
        commit_within: 60
  # default path settings
  store:
    mode: 'file'
    path: '.solr'
  cpucores: 2

```

The config is used to connect to Solr. This will tell the module where the Solr instance for this index lives and how to connect.

The store is to select the way to configure the solr configuration storage. Options are `file` and a required path, or `post` and a required endpoint to post to.

Post config:
```yml
store:
  mode: 'post'
  path: '/my_post_endpoint'
  uri: 'https://mydomain.com'
```

#### Defining amount of CPU cores

If your server has multiple CPU cores available, you can define the amount of cores in the config.
During indexing, this means that each core gets to do an indexing of a group.
The advantage is that it takes all cores available, speeding up the indexing process by the amount of cores available.

Because the amount of cores can not be determined programmatically, due to access control, you will have to define the amount of cores available manually.


**NOTE**

Given the current situation in server-land, the default amount of cores is 2. This should work fine for
most situations, even if you only have one core available. If you have more cores, you can make this
number larger, of course.

**NOTE**

Using all cores your system has, will make your website pretty slow during indexing! It is adviced to keep
at least one core free for handling page visits, while you're running an index.

### Using init()

Similar to the FulltextSearch module, using init supports all basic methods to add fulltext or filter fields.

Available methods are:

| Method | Purpose | Required | Usage |
|-|-|-|-|
| addClass | Add classes to index | Yes | `$this->addClass(SiteTree::class);` |
| addFulltextField | Add fields to index | No* | `$this->addFulltextField('Content');` |
| addFilterField | Add fields to use for filtering | No | `$this->addFilterField('ID');` |
| addBoostedField | Fields to boost by on Query time | No | `$this->addBoostedField('Title', ([]/2), 2);`** |
| addSortField | Field to sort by | No | `$this->addSortField('Created');` |
| addCopyField | Add a special copy field, besides the default _text | No | `$this->addCopyField('myCopy', ['Fields', 'To', 'Copy']);` |
| addStoredField | Add a field to be stored specifically | No | `$this->addStoredField('LastEdited');` |
| addFacetField | Field to build faceting on | No | `$this->addFacetField(SiteTree::class, ['Title' => 'FacetObject', 'Field' => 'FacetObjectID']);` |
 

### Using YML

```yml
Firesphere\SolrSearch\Indexes\BaseIndex:
  MySearchIndex:
    Classes:
      - SilverStripe\CMS\Model\SiteTree
    FulltextFields:
      - Content
      - TestObject.Title
      - TestObject.TestRelation.Title
    SortFields: 
	  - Created
    FilterFields:
      - Title
      - Created
      - Firesphere\SolrSearch\Tests\TestObject
    BoostedFields:
	  - Title
    CopyFields:
      _text:
        - '*'
    DefaultField: _text
    FacetFields:
      Firesphere\SolrSearch\Tests\TestObject:
        Field: ID
        Title: TestObject

```

#### Moving from init to YML

The [compatibility module](06-Fulltext-Search-Compatibility.md) has an extension method that allows you to build your index and then generate the YML content for you. See the compatibility module for more details.

## Another way to set the config in PHP

You could also use PHP, just like it was, to set the config. For readability however, it's better to use statics for Facets:
```php
    protected $facetFields = [
        RelatedObject::class   => [
            'Field' => 'RelatedObjectID',
            'Title' => 'RelationOne'
        ],
        OtherRelatedObject::class => [
            'Field' => 'OtherRelatedObjectID',
            'Title' => 'RelationTwo'
        ]
    ];
```

This will generate a facet field in Solr, assuming this relation exists on `SiteTree` or `Page`.

The relation would look like `SiteTree_RelatedObjectID`, where `RelatedObject` the name of the relation reflects.

The Title is used to group all facets by their Title, in the template, this is accessible by looping `$Result.FacetSet.TitleOfTheFacet`

## Accessing Solr

If available, you can access your Solr instance at `http://mydomain.com:8983`


----------
\* Although not required, it's highly recomended

\*\* The second option of an array can be omitted and directly given the boost value
