<?php

namespace Firesphere\PartialUserforms\Tests;

use SilverStripe\Dev\TestOnly;

/**
 * A simple helper class for test calls to private and protected methods
 *
 * @link https://jtreminio.com/blog/unit-testing-tutorial-part-iii-testing-protected-private-methods-coverage-reports-and-crap/
 */
class TestHelper implements TestOnly
{
    /**
     * Call protected/private method of a class
     *
     * @param Object $object
     * @param string $methodName
     * @param array $parameters
     * @return mixed
     * @throws \ReflectionException
     */
    public static function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
