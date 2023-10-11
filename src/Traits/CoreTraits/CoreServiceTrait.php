<?php
/**
 * Trait CoreServiceTrait|Firesphere\SolrSearch\Traits\CoreServiceTraits to have simple methods that don't really
 * need to be in core.
 *
 * @package Firesphere\Solr\Search
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\SolrSearch\Traits;

use Firesphere\SearchBackend\Helpers\FieldResolver;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use ReflectionException;
use SilverStripe\Core\Injector\Injector;
use Solarium\Client;
use Solarium\QueryType\Server\CoreAdmin\Query\Query;

/**
 * Trait CoreServiceTrait to have simple methods that don't really need to be in core.
 *
 * Trait to support basic settings for the Solr Core Service
 *
 * @package Firesphere\Solr\Search
 */
trait CoreServiceTrait
{
    /**
     * Add debugging information
     *
     * @var bool
     */
    protected $debug = false;
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
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Set the debug mode
     *
     * @param bool $debug
     * @return self
     */
    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;

        return $this;
    }

    /**
     * Is the given class a valid class to index
     * Does not discriminate against the indexes. All indexes are worth the same
     *
     * @param string $class
     * @return bool
     * @throws ReflectionException
     * @throws InvalidArgumentException
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
     * @throws InvalidArgumentException
     */
    public function getValidClasses(): array
    {
        /** @var CacheInterface $cache */
        $cache = Injector::inst()->get(CacheInterface::class . '.SolrCache');

        if ($cache->has('ValidClasses')) {
            return $cache->get('ValidClasses');
        }

        $indexes = $this->getValidIndexes();
        $classes = [];
        foreach ($indexes as $index) {
            $classes = $this->getClassesInHierarchy($index, $classes);
        }

        $cache->set('ValidClasses', array_unique($classes));

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
     * @param string $index Index to check classes for
     * @param array $classes Classes to get hierarchy for
     * @return array
     * @throws ReflectionException
     */
    protected function getClassesInHierarchy($index, array $classes): array
    {
        $indexClasses = singleton($index)->getClasses();
        foreach ($indexClasses as $class) {
            $classes = array_merge($classes, FieldResolver::getHierarchy($class));
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
     * Get the admin query
     *
     * @return Query
     */
    public function getAdmin(): Query
    {
        return $this->admin;
    }

    /**
     * Set a (custom) admin query object
     *
     * @param Query $admin
     */
    public function setAdmin(Query $admin): void
    {
        $this->admin = $admin;
    }
}
