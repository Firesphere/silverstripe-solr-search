<?php

namespace Firesphere\SolrSearch\Extensions;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Dev\Debug;
use SilverStripe\ORM\DataExtension;
use Solarium\QueryType\Select\Result\Result;

class BaseIndexExtension extends DataExtension
{

    /**
     * @param Result $results
     */
    public function onAfterSearch($results)
    {
        if (Director::isDev() && Controller::curr()->getRequest()->getVar('debugquery')) {
            Debug::dump($results->getDebug());
        }
    }
}
