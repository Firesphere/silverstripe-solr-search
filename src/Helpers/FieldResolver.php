<?php

namespace Firesphere\SolrSearch\Helpers;

use Exception;
use Firesphere\SolrSearch\Traits\GetSetSearchResolverTrait;
use ReflectionException;
use SilverStripe\Core\ClassInfo;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectSchema;

/**
 * Class FieldResolver
 * Some additional introspection tools that are used often by the fulltext search code
 *
 * @package Firesphere\SolrSearch\Helpers
 */
class FieldResolver
{
    use GetSetSearchResolverTrait;
    /**
     * @var array Class Ancestry
     */
    protected static $ancestry = [];
    /**
     * @var array Class Hierarchy, could be replaced with Ancestry
     */
    protected static $hierarchy = [];

    /**
     * Check if class is subclass of (a) the class in $instanceOf, or (b) any of the classes in the array $instanceOf
     *
     * @param string $class Name of the class to test
     * @param array|string $instanceOf Class ancestry it should be in
     * @return bool
     * @todo remove in favour of DataObjectSchema
     * @static
     */
    public static function isSubclassOf($class, $instanceOf): bool
    {
        $ancestry = self::$ancestry[$class] ?? self::$ancestry[$class] = ClassInfo::ancestry($class);

        return is_array($instanceOf) ?
            (bool)array_intersect($instanceOf, $ancestry) :
            array_key_exists($instanceOf, $ancestry);
    }

    /**
     * Resolve a field ancestry
     *
     * @param $field
     * @return array
     * @throws Exception
     *
     */
    public function resolveField($field)
    {
        $fullfield = str_replace('.', '_', $field);

        $buildSources = $this->getBuildSources();

        $found = [];

        if (strpos($field, '.') !== false) {
            $lookups = explode('.', $field);
            $field = array_pop($lookups);

            foreach ($lookups as $lookup) {
                $buildSources = $this->getNext($buildSources, $lookup);
            }
        }

        $found = $this->getFieldOptions($field, $buildSources, $fullfield, $found);

        return $found;
    }

    /**
     * Get the sources to build in to a Solr field
     *
     * @return array
     */
    protected function getBuildSources(): array
    {
        $sources = $this->index->getClasses();
        $buildSources = [];

        $schemaHelper = DataObject::getSchema();
        foreach ($sources as $source) {
            $buildSources[$source]['base'] = $schemaHelper->baseDataClass($source);
        }

        return $buildSources;
    }

    /**
     * Get the next lookup item from the buildSources
     *
     * @param array $buildSources
     * @param $lookup
     * @return array
     * @throws Exception
     */
    protected function getNext(array $buildSources, $lookup): array
    {
        $next = [];

        // @todo remove repetition
        foreach ($buildSources as $source => $baseOptions) {
            $next = $this->resolveRelation($source, $lookup, $next, $baseOptions);
        }

        $buildSources = $next;

        return $buildSources;
    }

    /**
     * Resolve relations if possible
     *
     * @param string $source
     * @param $lookup
     * @param array $next
     * @param array $options
     * @return array
     * @throws ReflectionException
     * @throws Exception
     */
    protected function resolveRelation($source, $lookup, array $next, array &$options): array
    {
        $source = $this->getSourceName($source);

        foreach (self::getHierarchy($source) as $dataClass) {
            $schema = DataObject::getSchema();
            $options['multi_valued'] = false;

            $class = $this->getRelationData($lookup, $schema, $dataClass, $options);

            list($options, $next) = $this->handleRelationData($source, $next, $options, $class, $dataClass);
        }

        return $next;
    }

    /**
     * This is used to clean the source name from suffix
     * suffixes are needed to support multiple relations with the same name on different page types
     *
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
            $classes = self::getHierarchyClasses($class, $includeSubclasses);

            if ($dataOnly) {
                $classes = array_filter($classes, static function ($class) {
                    return DataObject::getSchema()->classHasTable($class);
                });
            }

            self::$hierarchy[$cacheKey] = array_values($classes);

            return array_values($classes);
        }

        return self::$hierarchy[$cacheKey];
    }

    /**
     * Get the hierarchy for a class
     *
     * @param $class
     * @param $includeSubclasses
     * @return array
     * @throws ReflectionException
     * @todo clean this up to be more compatible with PHP features
     */
    protected static function getHierarchyClasses($class, $includeSubclasses): array
    {
        $classes = array_values(ClassInfo::ancestry($class));
        $classes = self::getSubClasses($class, $includeSubclasses, $classes);

        $classes = array_unique($classes);
        $classes = self::excludeDataObjectIDx($classes);

        return $classes;
    }

