<?php
/**
 * class SearchResult|Firesphere\SolrSearch\Results\SearchResult Result of a query
 *
 * @package Firesphere\SolrSearch\Results
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\SolrSearch\Results;

use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Queries\BaseQuery;
use Firesphere\SolrSearch\Services\SolrCoreService;
use Firesphere\SolrSearch\Traits\SearchResultGetTrait;
use Firesphere\SolrSearch\Traits\SearchResultSetTrait;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\View\ArrayData;
use SilverStripe\View\ViewableData;
use Solarium\Component\Result\Facet\Field;
use Solarium\Component\Result\FacetSet;
use Solarium\Component\Result\Spellcheck\Collation;
use Solarium\Component\Result\Spellcheck\Result as SpellcheckResult;
use Solarium\QueryType\Select\Result\Document;
use Solarium\QueryType\Select\Result\Result;
use stdClass;

/**
 * Class SearchResult is the combined result in a SilverStripe readable way
 *
 * Each of the requested features of a BaseQuery are generated to be easily accessible in the controller.
 * In the controller, each required item can be accessed through the resulting method in this class.
 *
 * @package Firesphere\SolrSearch\Results
 */
class SearchResult extends ViewableData
{
    use SearchResultGetTrait;
    use SearchResultSetTrait;
    /**
     * Query that has been executed
     *
     * @var BaseQuery
     */
    protected $query;

    /**
     * Index the query has run on
     *
     * @var BaseIndex
     */
    protected $index;

    /**
     * Resulting matches from the query on the index
     *
     * @var stdClass|ArrayList|DataList|DataObject
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
        parent::__construct();
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
     * Set the facets to build
     *
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
     *
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
     * Create facets from each faceted class
     *
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
     * Get the facets for each class and their count
     *
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
     * Set the collated spellcheck string
     *
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
     * Set the spellcheck list as an ArrayList
     *
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
     * Get the matches as a Paginated List
     *
     * @param HTTPRequest $request
     * @return PaginatedList
     */
    public function getPaginatedMatches(): PaginatedList
    {
        $request = Controller::curr()->getRequest();
        // Get all the items in the set and push them in to the list
        $items = $this->getMatches();
        /** @var PaginatedList $paginated */
        $paginated = PaginatedList::create($items, $request);
        // Do not limit the pagination, it's done at Solr level
        $paginated->setLimitItems(false)
            // Override the count that's set from the item count
            ->setTotalItems($this->getTotalItems())
            // Set the start to the current page from start.
            ->setPageStart($this->query->getStart())
            // The amount of items per page to display
            ->setPageLength($this->query->getRows());

        return $paginated;
    }

    /**
     * Get the matches as an ArrayList and add an excerpt if possible.
     * {@link static::createExcerpt()}
     *
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

        return ArrayList::create($items)->limit($this->query->getRows());
    }

    /**
     * Set the matches from Solarium as an ArrayList
     *
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
     * Check if the match is a DataObject and exists
     *
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
     * Generate an excerpt for a DataObject
     *
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
     * Get the highlight for a specific document
     *
     * @param $docID
     * @return string
     */
    public function getHighlightByID($docID): string
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
     * Allow overriding of matches with a custom result. Accepts anything you like, mostly
     *
     * @param stdClass|ArrayList|DataList|DataObject $matches
     * @return mixed
     */
    public function setCustomisedMatches($matches)
    {
        $this->matches = $matches;

        return $matches;
    }
}
