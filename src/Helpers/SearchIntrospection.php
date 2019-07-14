<?php

namespace Firesphere\SolrSearch\Helpers;

use Exception;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use ReflectionException;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectSchema;

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
     * @var array
     */
    protected $found = [];

    /**
     * Check if class is subclass of (a) the class in $instanceOf, or (b) any of the classes in the array $instanceOf
     * @param string $class Name of the class to test
     * @param array|string $instanceOf Class ancestry it should be in
     * @return bool
     * @todo remove in favour of DataObjectSchema
     * @static
     */
    public static function isSubclassOf($class, $instanceOf)
    {
        $ancestry = self::$ancestry[$class] ?? self::$ancestry[$class] = ClassInfo::ancestry($class);

        return is_array($instanceOf) ?
            (bool)array_intersect($instanceOf, $ancestry) :
            array_key_exists($instanceOf, $ancestry);
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
            $lookups = explode('.', $field);
            $field = array_pop($lookups);

            foreach ($lookups as $lookup) {
                $next = [];

                // @todo remove repetition
                foreach ($sources as $source => $baseOptions) {
                    $next = $this->getRelationIntrospection($source, $lookup, $next);
                }

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

        foreach (self::getHierarchy($source) as $dataClass) {
            $options = [];
            $singleton = singleton($dataClass);
            $schema = DataObject::getSchema();
            $className = $singleton->getClassName();
            $options['multi_valued'] = false;

            [$class, $key, $relationType, $options] = $this->getRelationData($lookup, $schema, $className, $options);

            if ($relationType !== false) {
                if ($this->checkRelationList($dataClass, $lookup, $relationType)) {
                    continue;
                }
                $options = $this->getLookupChain(
                    $options,
                    $lookup,
                    $relationType,
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
     * Get all the classes involved in a DataObject hierarchy - both super and optionally subclasses
     *
     * @static
     * @param string $class - The class to query
     * @param bool $includeSubclasses - True to return subclasses as well as super classes
     * @param bool $dataOnly - True to only return classes that have tables
     * @return array - Integer keys, String values as classes sorted by depth (most super first)
     * @throws ReflectionException
     */
    public static function getHierarchy($class, $includeSubclasses = true, $dataOnly = false): array
    {
        // Generate the unique key for this class and it's call type
        // It's a short-lived cache key for the duration of the request
        $cacheKey = sprintf('%s-%s-%s', $class, $includeSubclasses ? 'sc' : 'an', $dataOnly ? 'do' : 'al');

        if (!isset(self::$hierarchy[$cacheKey])) {
            $classes = array_values(ClassInfo::ancestry($class));
            $classes = self::getSubClasses($class, $includeSubclasses, $classes);

            $classes = array_unique($classes);
            $classes = self::excludeDataObjectIDx($classes);

            if ($dataOnly) {
                foreach ($classes as $i => $schemaClass) {
                    if (!DataObject::getSchema()->classHasTable($schemaClass)) {
                        unset($classes[$i]);
                    }
                }
            }

            self::$hierarchy[$cacheKey] = $classes;

            return $classes;
        }

        return self::$hierarchy[$cacheKey];
    }

    /**
     * @param $class
     * @param $includeSubclasses
     * @param array $classes
     * @return array
     * @throws ReflectionException
     */
    protected static function getSubClasses($class, $includeSubclasses, array $classes): array
    {
        if ($includeSubclasses) {
            $subClasses = ClassInfo::subclassesFor($class);
            $classes = array_merge($classes, array_values($subClasses));
        }

        return $classes;
    }

    /**
     * @param array $classes
     * @return array
     */
    protected static function excludeDataObjectIDx(array $classes): array
    {
        // Remove all classes below DataObject from the list
        $idx = array_search(DataObject::class, $classes, true);
        if ($idx !== false) {
            array_splice($classes, 0, $idx + 1);
        }

        return $classes;
    }

    /**
     * @param $lookup
     * @param DataObjectSchema $schema
     * @param $className
     * @param array $options
     * @return array
     * @throws Exception
     */
    protected function getRelationData($lookup, DataObjectSchema $schema, $className, array $options): array
    {
        $class = null;
        $relationType = false;
        if ($hasOne = $schema->hasOneComponent($className, $lookup)) {
            $class = $hasOne;
            $key = $lookup . 'ID';
            $relationType = 'has_one';
        } elseif ($hasMany = $schema->hasManyComponent($className, $lookup)) {
            $class = $hasMany;
            $options['multi_valued'] = true;
            $key = $schema->getRemoteJoinField($className, $lookup);
            $relationType = 'has_many';
        } elseif ($key = $schema->manyManyComponent($className, $lookup)) {
            $class = $key['childClass'];
            $options['multi_valued'] = true;
            $relationType = 'many_many';
        }

        return [$class, $key, $relationType, $options];
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
     * @param string $lookup
     * @param string $type
     * @param string $dataClass
     * @param string $class
     * @param string|array $key
     * @return array
     */
    public function getLookupChain($options, $lookup, $type, $dataClass, $class, $key): array
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
     * @throws ReflectionException
     */
    protected function getFieldOptions($field, array $sources, $fullfield, array $found): array
    {
        foreach ($sources as $class => $fieldOptions) {
            if (is_int($class)) {
                $class = $fieldOptions;
                $fieldOptions = [];
            }
            if (!empty($this->found[$class . '_' . $field])) {
                return $this->found[$class . '_' . $field];
            }
            $class = $this->getSourceName($class);
            $dataclasses = self::getHierarchy($class);

            while (count($dataclasses)) {
                $dataclass = array_shift($dataclasses);

                $fields = DataObject::getSchema()->databaseFields($class);

                [$type, $fieldOptions] = $this->getCallType($field, $fields, $fieldOptions, $dataclass);

                if ($type) {
                    // Don't search through child classes of a class we matched on. TODO: Should we?
                    $dataclasses = array_diff($dataclasses, array_values(ClassInfo::subclassesFor($dataclass)));
                    // Trim arguments off the type string
                    if (preg_match('/^(\w+)\(/', $type, $match)) {
                        $type = $match[1];
                    }
                    // Get the origin
                    $origin = $fieldOptions['origin'] ?? $dataclass;

                    [$fieldOptions, $found] = $this->getFoundOriginData($field, $fullfield, $fieldOptions, $origin,
                        $dataclass, $type, $found);
                }
            }
            $this->found[$class . '_' . $fullfield] = $found;
        }


        return $found;
    }

    /**
     * @param $field
     * @param $fullfield
     * @param array $dataclasses
     * @param string $class
     * @param array $fieldOptions
     * @return array|null
     * @throws ReflectionException
     */
    protected function buildFieldForClass($field, $fullfield, $dataclasses, $class, $fieldOptions): ?array
    {
        $found = null;
        while (count($dataclasses)) {
            $dataclass = array_shift($dataclasses);

            $fields = DataObject::getSchema()->databaseFields($class);

            [$type, $fieldOptions] = $this->getCallType($field, $fields, $fieldOptions, $dataclass);

            if ($type) {
                // Don't search through child classes of a class we matched on. TODO: Should we?
                $dataclasses = array_diff($dataclasses, array_values(ClassInfo::subclassesFor($dataclass)));
                // Trim arguments off the type string
                if (preg_match('/^(\w+)\(/', $type, $match)) {
                    $type = $match[1];
                }
                // Get the origin
                $origin = $fieldOptions['origin'] ?? $dataclass;

                [$fieldOptions, $found] = $this->getFoundOriginData($field, $fullfield, $fieldOptions, $origin,
                    $dataclass, $type, $found);
            }
        }
        $this->found[$class . '_' . $fullfield] = $found;

        return $found;
    }

    /**
     * @param $field
     * @param array $fields
     * @param array $fieldoptions
     * @param $dataclass
     * @return array
     */
    protected function getCallType($field, array $fields, array $fieldoptions, $dataclass): array
    {
        $type = null;

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

        return [$type, $fieldoptions];
    }

    /**
     * @return BaseIndex
     */
    public function getIndex(): BaseIndex
    {
        return $this->index;
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
     * @return array
     */
    public function getFound(): array
    {
        return $this->found;
    }

    /**
     * @param $field
     * @param $fullfield
     * @param $fieldOptions
     * @param $origin
     * @param $dataclass
     * @param $type
     * @param $found
     * @return array
     */
    protected function getFoundOriginData($field, $fullfield, $fieldOptions, $origin, $dataclass, $type, $found): array
    {
        $found["{$origin}_{$fullfield}"] = [
            'name'         => "{$origin}_{$fullfield}",
            'field'        => $field,
            'fullfield'    => $fullfield,
            'origin'       => $origin,
            'class'        => $dataclass,
            'lookup_chain' => $fieldOptions['lookup_chain'],
            'type'         => $type,
            'multi_valued' => isset($fieldOptions['multi_valued']) ? true : false,
        ];

        return [$fieldOptions, $found];
    }
}
