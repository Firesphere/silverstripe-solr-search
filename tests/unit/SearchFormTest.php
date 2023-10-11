<?php

namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Forms\SearchForm;
use Page;
use PageController;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Security\NullSecurityToken;

class SearchFormTest extends SapphireTest
{
    public function testConstruct()
    {
        /** @var Page $page */
        $page = Injector::inst()->get(Page::class);
        /** @var PageController $controller */
        $controller = Injector::inst()->createWithArgs(PageController::class, [$page]);

        /** @var SearchForm $form */
        $form = SearchForm::create(
            $controller,
            'TestForm',
            FieldList::create(),
            FieldList::create()
        );

        $this->assertInstanceOf(NullSecurityToken::class, $form->getSecurityToken());

        $this->assertEquals('GET', $form->FormMethod());
    }
}
