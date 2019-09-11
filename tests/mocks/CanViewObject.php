<?php


namespace Firesphere\SolrSearch\Tests;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

class CanViewObject extends DataObject
{

    /**
     * @param null|Member $member
     * @param array $context
     * @return bool|mixed
     */
    public function canView($member = null, $context = [])
    {
        if (!$member) {
            return false;
        }

        return $member->inGroup('administrators');
    }
}
