<?php


namespace Firesphere\SolrSearch\Tests;


use Firesphere\SolrSearch\States\SiteState;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Versioned\Versioned;

class SiteStateTest extends SapphireTest
{

    public function testStates()
    {
        $default = ['default'];
        $state = SiteState::getStates();
        $this->assertEquals($default, $state);
        SiteState::addState('test');
        $this->assertEquals(['default', 'test'], SiteState::getStates());
        SiteState::addStates(['state1', 'state2']);
        $this->assertEquals(['default', 'test', 'state1', 'state2'], SiteState::getStates());
        SiteState::setStates($default);
        $this->assertEquals($default, SiteState::getStates());
    }

    public function testVariants()
    {
        $this->assertEquals([], SiteState::variants());
    }

    public function testHasExtension()
    {
        $this->assertTrue(SiteState::hasExtension(SiteTree::class, Versioned::class));
        $this->assertFalse(SiteState::hasExtension(TestObject::class, Versioned::class));
    }

    public function testEnabledDisabled()
    {
        $state = new MockState();
        $this->assertFalse($state->isEnabled());
        $state->setEnabled(true);
        $this->assertTrue($state->isEnabled());
        $expected = [
           MockStateTwo::class => new MockStateTwo()
        ];

        $this->assertEquals($expected, SiteState::variants(true));

        $this->assertEquals($state->isEnabled(), $state->appliesToEnvironment());
    }

    public function testState()
    {
        SiteState::variants(true);
        $states = SiteState::currentStates();

        $this->assertEquals('Cow', $states[MockStateTwo::class]);

        SiteState::withState('Sheep');
        $states = SiteState::currentStates();
        $this->assertEquals('Sheep', $states[MockStateTwo::class]);
    }

    public function testIsApplicable()
    {
        $this->assertFalse(SiteState::isApplicable(MockState::class));
        $this->assertTrue(SiteState::isApplicable(MockStateTwo::class));

        $variants = SiteState::$variants;

        $this->assertInstanceOf(MockStateTwo::class, $variants[MockStateTwo::class]);
    }
}
