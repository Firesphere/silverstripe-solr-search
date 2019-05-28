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
            /** @var \Solarium\Component\Result\Debug\Result $result */
            $result = $results->getDebug();
            Debug::message("Query string:\n" . $result->getQueryString());
            Debug::message("Parsed query:\n" . $result->getParsedQuery());
            Debug::message("Query parser:\n" . $result->getQueryParser());
            Debug::message("Explanation:\n" . $result->getExplain());
        }
    }
}
