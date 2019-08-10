<?php

namespace Firesphere\SolrSearch\Extensions;

use Firesphere\SolrSearch\Indexes\BaseIndex;
use LogicException;
use SilverStripe\Core\Extension;
use SilverStripe\Dev\Debug;

/**
 * Class \Firesphere\SolrSearch\Extensions\BaseIndexExtension
 *
 * @property BaseIndex|BaseIndexExtension $owner
 */
class BaseIndexExtension extends Extension
{
    /**
     * Generate a yml version of the init method indexes
     */
    public function initToYml(): void
    {
        if (function_exists('yaml_emit')) {
            /** @var BaseIndex $owner */
            $owner = $this->owner;
            $result = [
                BaseIndex::class => [
                    $owner->getIndexName() =>
                        [
                            'Classes'        => $owner->getClasses(),
                            'FulltextFields' => array_values($owner->getFulltextFields()),
                            'SortFields'     => $owner->getSortFields(),
                            'FilterFields'   => $owner->getFilterFields(),
                            'BoostedFields'  => $owner->getBoostedFields(),
                            'CopyFields'     => $owner->getCopyFields(),
                            'DefaultField'   => $owner->getDefaultField(),
                            'FacetFields'    => $owner->getFacetFields(),
                            'StoredFields'   => $owner->getStoredFields()
                        ]
                ]
            ];

            Debug::dump(yaml_emit($result));

            return;
        }

        throw new LogicException('yaml-emit PHP module missing');
    }
}