    /**
     * Get the subclasses for the given class
     * Should be replaced with PHP native methods
     *
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
     * Objects to exclude from the index
     *
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
     * Relational data
     *
     * @param $lookup
     * @param DataObjectSchema $schema
     * @param $className
     * @param array $options
     * @return string|array|null
     * @throws Exception
     */
    protected function getRelationData($lookup, DataObjectSchema $schema, $className, array &$options)
    {
        if ($hasOne = $schema->hasOneComponent($className, $lookup)) {
            return $hasOne;
        }
        $options['multi_valued'] = true;
        if ($hasMany = $schema->hasManyComponent($className, $lookup)) {
            return $hasMany;
        }
        if ($key = $schema->manyManyComponent($className, $lookup)) {
            return $key['childClass'];
        }

        return null;
    }

    /**
     * Figure out the relational data for the given source etc.
     *
     * @param string $source
     * @param array $next
     * @param array $options
     * @param array|string|null $class
     * @param string|null $dataClass
     * @return array
     */
    protected function handleRelationData($source, array $next, array &$options, $class, $dataClass)
    {
        if (is_string($class) && $class) {
            if (!isset($options['origin'])) {
                $options['origin'] = $source;
            }

            // we add suffix here to prevent the relation to be overwritten by other instances
            // all sources lookups must clean the source name before reading it via getSourceName()
            $next[$class . '|xkcd|' . $dataClass] = $options;
        }

        return array($options, $next);
    }

    /**
     * Create field options for the given index field
     *
     * @param $field
     * @param array $sources
     * @param string $fullfield
     * @param array $found
     * @return array
     * @throws ReflectionException
     */
    protected function getFieldOptions($field, array $sources, $fullfield, array $found): array
    {
        foreach ($sources as $class => $fieldOptions) {
            $class = $this->getSourceName($class);
            $dataclasses = self::getHierarchy($class);

            $fields = DataObject::getSchema()->databaseFields($class);
            while ($dataclass = array_shift($dataclasses)) {
                $type = $this->getType($fields, $field, $dataclass);

                if ($type) {
                    // Don't search through child classes of a class we matched on.
                    $dataclasses = array_diff($dataclasses, array_values(ClassInfo::subclassesFor($dataclass)));
                    // Trim arguments off the type string
                    if (preg_match('/^(\w+)\(/', $type, $match)) {
                        $type = $match[1];
                    }

                    $found = $this->getFoundOriginData($field, $fullfield, $fieldOptions, $dataclass, $type, $found);
                }
            }
        }

        return $found;
    }

    /**
     * Get the type of this field
     *
     * @param array $fields
     * @param string $field
     * @param string $dataclass
     * @return string
     */
    protected function getType($fields, $field, $dataclass): string
    {
        if (!empty($fields[$field])) {
            return $fields[$field];
        }

        /** @var DataObject $singleton */
        $singleton = singleton($dataclass);

        $type = $singleton->castingClass($field);

        if (!$type) {
            // @todo should this be null?
            $type = 'String';
        }

        return $type;
    }

    /**
     * FoundOriginData is a helper to make sure the options are properly set.
     *
     * @param string $field
     * @param string $fullField
     * @param array $fieldOptions
     * @param string $dataclass
     * @param string $type
     * @param array $found
     * @return array
     */
    private function getFoundOriginData(
        $field,
        $fullField,
        $fieldOptions,
        $dataclass,
        $type,
        $found
    ): array
    {
        // Get the origin
        $origin = $fieldOptions['origin'] ?? $dataclass;

        $found["{$origin}_{$fullField}"] = [
            'name'         => "{$origin}_{$fullField}",
            'field'        => $field,
            'fullfield'    => $fullField,
            'origin'       => $origin,
            'class'        => $dataclass,
            'type'         => $type,
            'multi_valued' => isset($fieldOptions['multi_valued']) ? true : false,
        ];

        return $found;
    }

    /**
     * @param array $next
     * @param array|string $class
     * @param array $options
     * @param string $dataClass
     * @return array
     */
    protected function getNextOption(array $next, $class, array $options, $dataClass): array
    {
        if (is_string($class) && $class) {
            if (!isset($options['origin'])) {
                $options['origin'] = $dataClass;
            }

            // we add suffix here to prevent the relation to be overwritten by other instances
            // all sources lookups must clean the source name before reading it via getSourceName()
            $next[$class . '|xkcd|' . $dataClass] = $options;
        }

        return [$options, $next];
    }
}
