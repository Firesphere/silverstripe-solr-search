<?php


namespace Firesphere\SolrSearch\Interfaces;

use Firesphere\SolrSearch\Queries\BaseQuery;

/**
 * Interface SiteStateInterface defines the methods every State altering must implement.
 *
 * These methods must exist in the SiteStates that are available
 *
 * @package Firesphere\SolrSearch\Interfaces
 */
interface SiteStateInterface
{
    /**
     * Is this state applicable to this extension
     * E.g. in case of Fluent, the state "SubsiteID1" does not make sense
     *
     * @param string $state
     * @return bool
     */
    public function stateIsApplicable($state): bool;

    /**
     * Reset the SiteState to it's default state
     *
     * @param string|null $state
     * @return mixed
     */
    public function setDefaultState($state = null);

    /**
     * Return the current state of the site
     *
     * @return string
     */
    public function currentState(): string;

    /**
     * Activate a given state. This should only be done if the state is applicable
     *
     * @param string $state
     * @return mixed
     */
    public function activateState($state);

    /**
     * Method to alter the query. Can be no-op.
     *
     * @param BaseQuery $query
     * @return mixed
     */
    public function updateQuery(&$query);
}
