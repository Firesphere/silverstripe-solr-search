<?php

namespace Firesphere\SearchConfig\Helpers;

use Exception;
use Firesphere\SearchConfig\Indexes\BaseIndex;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;

/**
 * Some additional introspection tools that are used often by the fulltext search code
 */
class SearchIntrospection
{
    protected static $ancestry = array();
    protected static $hierarchy = array();
    /**
     * @var BaseIndex
     */
    protected $index;

    /**
     * Add classes to list, keeping only the parent when parent & child are both in list after add
     */
    public static function add_unique_by_ancestor(&$list, $class)
    {
        // If class already has parent in list, just ignore
        if (self::is_subclass_of($class, $list)) {
            return;
        }

        // Strip out any subclasses of $class already in the list
        $children = ClassInfo::subclassesFor($class);
        $list = array_diff($list, $children);

        // Then add the class in
        $list[] = $class;
    }

    /**
     * Check if class is subclass of (a) the class in $of, or (b) any of the classes in the array $of
     * @static
     * @param  $class
     * @param  $of
     * @return bool
     */
    public static function is_subclass_of($class, $of)
    {
        $ancestry = isset(self::$ancestry[$class]) ? self::$ancestry[$class] : (self::$ancestry[$class] = ClassInfo::ancestry($class));

        return is_array($of) ? (bool)array_intersect($of, $ancestry) : array_key_exists($of, $ancestry);
    }

    /**
     * Does this class, it's parent (or optionally one of it's children) have the passed extension attached?
     */
    public static function has_extension($class, $extension, $includeSubclasses = true)
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
     */
    public static function hierarchy($class, $includeSubclasses = true, $dataOnly = false)
    {
        $key = "$class!" . ($includeSubclasses ? 'sc' : 'an') . '!' . ($dataOnly ? 'do' : 'al');

        if (!isset(self::$hierarchy[$key])) {
            $classes = array_values(ClassInfo::ancestry($class));
            if ($includeSubclasses) {
                $classes = array_unique(array_merge($classes, array_values(ClassInfo::subclassesFor($class))));
            }

            $idx = array_search(DataObject::class, $classes);
            if ($idx !== false) {
                array_splice($classes, 0, $idx + 1);
            }

            if ($dataOnly) {
                foreach ($classes as $i => $class) {
                    if (!DataObject::getSchema()->classHasTable($class)) {
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
     * @todo clean up this messy copy-pasta code
     *
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
     * @param mixed $index
     * @return SearchIntrospection
     */
    public function setIndex($index)
    {
        $this->index = $index;

        return $this;
    }
}
