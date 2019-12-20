# Usage

## Getting started

You first _need_ to create an index extending the `Firesphere\SolrSearch\Indexes\BaseIndex` class, or if you are using the compatibility
module, the `SilverStripe\FullTextSearch\Solr\SolrIndex` class.

If you are extending the base Index, it will require a `getIndexName` method
which is used to determine the name of the index to query Solr.

Although the compatibility module provides a core naming scheme, it is still highly recommended
to implement your own method.

**IMPORTANT**

The usage of `YML` as a replacement for the core configuration, it  is not a replacement 
for creating your own Index extending either of the Base Indexes, it is a compliment to it.

`YML` is purely used for the configuration of the index Classes.

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

Note, if you are using defaults (localhost), it is not necessary to add this to your configuration.

The config is used to connect to Solr. This will tell the module where the Solr instance for this index lives and how to connect.

The store is to select the way to configure the solr configuration storage. Options are `file` and a required path, or `post` and a required endpoint to post to.

Post config:
```yml
store:
  mode: 'post'
  path: '/my_post_endpoint'
  uri: 'https://mydomain.com'
```

##### ShowInSearch

`ShowInSearch` is handled by the module itself, so there is no need to configure it within your YML/PHP index definition. When a content author sets this field to 0 via the CMS, then the related Page or File object is actually _removed_ from the applicable Solr core during the next run of the `SolrIndexJob` (which should be configured to run automatically).

Therefore, custom addition of `ShowInSearch` as a filterable or indexable field in YML for example, is likely to cause unexpected behaviour.

The reason for removing `ShowInSearch = false|0` from the indexing process, is to streamline the number of items stored in Solr's indexes. There is no effective need for items to be in the search, if they're not supposed to be displayed.

#### Defining the number of CPU cores

If your server has multiple CPU cores available, you can define the number of cores in the config.
During indexing, this means that each core gets to do an indexing of a group.
The advantage is that it takes all cores available, speeding up the indexing process by the number of cores available.

Because the number of cores can not be determined programmatically, due to access control, you will have to define 
the number of cores available manually.


**NOTE**

Given the current situation in server-land, the default number of cores is 2. This should work fine for
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
| addFulltextField | Add fields to index | No<sup>1</sup> | `$this->addFulltextField('Content');` |
| addAllFulltextField | Add all text fields to index | No | `$this->addAllFulltextFields();` |
| addFilterField | Add fields to use for filtering | No | `$this->addFilterField('ID');` |
| addBoostedField | Fields to boost by on Query time | No | `$this->addBoostedField('Title', ([]/2), 2);`<sup>2</sup> |
| addSortField | Field to sort by | No | `$this->addSortField('Created');` |
| addCopyField | Add a special copy field, besides the default _text | No | `$this->addCopyField('myCopy', ['Fields', 'To', 'Copy']);` |
| addStoredField | Add a field to be stored specifically | No | `$this->addStoredField('LastEdited');` |
| addFacetField | Field to build faceting on | No | `$this->addFacetField(SiteTree::class, ['BaseClass' => SiteTree::class, 'Title' => 'FacetObject', 'Field' => 'FacetObjectID']);` |
 

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
        BaseClass: SilverStripe\CMS\Model\SiteTree
        Field: ID
        Title: TestObject

```

#### MySearchIndex

This name should match the name you provided in your Index extending the `BaseIndex` you are instructed
to create in the first step of this document.

#### Moving from init to YML

The [compatibility module](10-Submodules/01-Fulltext-Search-Compatibility.md) has an optional extension method that allows you to build your index and then generate the YML content for you. See the compatibility module for more details.

## Grouped indexing

Note, grouped indexing is `0` based. Thus, if there are 150 groups to index, the final group to index will be 149 instead of 150.

## Another way to set the config in PHP

You could also use PHP, just like it was, to set the config. For readability however, it's better to use variables for Facets:
```php
    protected $facetFields = [
        RelatedObject::class   => [
            'BaseClass' => SiteTree::class,
            'Field'     => 'RelatedObjectID',
            'Title'     => 'RelationOne'
        ],
        OtherRelatedObject::class => [
            'BaseClass' => SiteTree::class,
            'Field'     => 'OtherRelatedObjectID',
            'Title'     => 'RelationTwo'
        ]
    ];
