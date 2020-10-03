# Executing a search

To search, here's an example using all the features, and setting the resulting outcome from the search
onto the current `Controller` to be useable in templates.

More advanced filter options are available, see the [Advanced filters & excludes](05-Advanced-Options/05-Filters-excludes.md)
page for more information.

```php
class SearchController extends PageController
{
    protected $ResultSet;
    
    public function setResultSet($set)
    {
        $this->ResultSet = $set;    
    }

    /**
     * @param array $data Data from the submission
     * @param SearchForm $form Submitted search form
     * @return $this
     */
    public function searchMyContent($data, $form)
    {
        $searchVars = $this->getRequest()->getVars();
        if (!empty($searchVars)) {
            // Set the query, possibly to be used to display it back to the user
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
            $params['sort'] = [MySortableClass::class . '_Created ' => $sort];
            $query->setSort($params['sort']);
            // Alternative:
            $query->addSort(MySortableClass::class . '_Created', $sort);

    
            // Execute the search
            $result = $index->doSearch($query);
    
            // Assuming the controller has this method and variable
            $this->setResultSet($result);
        }
    
        return $this;
    }
}
```

Now, in your template, you could do something like this, to display the results, based on Bootstrap:
```html
<% with $ResultSet %>
    <% if $TotalItems %>
        <div class="clearfix"></div>
        <div class="col-xs-12"><br/>
            <span class="pull-right">
                Results: <span class="js-total-results">$TotalItems</span>
            </span>
        </div>
    <% else %>
        <h6 class="col-xs-12 col-md-6">No results found for your query "<i>$Up.Query.XML</i>"</h6>
        <span class="hidden js-total-results">0</span>
    <% end_if %>
    <% if $Spellcheck.Count %>
        <h6 class="col-xs-12 col-md-6">You might have a spelling error, try
            <a href="{$Top.Link}search/?Query={$SpellcheckLink}">$SpellcheckTitle</a> instead?
        </h6>
    <% end_if %>
    <div class="col-xs-12">
        <% if $TotalItems %>
            <p>&nbsp;</p>
            <% loop $PaginatedMatches %>
                <% include Match %>
            <% end_loop %>
        <% end_if %>
        <% include Pagination %>
        <p>&nbsp;</p>
    </div>
    <nav id="pageNav" role="navigation" class="page-sidebar-widget page-sidebar-nav">
        <div class="row">
            <% with $Facets %>
                <!-- You can repeat the following for each of your Facet Titles -->
                <div class="col-xs-6 col-md-12">
                    <h3 class="h4 page-sidebar-header">YourFacetName</h3>
                    <ul class="list-unstyled">
                        <% loop $YourFacetName %>
                            <li>
                                <a href="$SearchLink" title="$Name $Topic.XML">
                                    $Name ($FacetCount)
                                </a>
                            </li>
                        <% end_loop %>
                    </ul>
                </div>
            <% end_with %>
        </div>
    </nav>
<% end_with %>
```

Example of a `$SearchLink` method, that'll return a link to the Faceted set:

```php
class FacetedObject
{
    public function getSearchLink()
    {
        $controller = Controller::curr();

        $vars = $controller->getRequest()->getVars();

        $vars['MyFacetObject[]'] = $this->ID;

        return $controller->Link('search?' . http_build_query($vars));
    }
}
```
 
