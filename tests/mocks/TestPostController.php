<?php


namespace Firesphere\SolrSearch\Tests;

use SilverStripe\Control\Controller;
use SilverStripe\Dev\TestOnly;

class TestPostController extends Controller implements TestOnly
{
    private static $allowed_actions = [
        'configure'
    ];

    public function configure()
    {
        return true;
    }
}