```

This will generate a facet field in Solr, assuming this relation exists on `SiteTree` or `Page`.

The relation would look like `SiteTree_RelatedObjectID`, where `RelatedObject` the name of the relation reflects.

The Title is used to group all facets by their Title, in the template, this is accessible by looping `$Result.FacetSet.TitleOfTheFacet`

### Important notice

Note, that Facets are relational. For faceting on a relation, omit the origin class (e.g. `SiteTree`), but supply the full relational
path to the facet. e.g. if you want to have facets on `RelationObject->RelationThing()->Relation()->ID`, the Facet declaration should be
`RelationObject.RelationThing.RelationID`. It should always end with an ID that is a `has_one` relation.

Although it can be a `has_many` just as well, and it would be `Relation.ID`, it is not advised, as it creates a lot of overhead.

It would and should work though. If you want to do it that way, there's no stopping you, it's just not advised. 

## Accessing Solr

If available, you can access your Solr instance at `http://mydomain.com:8983`

## Excluding unwanted indexes

To exclude unwanted indexes, it is possible declare a list of _wanted_ indexes in the `YML`

```yaml
Firesphere\SolrSearch\Services\SolrCoreService:
  indexes:
    - CircleCITestIndex
    - Firesphere\SolrSearch\Tests\TestIndex
    - Firesphere\SolrSearch\Tests\TestIndexTwo
    - Firesphere\SolrSearch\Tests\TestIndexThree
```

Looking at the `tests` folder, there is a `TestIndexFour`. This index is not loaded unless explicitly asked.

# Executing a search

To search, here's an example using all the features, and set the resulting outcome from the search
on the current `Controller` to be useable in the templates.

```php
class SearchController extends PageController
{
    /**
     * @param array $data Data from the submission
     * @param SearchForm $form Submitted search form
     * @return $this
     */
    public function searchMyContent($data, $form)
    {
        $searchVars = $this->getRequest()->getVars();
        if (!empty($searchVars)) {
            $this->setQuery($searchVars);
            /** @var BaseIndex $index */
            $index = Injector::inst()->get(MyIndex::class);
    
            // Start building the query, by adding the query term
            $query = new BaseQuery();
            $query->addTerm($searchVars['Query']);
    
            // Set the facets
            $query->setFacetsMinCount(1);
            $facetedFields = $index->getFacetFields();
            foreach ($facetedFields as $className => $field) {
                if (!empty($data[$field['Title']])) {
                    $query->addFacetFilter($field['Title'], $data[$field['Title']]);
                }
            }
    
            // Set the startpoint of the results
            $offset = $this->getRequest()->getVar('start') ?: 0;
            $query->setStart($offset);
    
            // Assuming "Order" is your query parameter that defines the sort order
            $sort = isset($data['Order']) ? strtolower($data['Order']) : 'asc';
    
            // Set the sorting. This can be an array of multiple sorts
            $params['sort'] = MySortableClass::class . '_Created ' . $sort;
            $query->setSort($params);
    
            // Execute the search
            $result = $index->doSearch($query);
    
            // Set the query, possibly to be used to display it back to the user
            $this->setQuery($searchVars);
            // Set the total items found
            $this->setTotal($result->Matches->totalItems);
            // Set the results on the Controller
            $this->setResults($result->Matches);
            // If there is some spellchecking, set it to be used in the template
            $this->setSpellcheck($result->Spellcheck);
            // Set the list of facets
            $this->setFacets($result->Facets);
        }
    
        return $this;
    }
}
```

**NOTE**

This is so called "pseudo code", it may not exactly work, but serves as example.
 
----------
<sup>1</sup> Although not required, it's highly recomended

<sup>2</sup> The second option of an array can be omitted and directly given the boost value
