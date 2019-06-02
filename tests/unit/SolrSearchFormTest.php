<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Forms\SolrSearchForm;
use Page;
use PageController;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Security\NullSecurityToken;

class SolrSearchFormTest extends SapphireTest
{
    public function testConstruct()
    {
        /** @var Page $page */
        $page = Injector::inst()->get(Page::class);
        /** @var PageController $controller */
        $controller = Injector::inst()->createWithArgs(PageController::class, [$page]);

        /** @var SolrSearchForm $form */
        $form = SolrSearchForm::create(
            $controller,
            'TestForm',
            FieldList::create(),
            FieldList::create()
        );

        $this->assertInstanceOf(NullSecurityToken::class, $form->getSecurityToken());

        $this->assertEquals('GET', $form->FormMethod());
    }
}
