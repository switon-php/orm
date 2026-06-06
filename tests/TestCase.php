<?php

declare(strict_types=1);

namespace Switon\Orm\Tests;

use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Orm\EntityHydratorInterface;
use Switon\Testing\TestCase as BaseTestCase;
use ReflectionClass;

/**
 * Base test case for ORM tests.
 *
 * Provides common functionality for all ORM tests, including Container initialization.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Configure container dependencies.
     *
     * Subclasses can override this method to register additional dependencies
     * or replace default implementations before autowiring.
     */
    protected function setUpContainer(): void
    {
        parent::setUpContainer();

        // Register IdentityInterface stub for EntityFiller dependency
        // Use createStub (not createMock) to avoid PHPUnit notices about unconfigured expectations
        // EntityFiller is used by EntityManager and other ORM components
        $stubIdentity = $this->createStub(\Switon\Principal\IdentityInterface::class);
        $stubIdentity->method('isGuest')->willReturn(true);
        $stubIdentity->method('getId')->willReturn(0);
        $stubIdentity->method('getName')->willReturn('');
        $stubIdentity->method('getRoles')->willReturn([]);
        $this->container->set(\Switon\Principal\IdentityInterface::class, $stubIdentity);
    }

    /**
     * Create a relation instance with constructor parameters and autowired dependencies.
     *
     * This method creates the relation using the container's make() method, which:
     * 1. Passes constructor parameters
     * 2. Automatically injects #[Autowired] dependencies from the container
     *
     * Usage:
     * ```php
     * // First, replace dependencies in container
     * $this->container->replace(EntityMetadataInterface::class, $mockMetadata);
     * $this->container->replace(EventDispatcherInterface::class, $mockDispatcher);
     *
     * // Then create the relation with constructor parameters
     * $relation = $this->createRelation(HasManyRelation::class, [
     *     'relatedEntity' => TestPost::class,
     *     'foreignKey' => 'user_id',
     *     'orderBy' => ['created_at' => SORT_DESC],
     * ]);
     * ```
     *
     * @template T of object
     *
     * @param class-string<T> $relationClass The relation class to instantiate
     * @param array $parameters Constructor parameters
     *
     * @return T The created relation instance with dependencies autowired
     */
    protected function createRelation(string $relationClass, array $parameters = []): object
    {
        return $this->make($relationClass, $parameters);
    }

    /**
     * Inject dependencies into a relation instance for testing.
     *
     * Simplifies unit testing by allowing mock injection without verbose reflection code.
     *
     * @param object $relation The relation instance to configure
     * @param array $dependencies Associative array of property => value pairs
     *
     * @return object The configured relation instance
     */
    protected function injectRelationDependencies(object $relation, array $dependencies): object
    {
        if (!isset($dependencies['entityHydrator'])) {
            $dependencies['entityHydrator'] = $this->container->get(EntityHydratorInterface::class);
        }

        $reflection = new ReflectionClass($relation);

        if (!isset($dependencies['eventDispatcher']) && $reflection->hasProperty('eventDispatcher')) {
            $dependencies['eventDispatcher'] = $this->container->get(EventDispatcherInterface::class);
        }

        foreach ($dependencies as $property => $value) {
            if ($reflection->hasProperty($property)) {
                $prop = $reflection->getProperty($property);
                $prop->setValue($relation, $value);
            }
        }

        return $relation;
    }
}
