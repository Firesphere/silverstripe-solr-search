<?php


namespace Firesphere\SolrSearch\Tests;


use Firesphere\SolrSearch\Admins\SearchAdmin;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\View\Requirements;

class SearchAdminTest extends SapphireTest
{
    public function testInit()
    {
        $admin = new SearchAdmin();
        $session = new Session(['hello' => 'world']);
        $admin->getRequest()->setSession($session);
        $admin->init();
        $this->arrayHasKey('solr-search/client/dist/main.js', Requirements::backend()->getCSS());
    }
}
