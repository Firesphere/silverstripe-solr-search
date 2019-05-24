<?php


namespace Firesphere\SearchConfig\Results;


use Firesphere\SearchConfig\Queries\BaseQuery;
use SilverStripe\ORM\ArrayList;
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
     * @var ArrayList
     */
    protected $facets;

    /**
     * @var Highlighting
     */
    protected $highlight;

    public function __construct(Result $result, $query)
    {
        $this->query = $query;
        $this->setMatches($result);
        $this->setFacets($result);
        $this->setHighlight($result);
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

    /**
     * @return SearchResult
     */
    public function getMatches()
    {
        return $this->matches;
    }

    /**
     * @param Result $facets
     * @return SearchResult
     */
    protected function setFacets($facets)
    {
        $resultSet = ArrayList::create();
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
                $resultSet->push($facetArray);
            }

        }

        $this->facets = $resultSet;

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
     * @return mixed
     */
    public function getFacets()
    {
        return $this->facets;
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
}