<?php


namespace Firesphere\SearchConfig\Tasks;


use Firesphere\SearchConfig\Helpers\SearchIntrospection;
use Firesphere\SearchConfig\Indexes\BaseIndex;
use Firesphere\SearchConfig\Services\SolrCoreService;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\Versioned\Versioned;
use Solarium\Client;
use Solarium\QueryType\Update\Query\Document\Document;
use Solarium\QueryType\Update\Query\Query;

class SolrIndexTask extends BuildTask
{
    private static $segment = 'SolrIndexTask';

    protected $title = 'Solr Index update';

    protected $description = 'Add or update documents to an existing Solr core.';

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var SearchIntrospection
     */
    protected $introspection;

    /**
     * Implement this method in the task subclass to
     * execute via the TaskRunner
     *
     * @param HTTPRequest $request
     * @return
     * @throws \Exception
     */
    public function run($request)
    {
        // Only index live items.
        // The old FTS module also indexed Draft items. This is unnecessary
        Versioned::set_reading_mode(Versioned::DRAFT . '.' . Versioned::LIVE);
        $this->client = (new SolrCoreService())->getClient();
        $this->introspection = new SearchIntrospection();
        $update = $this->client->createUpdate();

        $class = $request->getVar('class');

        $index = $request->getVar('index');

        /** @var BaseIndex $index */
        $index = Injector::inst()->get($index);

        $classes = $index->getClass();

        $fields = array_merge(
            $index->getFulltextFields(),
            $index->getSortFields(),
            $index->getFilterFields(),
            $index->getBoostedFields()
        );

        foreach ($fields as $field) {
            $fieldList[] = $this->introspection->getFieldIntrospection($field);
        }
        foreach ($classes as $class) {

        }
        $this->buildItem($fieldList);
    }

    protected function buildItem($fieldList)
    {
        $item = [];
        foreach ($fieldList as $field => $properties) {
            $item[$field] = $field['class']::get();
        }
    }

    /**
     * @param DataObject $object
     * @param Query $update
     * @return Document mixed
     */
    protected function createDocument($object, $update)
    {
        /** @var Document $doc */
        $doc = $update->createDocument();
        $doc->setKey($object->ClassName . '-' . $object->ID);
        $doc->addField('ID', $object->ID);
        $doc->addField('ClassName', $object->ClassName);
        $doc->addField('ClassHierarchy', ClassInfo::ancestry($object));

        return $doc;
    }
}