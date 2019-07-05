<?php


namespace Firesphere\SolrSearch\Results;

use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Queries\BaseQuery;
use Firesphere\SolrSearch\Services\SolrCoreService;
use Firesphere\SolrSearch\Traits\SearchResultGetTrait;
use Firesphere\SolrSearch\Traits\SearchResultSetTrait;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\View\ArrayData;
use Solarium\Component\Result\Facet\Field;
use Solarium\Component\Result\FacetSet;
use Solarium\Component\Result\Spellcheck\Collation;
use Solarium\Component\Result\Spellcheck\Result as SpellcheckResult;
use Solarium\QueryType\Select\Result\Document;
use Solarium\QueryType\Select\Result\Result;

class SearchResult
{
    use SearchResultGetTrait;
    use SearchResultSetTrait;
    /**
     * @var BaseQuery
     */
    protected $query;

    /**
     * @var BaseIndex
     */
    protected $index;

    /**
     * @var ArrayList
     */
    protected $matches;

    /**
     * SearchResult constructor.
     * Funnily enough, the $result contains the actual results, and has methods for the other things.
     * See Solarium docs for this.
     *
     * @param Result $result
     * @param BaseQuery $query
     * @param BaseIndex $index
     */
    public function __construct(Result $result, BaseQuery $query, BaseIndex $index)
    {
        $this->index = $index;
        $this->query = $query;
        $this->setMatches($result->getDocuments())
            ->setFacets($result->getFacetSet())
            ->setHighlight($result->getHighlighting())
            ->setTotalItems($result->getNumFound());
        if ($query->hasSpellcheck()) {
            $this->setSpellcheck($result->getSpellcheck())
                ->setCollatedSpellcheck($result->getSpellcheck());
        }
    }

    /**
     * @param FacetSet|null $facets
     * @return $this
     */
    protected function setFacets($facets): self
    {
        $this->facets = $this->buildFacets($facets);

        return $this;
    }

    /**
     * Build the given list of key-value pairs in to a SilverStripe useable array
     * @param FacetSet|null $facets
     * @return ArrayData
     */
    protected function buildFacets($facets): ArrayData
    {
        $facetArray = [];
        if ($facets) {
            $facetTypes = $this->index->getFacetFields();
            // Loop all available facet fields by type
            foreach ($facetTypes as $class => $options) {
                $facetArray = $this->createFacet($facets, $options, $class, $facetArray);
            }
        }

        // Return an ArrayList of the results
        return ArrayData::create($facetArray);
    }

    /**
     * @param FacetSet $facets
     * @param array $options
     * @param string $class
     * @param array $facetArray
     * @return array
     */
    protected function createFacet($facets, $options, $class, array $facetArray): array
    {
        // Get the facets by its title
        /** @var Field $typeFacets */
        $typeFacets = $facets->getFacet('facet-' . $options['Title']);
        $values = $typeFacets->getValues();
        $results = ArrayList::create();
        // If there are values, get the items one by one and push them in to the list
        if (count($values)) {
            $this->getClassFacets($class, $values, $results);
        }
        // Put the results in to the array
        $facetArray[$options['Title']] = $results;

        return $facetArray;
    }

    /**
     * @param $class
     * @param array $values
     * @param ArrayList $results
     */
    protected function getClassFacets($class, array $values, &$results): void
    {
        $items = $class::get()->byIds(array_keys($values));
        foreach ($items as $item) {
            // Set the FacetCount value to be sorted on later
            $item->FacetCount = $values[$item->ID];
            $results->push($item);
        }
        // Sort the results by FacetCount
        $results = $results->sort(['FacetCount' => 'DESC', 'Title' => 'ASC',]);
    }

    /**
     * @param mixed $collatedSpellcheck
     * @return $this
     */
    public function setCollatedSpellcheck($collatedSpellcheck): self
    {
        /** @var Collation $collated */
        if ($collatedSpellcheck && ($collated = $collatedSpellcheck->getCollations())) {
            $this->collatedSpellcheck = $collated[0]->getQuery();
        }

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
     * @param HTTPRequest $request
     * @return PaginatedList
     */
    public function getPaginatedMatches(HTTPRequest $request): PaginatedList
    {
        // Get all the items in the set and push them in to the list
        $items = $this->getMatches();
        /** @var PaginatedList $paginated */
        $paginated = PaginatedList::create($items, $request);
        // Do not limit the pagination, it's done at Solr level
        $paginated->setLimitItems(false);
        // Override the count that's set from the item count
        $paginated->setTotalItems($this->getTotalItems());
        // Set the start to the current page from start.
        $paginated->setPageStart($this->query->getStart());
        // The amount of items per page to display
        $paginated->setPageLength($this->query->getRows());

        return $paginated;
    }

    /**
     * @return ArrayList
     */
    public function getMatches(): ArrayList
    {
        $matches = $this->matches;
        $items = [];
        $idField = SolrCoreService::ID_FIELD;
        $classIDField = SolrCoreService::CLASS_ID_FIELD;
        foreach ($matches as $match) {
            $item = $this->isDataObject($match, $classIDField);
            if ($item !== false) {
                $this->createExcerpt($idField, $match, $item);
                $items[] = $item;
                $item->destroy();
            }
            unset($match);
        }

        return ArrayList::create($items);
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
     * @param $match
     * @param string $classIDField
     * @return DataObject|bool
     */
    protected function isDataObject($match, string $classIDField)
    {
        if (!$match instanceof DataObject) {
            $class = $match->ClassName;
            /** @var DataObject $match */
            $match = $class::get()->byID($match->{$classIDField});
        }

        return ($match && $match->exists()) ? $match : false;
    }

    /**
     * @param string $idField
     * @param $match
     * @param DataObject $item
     */
    protected function createExcerpt(string $idField, $match, DataObject $item): void
    {
        $item->Excerpt = DBField::create_field(
            'HTMLText',
            str_replace(
                '&#65533;',
                '',
                $this->getHighlightByID($match->{$idField})
            )
        );
    }

    /**
     * @param $docID
     * @return string|null
     */
    public function getHighlightByID($docID): ?string
    {
        $highlights = [];
        if ($this->highlight && $docID) {
            $highlights = [];
            foreach ($this->highlight->getResult($docID) as $field => $highlight) {
                $highlights[] = implode(' (...) ', $highlight);
            }
        }

        return implode(' (...) ', $highlights);
    }

    /**
     * Allow overriding of matches with a custom result
     *
     * @param $matches
     * @return mixed
     */
    public function setCustomisedMatches($matches)
    {
        $this->matches = $matches;

        return $matches;
    }
}
