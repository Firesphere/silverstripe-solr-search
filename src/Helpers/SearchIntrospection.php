<?php

namespace Firesphere\SolrSearch\Helpers;

use Exception;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;

/**
 * Some additional introspection tools that are used often by the fulltext search code
 */
class SearchIntrospection
{
    protected static $ancestry = [];
    protected static $hierarchy = [];
    /**
     * @var BaseIndex
     */
    protected $index;

    /**
     * Check if class is subclass of (a) the class in $of, or (b) any of the classes in the array $of
     * @param  $class
     * @param  $of
     * @return bool
     * @todo remove in favour of DataObjectSchema
     * @static
     */
    public static function isSubclassOf($class, $of)
    {
        $ancestry = self::$ancestry[$class] ?? self::$ancestry[$class] = ClassInfo::ancestry($class);

        return is_array($of) ? (bool)array_intersect($of, $ancestry) : array_key_exists($of, $ancestry);
    }

    /**
     * Does this class, it's parent (or optionally one of it's children) have the passed extension attached?
     * @param $class
     * @param $extension
     * @param bool $includeSubclasses
     * @return bool
     * @throws \ReflectionException
     */
    public static function hasExtension($class, $extension, $includeSubclasses = true)
    {
        foreach (self::hierarchy($class, $includeSubclasses) as $relatedclass) {
            if ($relatedclass::has_extension($extension)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all the classes involved in a DataObject hierarchy - both super and optionally subclasses
     *
     * @static
     * @param string $class - The class to query
     * @param bool $includeSubclasses - True to return subclasses as well as super classes
     * @param bool $dataOnly - True to only return classes that have tables
     * @return array - Integer keys, String values as classes sorted by depth (most super first)
     * @throws \ReflectionException
     */
    public static function hierarchy($class, $includeSubclasses = true, $dataOnly = false): array
    {
        // What does this actually do?
        $key = "$class!" . ($includeSubclasses ? 'sc' : 'an') . '!' . ($dataOnly ? 'do' : 'al');

        if (!isset(self::$hierarchy[$key])) {
            $classes = array_values(ClassInfo::ancestry($class));
            if ($includeSubclasses) {
                $classes = array_unique(array_merge($classes, array_values(ClassInfo::subclassesFor($class))));
            }

            $idx = array_search(DataObject::class, $classes, true);
            if ($idx !== false) {
                array_splice($classes, 0, $idx + 1);
            }

            if ($dataOnly) {
                foreach ($classes as $i => $schemaClass) {
                    if (!DataObject::getSchema()->classHasTable($schemaClass)) {
                        unset($classes[$i]);
                    }
                }
            }

            self::$hierarchy[$key] = $classes;
        }

        return self::$hierarchy[$key];
    }


    /**
     * @param $field
     * @return array
     * @throws Exception
     *
     */
    public function getFieldIntrospection($field)
    {
        $fullfield = str_replace('.', '_', $field);
        $sources = $this->index->getClasses();

        foreach ($sources as $source) {
            $sources[$source]['base'] = DataObject::getSchema()->baseDataClass($source);
            $sources[$source]['lookup_chain'] = [];
        }

        $found = [];

        if (strpos($field, '.') !== false) {
            $lookups = explode(".", $field);
            $field = array_pop($lookups);

            foreach ($lookups as $lookup) {
                $next = [];

                // @todo remove repetition
                foreach ($sources as $source => $baseOptions) {
                    $next = $this->getRelationIntrospection($source, $lookup, $next);
                }

                if (!$next) {
                    return $next;
                } // Early out to avoid excessive empty looping
                $sources = $next;
            }
        }

        $found = $this->getFieldOptions($field, $sources, $fullfield, $found);

        return $found;
    }

    /**
     * @param $source
     * @param $lookup
     * @param array $next
     * @return array
     * @throws Exception
     */
    protected function getRelationIntrospection($source, $lookup, array $next): array
    {
        $source = $this->getSourceName($source);

        foreach (self::hierarchy($source) as $dataClass) {
            $class = null;
            $options = [];
            $singleton = singleton($dataClass);
            $schema = DataObject::getSchema();
            $className = $singleton->getClassName();
            $options['multi_valued'] = false;
            $rel = false;

            if ($hasOne = $schema->hasOneComponent($className, $lookup)) {
                $class = $hasOne;
                $key = $lookup . 'ID';
                $rel = 'has_one';
            } elseif ($hasMany = $schema->hasManyComponent($className, $lookup)) {
                $class = $hasMany;
                $options['multi_valued'] = true;
                $key = $schema->getRemoteJoinField($className, $lookup);
                $rel = 'has_many';
            } elseif ($key = $schema->manyManyComponent($className, $lookup)) {
                $class = $key['childClass'];
                $options['multi_valued'] = true;
                $rel = 'many_many';
            }

            if ($rel !== false) {
                if ($this->checkRelationList($dataClass, $lookup, $rel)) {
                    continue;
                }
                $options = $this->getLookupChain(
                    $options,
                    $lookup,
                    $rel,
                    $dataClass,
                    $class,
                    $key
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

        return $next;
    }

    /**
     * This is used to clean the source name from suffix
     * suffixes are needed to support multiple relations with the same name on different page types
     * @param string $source
     * @return string
     */
    protected function getSourceName($source)
    {
        $explodedSource = explode('_|_', $source);

        return $explodedSource[0];
    }

    /**
     * @param $dataClass
     * @param $lookup
     * @param $relation
     * @return bool
     */
    public function checkRelationList($dataClass, $lookup, $relation)
    {
        // we only want to include base class for relation, omit classes that inherited the relation
        $relationList = Config::inst()->get($dataClass, $relation, Config::UNINHERITED);
        $relationList = $relationList ?? [];

        return (!array_key_exists($lookup, $relationList));
    }

    /**
     * @param array $options
     * @param $lookup
     * @param $type
     * @param $dataClass
     * @param $class
     * @param $key
     * @return array
     */
    public function getLookupChain($options, $lookup, $type, $dataClass, $class, $key)
    {
        $options['lookup_chain'][] = array(
            'call'       => 'method',
            'method'     => $lookup,
            'through'    => $type,
            'class'      => $dataClass,
            'otherclass' => $class,
            'foreignkey' => $key
        );

        return $options;
    }

    /**
     * @param $field
     * @param array $sources
     * @param $fullfield
     * @param array $found
     * @return array
     * @throws \ReflectionException
     */
    protected function getFieldOptions($field, array $sources, $fullfield, array $found): array
    {
        foreach ($sources as $class => $fieldoptions) {
            if (is_int($class)) {
                $class = $fieldoptions;
                $fieldoptions = [];
            }
            $class = $this->getSourceName($class);
            $dataclasses = self::hierarchy($class);

            while (count($dataclasses)) {
                $dataclass = array_shift($dataclasses);
                $type = null;

                $fields = DataObject::getSchema()->databaseFields($class);

                if (isset($fields[$field])) {
                    $type = $fields[$field];
                    $fieldoptions['lookup_chain'][] = [
                        'call'     => 'property',
                        'property' => $field
                    ];
                } else {
                    $singleton = singleton($dataclass);

                    if ($singleton->hasMethod("get$field")) {
                        $type = $singleton->castingClass($field);
                        if (!$type) {
                            $type = 'String';
                        }

                        $fieldoptions['lookup_chain'][] = [
                            'call'   => 'method',
                            'method' => "get$field"
                        ];
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
                    $origin = $fieldoptions['origin'] ?? $dataclass;

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

    /**
     * @param mixed $index
     * @return $this
     */
    public function setIndex($index)
    {
        $this->index = $index;

        return $this;
    }

    /**
     * @return BaseIndex
     */
    public function getIndex(): BaseIndex
    {
        return $this->index;
    }
}
