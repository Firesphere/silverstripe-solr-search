<?php


namespace Firesphere\SolrSearch\States;

use Firesphere\SolrSearch\Helpers\FieldResolver;
use Firesphere\SolrSearch\Interfaces\SiteStateInterface;
use Firesphere\SolrSearch\Queries\BaseQuery;
use ReflectionClass;
use ReflectionException;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;

/**
 * Class SiteState
 *
 * Determine and apply the state of the site currently. This is used at index-time to figure out what state to index.
 * An example of this is the FluentSearchVariant extension from Fluent.
 *
 * Fluent uses the old SearchVariant method, which is actually not that bad a concept. These "Variants", now called
 * "States" set the state of the site to a required setting for each available state.
 *
 * States SHOULD add their own states through an extension, otherwise it won't be called.
 * {@link FluentIndexExtension::onBeforeInit()}
 *
 * States, options, etc. are simplified for a more streamlined approach
 *
 * @package Firesphere\SolrSearch\States
 */
abstract class SiteState
{
    use Configurable;
    use Injectable;

    const DEFAULT_STATE = 'default';
    /**
     * States that can be applied
     *
     * @var array
     */
    public static $states = [
        self::DEFAULT_STATE,
    ];
    /**
     * Variants of SiteState that can be activated
     *
     * @var array
     */
    public static $variants = [];
    /**
     * @var array Default states
     */
    protected static $defaultStates = [];
    /**
     * @var bool Is this State enabled
     */
    public $enabled = true;
    /**
     * @var string current state
     */
    protected $state;

    /**
     * Get available states
     *
     * @static
     * @return array
     */
    public static function getStates(): array
    {
        return self::$states;
    }

    /**
     * Set states
     *
     * @static
     * @param array $states
     */
    public static function setStates(array $states): void
    {
        self::$states = $states;
    }

    /**
     * Add a state
     *
     * @static
     * @param $state
     */
    public static function addState($state): void
    {
        self::$states[] = $state;
    }

    /**
     * Add multiple states
     *
     * @static
     * @param array $states
     */
    public static function addStates(array $states): void
    {
        self::$states = array_merge(self::$states, $states);
    }

    /**
     * Does this class, it's parent (or optionally one of it's children) have the passed extension attached?
     *
     * @static
     * @param $class
     * @param $extension
     * @return bool
     * @throws ReflectionException
     */
    public static function hasExtension($class, $extension): bool
    {
        /** @var DataObject $relatedclass */
        foreach (FieldResolver::gethierarchy($class) as $relatedclass) {
            if ($relatedclass::has_extension($extension)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the current state of every variant
     *
     * @static
     * @return array
     * @throws ReflectionException
     */
    public static function currentStates(): array
    {
        foreach (self::variants() as $variant => $instance) {
            self::$defaultStates[$variant] = $instance->currentState();
        }

        return self::$defaultStates;
    }

    /**
     * Returns an array of variants.
     *
     * @static
     * @param bool $force Force updating the variants
     * @return array - An array of (string)$variantClassName => (Object)$variantInstance pairs
     * @throws ReflectionException
     */
    public static function variants($force = false): array
    {
        // Build up and cache a list of all search variants (subclasses of SearchVariant)
        if (empty(self::$variants) || $force) {
            $classes = ClassInfo::subclassesFor(static::class);

            foreach ($classes as $variantclass) {
                self::isApplicable($variantclass);
            }
        }

        return self::$variants;
    }

    /**
     * Is this extension applied and instantiable
     *
     * @static
     * @param $variantClass
     * @return bool
     * @throws ReflectionException
     */
    public static function isApplicable($variantClass): bool
    {
        $ref = new ReflectionClass($variantClass);
        if ($ref->isInstantiable()) {
            /** @var SiteState $variant */
            $variant = singleton($variantClass);
            if ($variant->appliesToEnvironment() && $variant->isEnabled()) {
                self::$variants[$variantClass] = $variant;

                return true;
            }
        }

        return false;
    }

    /**
     * Does this state apply to the current object/environment settings
     *
     * @return bool
     */
    public function appliesToEnvironment(): bool
    {
        return $this->enabled;
    }

    /**
     * Is this state enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Set the state to whatever is required. Most commonly true
     *
     * @param bool $enabled
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * Activate a site state for indexing
     *
     * @param $state
     * @throws ReflectionException
     */
    public static function withState($state): void
    {
        /**
         * @var string $variant
         * @var SiteStateInterface $instance
         */
        foreach (self::variants() as $variant => $instance) {
            if ($state === self::DEFAULT_STATE) {
                $instance->setDefaultState(self::$defaultStates[$variant]);
            } elseif ($instance->stateIsApplicable($state)) {
                $instance->activateState($state);
            }
        }
    }

    /**
     * Alter the query for each instance
     *
     * @param BaseQuery $query
     * @throws ReflectionException
     */
    public static function alterQuery(&$query): void
    {
        /**
         * @var string $variant
         * @var SiteStateInterface $instance
         */
        foreach (self::variants(true) as $variant => $instance) {
            $instance->updateQuery($query);
        }
    }

    /**
     * Get the states set as default
     *
     * @return array
     */
    public static function getDefaultStates(): array
    {
        return self::$defaultStates;
    }

    /**
     * Set the default states
     *
     * @param array $defaultStates
     */
    public static function setDefaultStates(array $defaultStates): void
    {
        self::$defaultStates = $defaultStates;
    }
}
