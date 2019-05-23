<?php


namespace Firesphere\SearchConfig\Services;

use Exception;
use Firesphere\SearchConfig\Helpers\SearchIntrospection;
use Firesphere\SearchConfig\Helpers\Statics;
use Firesphere\SearchConfig\Indexes\BaseIndex;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ViewableData;

class SchemaService extends ViewableData
{

    /**
     * @var bool
     */
    protected $store = false;
    /**
     * @var string ABSOLUTE Path to template
     */
    protected $template;

    /**
     * @var string ABSOLUTE Path to types.ss template
     */
    protected $typesTemplate;
    /**
     * @var BaseIndex
     */
    protected $index;

    /**
     * @var SearchIntrospection
     */
    protected $introspection;

    public function __construct()
    {
        parent::__construct();
        $this->introspection = Injector::inst()->get(SearchIntrospection::class);
    }

    /**
     * @return BaseIndex
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * @param BaseIndex $index
     * @return SchemaService
     */
    public function setIndex($index)
    {
        $this->index = $index;
        // Add the index to the introspection as well, there's no need for a separate call here
        // We're loading this core, why would we want the introspection from a different index?
        $this->introspection->setIndex($index);

        return $this;
    }

    /**
     * @return string
     */
    public function getIndexName()
    {
        return $this->index->getIndexName();
    }

    /**
     * @return string
     */
    public function getDefaultField()
    {
        return $this->index->getDefaultField();
    }

    /**
     * @return ArrayList
     * @throws Exception
     */
    public function getFulltextFieldDefinitions()
    {
        $return = ArrayList::create();
        foreach ($this->index->getFulltextFields() as $field) {
            $this->getFieldDefinition($field, $return);
        }

        return $return;
    }

    /**
     * @param $field
     * @param ArrayList $return
     * @throws Exception
     */
    protected function getFieldDefinition($field, &$return, $copyField = null)
    {
        $field = $this->introspection->getFieldIntrospection($field);
        $typeMap = Statics::getTypeMap();
        foreach ($field as $name => $options) {
            $item = [
                'Field'       => $name,
                'Type'        => $typeMap[$options['type']],
                'Indexed'     => 'true',
                'Stored'      => $this->store ? 'true' : 'false',
                'MultiValued' => $options['multi_valued'] ? 'true' : 'false',
                'Destination' => $copyField
            ];
            $return->push($item);
        }
    }

    /**
     * @return ArrayList
     */
    public function getCopyFields()
    {
        $return = ArrayList::create();
        foreach (array_keys($this->index->getCopyFields()) as $copyField) {
            $item = [
                'Field' => $copyField
            ];

            $return->push($item);
        }

        return $return;
    }

    /**
     * @return ArrayList
     * @throws Exception
     */
    public function getCopyFieldDefinitions()
    {
        $copyFields = $this->index->getCopyFields();

        $return = ArrayList::create();

        foreach ($copyFields as $field => $fields) {
            // Allow all fields to be in a copyfield via a shorthand
            if ($fields[0] === '*') {
                $fields = $this->index->getFulltextFields();
            }

            foreach ($fields as $copyField) {
                $this->getFieldDefinition($copyField, $return, $field);
            }
        }

        return $return;
    }

    /**
     * @return ArrayList
     * @throws Exception
     */
    public function getFilterFieldDefinitions()
    {
        $return = ArrayList::create();
        foreach ($this->index->getFilterFields() as $field) {
            $this->getFieldDefinition($field, $return);
        }

        return $return;
    }

    public function getTypes()
    {
        if (!$this->typesTemplate) {
            // @todo configurable but with default to the current absolute path
            $dir = ModuleLoader::getModule('firesphere/solr-search')->getPath();
            $this->setTypesTemplate($dir . '/Solr/5/templates/types.ss');
        }

        return $this->renderWith($this->getTypesTemplate());
    }

    /**
     * @return string
     */
    public function getTypesTemplate()
    {
        return $this->typesTemplate;
    }

    /**
     * @param string $typesTemplate
     * @return SchemaService
     */
    public function setTypesTemplate($typesTemplate)
    {
        $this->typesTemplate = $typesTemplate;

        return $this;
    }

    public function generateSchema()
    {
        if (!$this->template) {
            $dir = ModuleLoader::getModule('firesphere/solr-search')->getPath();
            $this->setTemplate($dir . '/Solr/5/templates/schema.ss');
        }

        return $this->renderWith($this->getTemplate());
    }

    /**
     * @return string
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * @param string $template
     * @return SchemaService
     */
    public function setTemplate($template)
    {
        $this->template = $template;

        return $this;
    }

    public function getExtrasPath()
    {
        // @todo configurable but with default to the current absolute path
        $dir = __DIR__;
        $dir = rtrim(substr($dir, 0, strpos($dir, 'searchconfig') + strlen('searchconfig')), '/');

        $confDirs = SolrCoreService::config()->get('paths');

        return sprintf($confDirs['extras'], $dir);
    }

    /**
     * @return bool
     */
    public function getStore()
    {
        return $this->store;
    }

    /**
     * @param bool $store
     * @return SchemaService
     */
    public function setStore($store)
    {
        $this->store = $store;

        return $this;
    }

    /**
     * @return SearchIntrospection
     */
    public function getIntrospection()
    {
        return $this->introspection;
    }

    /**
     * @param SearchIntrospection $introspection
     * @return SchemaService
     */
    public function setIntrospection($introspection)
    {
        $this->introspection = $introspection;

        return $this;
    }
}
