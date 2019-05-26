<?php


namespace Firesphere\SolrSearch\Results;

use Firesphere\SolrSearch\Queries\BaseQuery;
use SilverStripe\Control\Controller;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\View\ArrayData;
use Solarium\Component\Result\Highlighting\Highlighting;
use Solarium\QueryType\Select\Result\Result;

class SearchResult
{
    /**
     * @var BaseQuery
     */
    protected $query;

    /**
     * @var Controller
     */
    protected $controller;

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

    public function __construct(Result $result, $query, $controller)
    {
        $this->query = $query;
        $this->setMatches($result);
        $this->setFacets($result);
        $this->setHighlight($result);
        $this->setTotalItems($result->getNumFound());
        $this->setController($controller);
    }

    public function getPaginatedMatches($request)
    {
        $paginated = PaginatedList::create($this->matches, $request);
        $paginated->setTotalItems($this->getTotalItems());

        return $paginated;
    }

    /**
     * @return int
     */
    public function getTotalItems()
    {
        return $this->totalItems;
    }

    /**
     * @param int $totalItems
     * @return SearchResult
     */
    public function setTotalItems($totalItems)
    {
        $this->totalItems = $totalItems;

        return $this;
    }

    public function overrideMatches($matches)
    {
        $this->matches = $matches;

        return $matches;
    }

    /**
     * @return ArrayList
     */
    public function getMatches()
    {
        return $this->matches;
    }

    /**
     * @param Result $result
     * @return SearchResult
     */
    protected function setMatches($result)
    {
        $data = $result->getData();

        $docs = ArrayList::create($data['response']['docs']);
        $this->matches = $docs;

        return $this;
    }

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
     * @param Result $result
     * @return SearchResult
     */
    public function setHighlight($result)
    {
        $this->highlight = $result->getHighlighting();

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
     * @param Result $facets
     * @return SearchResult
     */
    protected function setFacets($facets)
    {
        // @todo clean up this mess
        $facetArray = [];
        if ($facets = $facets->getFacetSet()) {
            $facetTypes = $this->query->getFacetFields();
            foreach ($facetTypes as $class => $options) {
                $typeFacets = $facets->getFacet($options['Title']);
                $values = $typeFacets->getValues();
                $results = ArrayList::create();
                if (count($values)) {
                    $items = $class::get()->byIds(array_keys($values));
                    foreach ($items as $item) {
                        $item->FacetCount = $values[$item->ID];
                        $results->push($item);
                    }
                    $results = $results->sort(['FacetCount' => 'DESC', 'Title' => 'ASC',]);
                }
                $facetArray[$options['Title']] = $results;
            }
        }

        $this->facets = ArrayData::create($facetArray);

        return $this;
    }

    /**
     * @return Controller
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * @param Controller $controller
     * @return SearchResult
     */
    public function setController($controller)
    {
        $this->controller = $controller;

        return $this;
    }
}
