<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Interfaces\SiteStateInterface;
use Firesphere\SolrSearch\Queries\BaseQuery;
use Firesphere\SolrSearch\States\SiteState;
use SilverStripe\Dev\TestOnly;

class MockStateTwo extends SiteState implements TestOnly, SiteStateInterface
{
    protected $state = 'Cow';

    public function appliesToEnvironment(): bool
    {
        return $this->enabled;
    }

    public function currentState(): string
    {
        return $this->state;
    }

    public function activateState($state)
    {
        $this->state = $state;
    }

    /**
     * Is this state applicable to this extension
     * E.g. in case of Fluent, the state "SubsiteID1" does not make sense
     *
     * @param string $state
     * @return bool
     */
    public function stateIsApplicable($state): bool
    {
        return in_array($state, ['Cow', 'Sheep']);
    }

    /**
     * Reset the SiteState to it's default state
     *
     * @param null $state
     * @return mixed
     */
    public function setDefaultState($state = null)
    {
        $this->state = 'Cow';
    }

    /**
     * Method to alter the query. Can be no-op.
     *
     * @param BaseQuery $query
     * @return mixed
     */
    public function updateQuery(&$query)
    {
        // TODO: Implement updateQuery() method.
    }
}
