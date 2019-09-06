<?php


namespace Firesphere\SolrSearch\Tests;


use Firesphere\SolrSearch\States\SiteState;
use SilverStripe\Dev\TestOnly;

class MockStateTwo extends SiteState implements TestOnly
{
    public $State = 'Cow';

    public $enabled = true;

    public function appliesToEnvironment(): bool
    {
        return $this->enabled;
    }

    public function currentState()
    {
        return $this->State;
    }

    public function activateState($state)
    {
        $this->State = $state;
    }
}
