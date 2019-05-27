<?php


namespace Firesphere\SolrSearch\Extensions;

use Firesphere\SolrSearch\Indexes\BaseIndex;
use SilverStripe\ORM\DataExtension;

/**
 * Class \Firesphere\SolrSearch\Extensions\SubsitesExtension
 *
 * @property BaseIndex|SubsitesExtension $owner
 */
class SubsitesExtension extends DataExtension
{
    public function onAfterInit()
    {
        // Add default support for Subsites.
        if (class_exists('SilverStripe\\Subsites\\Model\\Subsite')) {
            $this->owner->addFilterField('SubsiteID');
        }
    }
}
