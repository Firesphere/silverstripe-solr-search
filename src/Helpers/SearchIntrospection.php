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
        $buildSources = [];

        $schemaHelper = DataObject::getSchema();
        foreach ($sources as $source) {
            $buildSources[$source]['base'] = $schemaHelper->baseDataClass($source);
        }

        $found = [];

        if (strpos($field, '.') !== false) {
            $lookups = explode('.', $field);
            $field = array_pop($lookups);

            foreach ($lookups as $lookup) {
                $next = [];

                // @todo remove repetition
                foreach ($buildSources as $source => $baseOptions) {
                    $next = $this->getRelationIntrospection($source, $lookup, $next);
                }

                $buildSources = $next;
            }
        }

        $found = $this->getFieldOptions($field, $buildSources, $fullfield, $found);

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

            $class = $this->getRelationData($lookup, $schema, $className, $options);

            if (is_string($class) && $class) {
                if (!isset($options['origin'])) {
                    $options['origin'] = $dataClass;
                }

                // we add suffix here to prevent the relation to be overwritten by other instances
                // all sources lookups must clean the source name before reading it via getSourceName()
                $next[$class . '|xkcd|' . $dataClass] = $options;
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
    private function getSourceName($source)
    {
        $explodedSource = explode('|xkcd|', $source);

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
    private static function getSubClasses($class, $includeSubclasses, array $classes): array
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
    private static function excludeDataObjectIDx(array $classes): array
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
     * @return array|null
     * @throws Exception
     */
    protected function getRelationData($lookup, DataObjectSchema $schema, $className, array &$options)
    {
        $class = null;
        if ($hasOne = $schema->hasOneComponent($className, $lookup)) {
            $class = $hasOne;
        } elseif ($hasMany = $schema->hasManyComponent($className, $lookup)) {
            $class = $hasMany;
            $options['multi_valued'] = true;
        } elseif ($key = $schema->manyManyComponent($className, $lookup)) {
            $class = $key['childClass'];
            $options['multi_valued'] = true;
        }

        return $class;
    }

    /**
     * @param $field
     * @param array $sources
     * @param $fullfield
     * @param array $found
     * @return array
     * @throws ReflectionException
     */
    public function getFieldOptions($field, array $sources, $fullfield, array $found): array
    {
        foreach ($sources as $class => $fieldOptions) {
            if (is_int($class)) {
                $class = $fieldOptions;
            }
            if (!empty($this->found[$class . '_' . $field])) {
                return $this->found[$class . '_' . $field];
            }
            $class = $this->getSourceName($class);
            $dataclasses = self::getHierarchy($class);

            $fields = DataObject::getSchema()->databaseFields($class);
            while ($dataclass = array_shift($dataclasses)) {
                $type = $this->getType($fields, $field, $dataclass);

                if ($type) {
                    // Don't search through child classes of a class we matched on. TODO: Should we?
                    $dataclasses = array_diff($dataclasses, array_values(ClassInfo::subclassesFor($dataclass)));
                    // Trim arguments off the type string
                    if (preg_match('/^(\w+)\(/', $type, $match)) {
                        $type = $match[1];
                    }

                    $found = $this->getFoundOriginData(
                        $field,
                        $fullfield,
                        $fieldOptions,
                        $dataclass,
                        $type,
                        $found
                    );
                }
            }
            $this->found[$class . '_' . $fullfield] = $found;
        }


        return $found;
    }

    /**
     * @param array $fields
     * @param string $field
     * @param string $dataclass
     * @return string
     */
    public function getType($fields, $field, $dataclass)
    {
        if (!empty($fields[$field])) {
            return $fields[$field];
        }

        $singleton = singleton($dataclass);
        $type = $singleton->castingClass($field);
        if (!$type) {
            $type = 'String';
        }

        return $type;
    }

    /**
     * @param string $field
     * @param string $fullfield
     * @param array $fieldOptions
     * @param string $dataclass
     * @param string $type
     * @param array $found
     * @return array
     */
    private function getFoundOriginData($field, $fullfield, $fieldOptions, $dataclass, $type, $found): array
    {
        // Get the origin
        $origin = $fieldOptions['origin'] ?? $dataclass;

        $found["{$origin}_{$fullfield}"] = [
            'name'         => "{$origin}_{$fullfield}",
            'field'        => $field,
            'fullfield'    => $fullfield,
            'origin'       => $origin,
            'class'        => $dataclass,
            'type'         => $type,
            'multi_valued' => isset($fieldOptions['multi_valued']) ? true : false,
        ];

        return $found;
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
}
