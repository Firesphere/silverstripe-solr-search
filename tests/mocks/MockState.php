<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\States\SiteState;
use SilverStripe\Dev\TestOnly;

class MockState extends SiteState implements TestOnly
{
    public $enabled = false;

    public function appliesToEnvironment(): bool
    {
        return $this->enabled;
    }
}
