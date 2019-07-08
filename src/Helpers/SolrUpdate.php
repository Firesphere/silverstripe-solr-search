<?php


namespace Firesphere\SolrSearch\Helpers;

use Exception;
use Firesphere\SolrSearch\Factories\DocumentFactory;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Services\SolrCoreService;
use LogicException;
use ReflectionException;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use Solarium\QueryType\Update\Query\Query;
use Solarium\QueryType\Update\Result;

class SolrUpdate
{
    public const DELETE_TYPE_ALL = 'deleteall';
    public const DELETE_TYPE = 'delete';
    public const UPDATE_TYPE = 'update';
    public const CREATE_TYPE = 'create';

    protected $debug = false;

    /**
     * @param ArrayList|DataList|array $items
     * @param string $type
     * @param null|string $index
     * @return bool|Result
     * @throws ReflectionException
     * @throws Exception
     */
    public function updateItems($items, $type, $index = null)
    {
        if ($items === null) {
            throw new LogicException('Can\'t manipulate an empty item set');
        }
        if ($type === static::DELETE_TYPE_ALL) {
            throw new LogicException('To delete all items, call doManipulate directly');
        }
        $indexes = (new SolrCoreService())->getValidIndexes($index);

        $result = false;
        $items = is_iterable($items) ? $items : ArrayList::create([$items]);

        $hierarchy = SearchIntrospection::hierarchy($items->first()->ClassName);

        foreach ($indexes as $indexString) {
            /** @var BaseIndex $index */
            $index = Injector::inst()->get($indexString);
            $classes = $index->getClasses();
            $inArray = array_intersect($classes, $hierarchy);
            // No point in sending a delete|update|create for something that's not in the index
            if (!count($inArray)) {
                continue;
            }

            $result = $this->doManipulate($items, $type, $index);
        }
        gc_collect_cycles();

        return $result;
    }

    /**
     * @param $items
     * @param $type
     * @param BaseIndex $index
     * @return Result
     * @throws Exception
     */
    public function doManipulate($items, $type, BaseIndex $index): Result
    {
        $client = $index->getClient();

        // get an update query instance
        $update = $client->createUpdate();

        // add the delete query and a commit command to the update query
        if ($type === static::DELETE_TYPE) {
            foreach ($items as $item) {
                $update->addDeleteById(sprintf('%s-%s', $item->ClassName, $item->ID));
            }
        } elseif ($type === static::DELETE_TYPE_ALL) {
            $update->addDeleteQuery('*:*');
        } elseif ($type === static::UPDATE_TYPE || $type === static::CREATE_TYPE) {
            $this->updateIndex($index, $items, $update);
        }
        $update->addCommit();

        return $client->update($update);
    }

    /**
     * @param BaseIndex $index
     * @param ArrayList|DataList $items
     * @param Query $update
     * @throws Exception
     */
    public function updateIndex($index, $items, $update): void
    {
        $fields = $index->getFieldsForIndexing();
        $factory = $this->getFactory($items);
        $docs = $factory->buildItems($fields, $index, $update);
        if (count($docs)) {
            $update->addDocuments($docs);
        }
    }

    /**
     * @param ArrayList|DataList $items
     * @return DocumentFactory
     */
    protected function getFactory($items): DocumentFactory
    {
        $factory = Injector::inst()->get(DocumentFactory::class);
        $factory->setItems($items);
        $factory->setClass($items->first()->ClassName);
        $factory->setDebug($this->debug);

        return $factory;
    }

    /**
     * @param mixed $debug
     * @return SolrUpdate
     */
    public function setDebug($debug): SolrUpdate
    {
        $this->debug = $debug;

        return $this;
    }
}
