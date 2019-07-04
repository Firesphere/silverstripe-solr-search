<?php


namespace Firesphere\SolrSearch\Compat;

use Firesphere\SolrSearch\Indexes\BaseIndex;
use SilverStripe\ORM\DataExtension;

/**
 * Class \Firesphere\SolrSearch\Compat\SubsitesExtension
 *
 * @property BaseIndex|SubsitesExtension $owner
 */
class SubsitesExtension extends DataExtension
{
    public function onAfterInit()
    {
        // Add default support for Subsites.
        if (class_exists('SilverStripe\\Subsites\\Model\\Subsite')) {
            /** @var BaseIndex $owner */
            $owner = $this->owner;
            $addFilterField('SubsiteID');
        }
    }
}
