<?php


namespace Firesphere\SolrSearch\States;

use Firesphere\SolrSearch\Compat\FluentExtension;
use Firesphere\SolrSearch\Helpers\FieldResolver;
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
 * {@see FluentExtension::onBeforeInit()}
 *
 * States, options, etc. are simplified for a more streamlined approach
 *
 * @package Firesphere\SolrSearch\States
 */
abstract class SiteState
{
    use Configurable;
    use Injectable;
    /**
     * States that can be applied
     *
     * @var array
     */
    public static $states = [
        'default',
    ];
    /**
     * Variants of SiteState that can be activated
     *
     * @var array
     */
    public static $variants = [];
    /**
     * @var bool Is this State enabled
     */
    public $enabled = true;

    /**
     * Get available states
     *
     * @return array
     */
    public static function getStates(): array
    {
        return self::$states;
    }

    /**
     * Set states
     *
     * @param array $states
     */
    public static function setStates(array $states): void
    {
        self::$states = $states;
    }

    /**
     * Add a state
     *
     * @param $state
     */
    public static function addState($state): void
    {
        self::$states[] = $state;
    }

    /**
     * Add multiple states
     *
     * @param array $states
     */
    public static function addStates(array $states): void
    {
        self::$states = array_merge(self::$states, $states);
    }

    /**
     * Does this class, it's parent (or optionally one of it's children) have the passed extension attached?
     *
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
        $state = [];
        foreach (self::variants() as $variant => $instance) {
            $state[$variant] = $instance->currentState();
        }

        return $state;
    }

    /**
     * Returns an array of variants.
     *
     * With no arguments, returns all variants
     *
     * With a classname as the first argument, returns the variants that apply to that class
     * (optionally including subclasses)
     *
     * @static
     * @param bool $force
     * @return array - An array of (string)$variantClassName => (Object)$variantInstance pairs
     * @throws ReflectionException
     */
    public static function variants($force = false): ?array
    {
        // Build up and cache a list of all search variants (subclasses of SearchVariant)
        if (!empty(self::$variants) || $force) {
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
     * @param $variantclass
     * @return bool
     * @throws ReflectionException
     */
    public static function isApplicable($variantclass): bool
    {
        $ref = new ReflectionClass($variantclass);
        if ($ref->isInstantiable()) {
            /** @var SiteState $variant */
            $variant = singleton($variantclass);
            if ($variant->appliesToEnvironment() && $variant->isEnabled()) {
                self::$variants[$variantclass] = $variant;
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
         * @var static $instance
         */
        foreach (self::variants() as $variant => $instance) {
            $instance->activateState($state);
        }
    }
}
