<?php


namespace Firesphere\SolrSearch\Extensions;

use Exception;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use ReflectionClass;
use ReflectionException;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;

/**
 * Class \Firesphere\SolrSearch\Extensions\DataObjectExtension
 *
 * @property DataObjectExtension|DataObject $owner
 */
class DataObjectExtension extends DataExtension
{
    public function onAfterWrite()
    {
        parent::onAfterWrite();
    }

    /**
     * @throws ReflectionException
     */
    public function onAfterDelete()
    {
        parent::onAfterDelete();

        $indexes = ClassInfo::subclassesFor(BaseIndex::class);

        foreach ($indexes as $index) {
            // Skip the abstract base
            $ref = new ReflectionClass($index);
            if (!$ref->isInstantiable()) {
                continue;
            }

            /** @var BaseIndex $index */
            $index = Injector::inst()->get($index);
            // No point in sending a delete for something that's not in the index
            // @todo check the hierarchy, this could be a parent that should be indexed
            if (in_array($this->owner->ClassName, $index->getClass(), true)) {
                $client = $index->getClient();

                try {
                    // get an update query instance
                    $update = $client->createUpdate();

                    // add the delete query and a commit command to the update query
                    $update->addDeleteQuery('_documentid:' . $this->owner->ClassName . '-' . $this->owner->ID);
                    $update->addCommit();
                } catch (Exception $e) {
                    // Continue, this document doesn't exist, ignore it :)
                    continue;
                }
            }

            $index = null;
        }
    }
}
