<?php


namespace Firesphere\SolrSearch\Results;

use Firesphere\SolrSearch\Queries\BaseQuery;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\View\ArrayData;
use Solarium\Component\Result\FacetSet;
use Solarium\Component\Result\Highlighting\Highlighting;
use Solarium\Component\Result\Spellcheck\Result as SpellcheckResult;
use Solarium\QueryType\Select\Result\Document;
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
     * @var ArrayList
     */
    protected $spellcheck;

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
        $this->setMatches($result->getDocuments());
        $this->setFacets($result->getFacetSet());
        $this->setHighlight($result->getHighlighting());
        $this->setTotalItems($result->getNumFound());
        if ($query->isSpellcheck()) {
            $this->setSpellcheck($result->getSpellcheck());
        }
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
     * @param array $result
     * @return $this
     */
    protected function setMatches($result): self
    {
        $data = [];
        /** @var Document $item */
        foreach ($result as $item) {
            $data[] = ArrayData::create($item->getFields());
        }

        $docs = ArrayList::create($data);
        $this->matches = $docs;

        return $this;
    }

    /**
     * @param $docID
     * @return string|null
     */
    public function getHighlight($docID): ?string
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
    protected function setHighlight(Highlighting $result): self
    {
        $this->highlight = $result;

        return $this;
    }

    /**
     * @return ArrayList
     */
    public function getFacets(): ArrayList
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
     * @param SpellcheckResult|null $spellcheck
     * @return SearchResult
     */
    public function setSpellcheck($spellcheck): self
    {
        $spellcheckList = [];

        if ($spellcheck && ($suggestions = $spellcheck->getSuggestion(0))) {
            foreach ($suggestions->getWords() as $suggestion) {
                $spellcheckList[] = ArrayData::create($suggestion);
            }
        }

        $this->spellcheck = ArrayList::create($spellcheckList);

        return $this;
    }

    /**
     * @return ArrayList
     */
    public function getSpellcheck(): ArrayList
    {
        return $this->spellcheck;
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
                // @todo bugfix this. It doesn't consistently return something
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
