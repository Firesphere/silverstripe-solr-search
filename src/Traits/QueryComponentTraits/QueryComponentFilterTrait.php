<?php


namespace Firesphere\SolrSearch\Traits;

use Firesphere\SolrSearch\Queries\BaseQuery;
use Minimalcode\Search\Criteria;
use SilverStripe\ORM\DataList;
use SilverStripe\Security\Group;
use SilverStripe\Security\Security;
use Solarium\QueryType\Select\Query\Query;

/**
 * Trait QueryComponentFilterTrait
 *
 * @package Firesphere\SolrSearch\Traits
 */
trait QueryComponentFilterTrait
{
    /**
     * @var BaseQuery Base query that's about to be executed
     */
    protected $query;
    /**
     * @var Query Solarium query
     */
    protected $clientQuery;

    /**
     * Create filter queries
     */
    protected function buildFilters(): void
    {
        $filters = $this->query->getFilter();
        foreach ($filters as $field => $value) {
            $value = is_array($value) ? $value : [$value];
            $criteria = Criteria::where($field)->in($value);
            $this->clientQuery->createFilterQuery('filter-' . $field)
                ->setQuery($criteria->getQuery());
        }
    }

    /**
     * Add filtering on canView
     */
    protected function buildViewFilter(): void
    {
        // Filter by what the user is allowed to see
        $viewIDs = ['null']; // null is always an option as that means publicly visible
        $member = Security::getCurrentUser();
        if ($member && $member->exists()) {
            // Member is logged in, thus allowed to see these
            $viewIDs[] = 'LoggedIn';

            /** @var DataList|Group[] $groups */
            $groups = Security::getCurrentUser()->Groups();
            if ($groups->count()) {
                $viewIDs = array_merge($viewIDs, $groups->column('Code'));
            }
        }
        /** Add canView criteria. These are based on {@link DataObjectExtension::ViewStatus()} */
        $query = Criteria::where('ViewStatus')->in($viewIDs);

        $this->clientQuery->createFilterQuery('ViewStatus')
            ->setQuery($query->getQuery());
    }

    /**
     * Add filtered queries based on class hierarchy
     * We only need the class itself, since the hierarchy will take care of the rest
     */
    protected function buildClassFilter(): void
    {
        if (count($this->query->getClasses())) {
            $classes = $this->query->getClasses();
            $criteria = Criteria::where('ClassHierarchy')->in($classes);
            $this->clientQuery->createFilterQuery('classes')
                ->setQuery($criteria->getQuery());
        }
    }

    /**
     * Remove items to exclude
     */
    protected function buildExcludes(): void
    {
        $filters = $this->query->getExclude();
        foreach ($filters as $field => $value) {
            $value = is_array($value) ? $value : [$value];
            $criteria = Criteria::where($field)
                ->in($value)
                ->not();
            $this->clientQuery->createFilterQuery('exclude-' . $field)
                ->setQuery($criteria->getQuery());
        }
    }
}
