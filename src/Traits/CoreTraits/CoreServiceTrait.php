<?php


namespace Firesphere\SolrSearch\Traits;

use Firesphere\SolrSearch\Helpers\FieldResolver;
use ReflectionException;
use Solarium\Client;
use Solarium\QueryType\Server\CoreAdmin\Query\Query;

/**
 * Trait CoreServiceTrait to have simple methods that don't really need to be in core.
 *
 * Trait to support basic settings for the Solr Core Service
 *
 * @package Firesphere\SolrSearch\Traits
 */
trait CoreServiceTrait
{
    /**
     * Add debugging information
     *
     * @var bool
     */
    protected $inDebugMode = false;
    /**
     * @var Client The current client
     */
    protected $client;
    /**
     * @var Query A core admin object
     */
    protected $admin;

    /**
     * Check if we are in debug mode
     *
     * @return bool
     */
    public function isInDebugMode(): bool
    {
        return $this->inDebugMode;
    }

    /**
     * Set the debug mode
     *
     * @param bool $inDebugMode
     * @return self
     */
    public function setInDebugMode(bool $inDebugMode): self
    {
        $this->inDebugMode = $inDebugMode;

        return $this;
    }

    /**
     * Is the given class a valid class to index
     * Does not discriminate against the indexes. All indexes are worth the same
     *
     * @param string $class
     * @return bool
     * @throws ReflectionException
     */
    public function isValidClass($class): bool
    {
        $classes = $this->getValidClasses();

        return in_array($class, $classes, true);
    }

    /**
     * Get all classes from all indexes and return them.
     * Used to get all classes that are to be indexed on change
     * Note, only base classes are in this object. A publish recursive is required
     * when any change from a relation is published.
     *
     * @return array
     * @throws ReflectionException
     */
    public function getValidClasses(): array
    {
        $indexes = $this->getValidIndexes();
        $classes = [];
        foreach ($indexes as $index) {
            $classes = $this->getClassesInHierarchy($index, $classes);
        }

        return array_unique($classes);
    }

    /**
     * Ensure the getValidIndexes() method exists on all classes using this trait.
     *
     * @return mixed
     */
    abstract public function getValidIndexes();

    /**
     * Get the classes in hierarchy to see if it's valid
     *
     * @param $index
     * @param array $classes
     * @return array
     * @throws ReflectionException
     */
    protected function getClassesInHierarchy($index, array $classes): array
    {
        $indexClasses = singleton($index)->getClasses();
        foreach ($indexClasses as $class) {
            $classes = array_merge($classes, FieldResolver::getHierarchy($class, true));
        }

        return $classes;
    }

    /**
     * Get the client
     *
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Set the client
     *
     * @param Client $client
     * @return self
     */
    public function setClient($client): self
    {
        $this->client = $client;

        return $this;
    }

    /**
     * @return Query
     */
    public function getAdmin(): Query
    {
        return $this->admin;
    }

    /**
     * @param Query $admin
     */
    public function setAdmin(Query $admin): void
    {
        $this->admin = $admin;
    }
}
