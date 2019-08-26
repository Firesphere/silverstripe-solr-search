<?php


namespace Firesphere\SolrSearch\Tests;


use Firesphere\SolrSearch\Admins\SearchAdmin;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\View\Requirements;

class SearchAdminTest extends SapphireTest
{
    public function testInit()
    {
        $admin = new SearchAdmin();
        $admin->init();
        $this->arrayHasKey('solr-search/client/dist/main.js', Requirements::backend()->getCSS());
    }
}
