<?php


namespace Firesphere\SolrSearch\Results;

use Firesphere\SolrSearch\Queries\BaseQuery;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\View\ArrayData;
use Solarium\Component\Result\FacetSet;
use Solarium\Component\Result\Highlighting\Highlighting;
use Solarium\QueryType\Select\Result\Result;

class SearchResult
{
    /**
     * @var BaseQuery
     */
    protected $query;

    /**
     * @var ArrayList
     */
    protected $matches;

    /**
     * @var int
     */
    protected $totalItems;

    /**
     * @var ArrayList
     */
    protected $facets;

    /**
     * @var Highlighting
     */
    protected $highlight;

    /**
     * SearchResult constructor.
     * Funnily enough, the $result contains the actual results, and has methods for the other things.
     * See Solarium docs for this.
     *
     * @param Result $result
     * @param $query
     */
    public function __construct(Result $result, BaseQuery $query)
    {
        $this->query = $query;
        $this->setMatches($result);
        $this->setFacets($result->getFacetSet());
        $this->setHighlight($result->getHighlighting());
        $this->setTotalItems($result->getNumFound());
    }

    /**
     * @param HTTPRequest $request
     * @return PaginatedList
     */
    public function getPaginatedMatches(HTTPRequest $request): PaginatedList
    {
        $paginated = PaginatedList::create($this->matches, $request);
        $paginated->setTotalItems($this->getTotalItems());

        return $paginated;
    }

    /**
     * @return int
     */
    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    /**
     * @param int $totalItems
     * @return $this
     */
    public function setTotalItems($totalItems): self
    {
        $this->totalItems = $totalItems;

        return $this;
    }

    /**
     * Allow overriding of matches with a custom result
     *
     * @param $matches
     * @return mixed
     */
    public function overrideMatches($matches)
    {
        $this->matches = $matches;

        return $matches;
    }

    /**
     * @return ArrayList
     */
    public function getMatches(): ArrayList
    {
        return $this->matches;
    }

    /**
     * @param Result $result
     * @return $this
     */
    protected function setMatches($result): self
    {
        $data = $result->getData();

        $docs = ArrayList::create($data['response']['docs']);
        $this->matches = $docs;

        return $this;
    }

    /**
     * @param $docID
     * @return string|null
     */
    public function getHighlight($docID)
    {
        if ($this->highlight) {
            $hl = [];
            foreach ($this->highlight->getResult($docID) as $field => $highlight) {
                $hl[] = implode(' (...) ', $highlight);
            }

            return implode(' (...) ', $hl);
        }

        return null;
    }

    /**
     * @param Highlighting $result
     * @return $this
     */
    public function setHighlight(Highlighting $result): self
    {
        $this->highlight = $result;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getFacets()
    {
        return $this->facets;
    }

    /**
     * @param FacetSet $facets
     * @return $this
     */
    protected function setFacets(FacetSet $facets): self
    {
        $this->facets = $this->buildFacets($facets);

        return $this;
    }

    /**
     * Build the given list of key-value pairs in to a SilverStripe useable array
     * @param FacetSet $facets
     * @return ArrayData
     */
    protected function buildFacets(FacetSet $facets): ArrayData
    {
        $facetArray = [];
        if ($facets) {
            $facetTypes = $this->query->getFacetFields();
            // Loop all available facet fields by type
            foreach ($facetTypes as $class => $options) {
                // Get the facets by its title
                $typeFacets = $facets->getFacet($options['Title']);
                $values = $typeFacets->getValues();
                $results = ArrayList::create();
                // If there are values, get the items one by one and push them in to the list
                if (count($values)) {
                    $items = $class::get()->byIds(array_keys($values));
                    foreach ($items as $item) {
                        // Set the FacetCount value to be sorted on later
                        $item->FacetCount = $values[$item->ID];
                        $results->push($item);
                    }
                    // Sort the results by FacetCount
                    $results = $results->sort(['FacetCount' => 'DESC', 'Title' => 'ASC',]);
                }
                // Put the results in to the array
                $facetArray[$options['Title']] = $results;
            }
        }

        // Return an ArrayList of the results
        return ArrayData::create($facetArray);
    }
}
