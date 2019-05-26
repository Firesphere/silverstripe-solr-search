<?php


namespace Firesphere\SolrSearch\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Group;
use SilverStripe\Security\Permission;

class DataObjectExtension extends DataExtension
{

    /**
     * @todo get permissions for each group and add them to Solr
     */
    public function getCanView()
    {
        $groups = Group::get()->column('Code');

        $canViewByCode = Permission::get_groups_by_permission($groups);
    }
}
