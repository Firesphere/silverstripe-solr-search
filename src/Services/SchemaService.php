<?php


namespace Firesphere\SearchConfig\Services;

use Firesphere\SearchConfig\Helpers\SearchIntrospection;
use Firesphere\SearchConfig\Indexes\BaseIndex;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
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

    public function getFulltextFieldDefinitions()
    {
        $return = ArrayList::create();
        foreach ($this->index->getFulltextFields() as $field) {
            $this->getFieldDefinition($field, $return);
        }

        return $return;
    }

    public function getFilterFieldDefinitions()
    {
        $return = ArrayList::create();
        foreach ($this->index->getFilterFields() as $field) {
            $this->getFieldDefinition($field, $return);
        }

        return $return;
    }

    /**
     * This is used to clean the source name from suffix
     * suffixes are needed to support multiple relations with the same name on different page types
     * @param string $source
     * @return string
     */
    protected function getSourceName($source)
    {
        $source = explode('_|_', $source);

        return $source[0];
    }


    /**
     * @todo clean up this messy copy-pasta code
     *
     * @param $field
     * @return array
     * @throws \Exception
     */
    public function getFieldIntrospection($field)
    {
        $fullfield = str_replace('.', '_', $field);
        $classes = $this->index->getClass();

        $found = [];

        if (strpos($field, '.') !== false) {
            $lookups = explode('.', $field);
            $field = array_pop($lookups);

            foreach ($lookups as $lookup) {
                $next = [];

                foreach ($classes as $source) {
                    $source = $this->getSourceName($source);

                    foreach (SearchIntrospection::hierarchy($source) as $dataClass) {
                        $class = null;
                        $options = [];
                        $singleton = singleton($dataClass);
                        $schema = DataObject::getSchema();
                        $className = $singleton->getClassName();

                        if ($hasOne = $schema->hasOneComponent($className, $lookup)) {
                            // we only want to include base class for relation, omit classes that inherited the relation
                            $relationList = Config::inst()->get($dataClass, 'has_one', Config::UNINHERITED);
                            $relationList = ($relationList !== null) ? $relationList : [];
                            if (!array_key_exists($lookup, $relationList)) {
                                continue;
                            }

                            $class = $hasOne;
                            $options['lookup_chain'][] = array(
                                'call'       => 'method',
                                'method'     => $lookup,
                                'through'    => 'has_one',
                                'class'      => $dataClass,
                                'otherclass' => $class,
                                'foreignkey' => "{$lookup}ID"
                            );
                        } elseif ($hasMany = $schema->hasManyComponent($className, $lookup)) {
                            // we only want to include base class for relation, omit classes that inherited the relation
                            $relationList = Config::inst()->get($dataClass, 'has_many', Config::UNINHERITED);
                            $relationList = ($relationList !== null) ? $relationList : [];
                            if (!array_key_exists($lookup, $relationList)) {
                                continue;
                            }

                            $class = $hasMany;
                            $options['multi_valued'] = true;
                            $options['lookup_chain'][] = array(
                                'call'       => 'method',
                                'method'     => $lookup,
                                'through'    => 'has_many',
                                'class'      => $dataClass,
                                'otherclass' => $class,
                                'foreignkey' => $schema->getRemoteJoinField($className, $lookup, 'has_many')
                            );
                        } elseif ($manyMany = $schema->manyManyComponent($className, $lookup)) {
                            // we only want to include base class for relation, omit classes that inherited the relation
                            $relationList = Config::inst()->get($dataClass, 'many_many', Config::UNINHERITED);
                            $relationList = ($relationList !== null) ? $relationList : [];
                            if (!array_key_exists($lookup, $relationList)) {
                                continue;
                            }

                            $class = $manyMany['childClass'];
                            $options['multi_valued'] = true;
                            $options['lookup_chain'][] = array(
                                'call'       => 'method',
                                'method'     => $lookup,
                                'through'    => 'many_many',
                                'class'      => $dataClass,
                                'otherclass' => $class,
                                'details'    => $manyMany,
                            );
                        }

                        if (is_string($class) && $class) {
                            if (!isset($options['origin'])) {
                                $options['origin'] = $dataClass;
                            }

                            // we add suffix here to prevent the relation to be overwritten by other instances
                            // all sources lookups must clean the source name before reading it via getSourceName()
                            $next[$class . '_|_' . $dataClass] = $options;
                        }
                    }
                }

                if (!$next) {
                    return $next;
                } // Early out to avoid excessive empty looping
                $classes = $next;
            }
        }

        foreach ($classes as $class => $fieldoptions) {
            if (is_int($class)) {
                $class = $fieldoptions;
                $fieldoptions = [];
            }
            $class = $this->getSourceName($class);
            $dataclasses = SearchIntrospection::hierarchy($class);

            while (count($dataclasses)) {
                $dataclass = array_shift($dataclasses);
                $type = null;

                $fields = DataObject::getSchema()->databaseFields($class);

                if (isset($fields[$field])) {
                    $type = $fields[$field];
                    $fieldoptions['lookup_chain'][] = array('call' => 'property', 'property' => $field);
                } else {
                    $singleton = singleton($dataclass);

                    if ($singleton->hasMethod("get$field") || $singleton->hasField($field)) {
                        $type = $singleton->castingClass($field);
                        if (!$type) {
                            $type = 'String';
                        }

                        if ($singleton->hasMethod("get$field")) {
                            $fieldoptions['lookup_chain'][] = array('call' => 'method', 'method' => "get$field");
                        } else {
                            $fieldoptions['lookup_chain'][] = array('call' => 'property', 'property' => $field);
                        }
                    }
                }

                if ($type) {
                    // Don't search through child classes of a class we matched on. TODO: Should we?
                    $dataclasses = array_diff($dataclasses, array_values(ClassInfo::subclassesFor($dataclass)));
                    // Trim arguments off the type string
                    if (preg_match('/^(\w+)\(/', $type, $match)) {
                        $type = $match[1];
                    }
                    // Get the origin
                    $origin = isset($fieldoptions['origin']) ? $fieldoptions['origin'] : $dataclass;

                    $origin = ClassInfo::shortName($origin);
                    $found["{$origin}_{$fullfield}"] = array(
                        'name'         => "{$origin}_{$fullfield}",
                        'field'        => $field,
                        'fullfield'    => $fullfield,
                        'origin'       => $origin,
                        'class'        => $dataclass,
                        'lookup_chain' => $fieldoptions['lookup_chain'],
                        'type'         => $type,
                        'multi_valued' => isset($fieldoptions['multi_valued']) ? true : false,
                    );
                }
            }
        }

        return $found;
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
     * @param $field
     * @param ArrayList $return
     */
    protected function getFieldDefinition($field, &$return)
    {
        $field = $this->getFieldIntrospection($field);
        foreach ($field as $name => $options) {
            $item = [
                'Field'       => $options['name'],
                'Type'        => static::$typeMap[$options['type']],
                'Indexed'     => 'true',
                'Stored'      => $this->store ? 'true' : 'false',
                'MultiValued' => $options['multi_valued'] ? 'true' : 'false',
                'Destination' => $this->index->getCopyField(),
            ];
            $return->push($item);
        }
    }
}
