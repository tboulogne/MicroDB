<?php

namespace Finesse\MicroDB\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case class for other rests
 *
 * @author Surgie
 */
class TestCase extends BaseTestCase
{
    /**
     * {@inheritDoc}
     */
    protected function tearDown()
    {
        parent::tearDown();
        \Mockery::close();
    }

    /**
     * Call protected/private method of a class.
     *
     * @param object $object Instantiated object that we will run method on
     * @param string $methodName Method name to call
     * @param array $parameters Array of parameters to pass into method
     * @return mixed Method return
     */
    protected function invokeMethod(&$object, string $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
