<?php


namespace Firesphere\SolrSearch\Helpers;

use Exception;
use Firesphere\SolrSearch\Factories\DocumentFactory;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use ReflectionClass;
use ReflectionException;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use Solarium\Core\Client\Response;

class SolrUpdate
{
    public const DELETE_TYPE = 'delete';
    public const UPDATE_TYPE = 'update';

    /**
     * @todo use this helper in the IndexTask so it's not duplicated?
     * @param DataObject $object
     * @param string $type
     * @return bool|Response
     * @throws ReflectionException
     * @throws Exception
     */
    public static function updateObject($object, $type)
    {
        $indexes = ClassInfo::subclassesFor(BaseIndex::class);
        $result = false;
        foreach ($indexes as $indexString) {
            // Skip the abstract base
            $ref = new ReflectionClass($indexString);
            if (!$ref->isInstantiable()) {
                continue;
            }

            /** @var BaseIndex $index */
            $index = Injector::inst()->get($indexString);
            // No point in sending a delete for something that's not in the index
            // @todo check the hierarchy, this could be a parent that should be indexed
            if (in_array($object->ClassName, $index->getClasses(), true)) {
                $client = $index->getClient();

                // get an update query instance
                $update = $client->createUpdate();

                // add the delete query and a commit command to the update query
                if ($type === static::DELETE_TYPE) {
                    $update->addDeleteById(sprintf('%s-%s', $object->ClassName, $object->ID));
                } elseif ($type === static::UPDATE_TYPE) {
                    $items = ArrayList::create([$object]);
                    $fields = $index->getFieldsForIndexing();
                    $count = 0;
                    $factory = new DocumentFactory();
                    $factory->setItems($items);
                    $docs = $factory->buildItems($object->ClassName, $fields, $index, $update, 0, $count);
                    $update->addDocuments($docs);
                    $update->addCommit();
                    $client->update($update);
                    // Does this clear out the memory properly?
                    foreach ($docs as $doc) {
                        unset($doc);
                    }
                    gc_collect_cycles();
                }
            }
        }

        return $result;
    }
}
