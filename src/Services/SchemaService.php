<?php


namespace Firesphere\SearchConfig\Services;

use Exception;
use Firesphere\SearchConfig\Helpers\SearchIntrospection;
use Firesphere\SearchConfig\Indexes\BaseIndex;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\ViewableData;

class SchemaService extends ViewableData
{

    /**
     * @var array map SilverStripe DB types to Solr types
     */
    protected static $typeMap = [
        '*'           => 'text',
        'HTMLVarchar' => 'htmltext',
        'Varchar'     => 'string',
        'Text'        => 'string',
        'HTMLText'    => 'htmltext',
        'Boolean'     => 'boolean',
        'Date'        => 'tdate',
        'Datetime'    => 'tdate',
        'ForeignKey'  => 'tint',
        'Int'         => 'tint',
        'Float'       => 'tfloat',
        'Double'      => 'tdouble'
    ];
    /**
     * @var bool
     */
    protected $store = false;
    /**
     * @var string ABSOLUTE Path to template
     */
    protected $template;
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
        $this->introspection->setIndex($index);

        return $this;
    }

    public function getIndexName()
    {
        return $this->index->getIndexName();
    }

    public function getDefaultField()
    {
        return $this->index->getCopyField();
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
    protected function getFieldDefinition($field, &$return)
    {
        $field = $this->introspection->getFieldIntrospection($field);
        foreach ($field as $name => $options) {
            $item = [
                'Field'       => $name,
                'Type'        => static::$typeMap[$options['type']],
                'Indexed'     => 'true',
                'Stored'      => $this->store ? 'true' : 'false',
                'MultiValued' => $options['multi_valued'] ? 'true' : 'false',
                'Destination' => $this->index->getCopyField(),
            ];
            $return->push($item);
        }
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

    public function generateSchema()
    {
        if (!$this->template) {
            // @todo configurable but with default to the current absolute path
            $dir = __DIR__;
            $dir = rtrim(substr($dir, 0, strpos($dir, 'searchconfig') + strlen('searchconfig')), '/');
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
     * @param SearchIntrospection $introspection
     * @return SchemaService
     */
    public function setIntrospection($introspection)
    {
        $this->introspection = $introspection;

        return $this;
    }

    /**
     * @return SearchIntrospection
     */
    public function getIntrospection()
    {
        return $this->introspection;
    }
}
