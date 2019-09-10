<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Interfaces\SiteStateInterface;
use Firesphere\SolrSearch\States\SiteState;
use SilverStripe\Dev\TestOnly;

class MockState extends SiteState implements TestOnly, SiteStateInterface
{
    public $enabled = false;

    public function appliesToEnvironment(): bool
    {
        return $this->enabled;
    }

    /**
     * Reset the SiteState to it's default state
     *
     * @return mixed
     */
    public function setDefaultState()
    {
        $this->activateState('default');
    }

    /**
     * Return the current state of the site
     *
     * @return string
     */
    public function currentState(): string
    {
        return 'default';
    }

    /**
     * Activate a given state. This should only be done if the state is applicable
     *
     * @param string $state
     * @return mixed
     */
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
        return in_array($state, ['default', 'other']);
    }
}
