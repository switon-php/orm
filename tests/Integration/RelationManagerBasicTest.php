<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Integration;

use ReflectionClass;
use Switon\Orm\RelationManager;
use Switon\Orm\RelationManagerInterface;
use Switon\Orm\Tests\TestCase;
use function array_map;
use function class_exists;
use function interface_exists;
use function is_subclass_of;

class RelationManagerBasicTest extends TestCase
{
    public function testRelationManagerClassExistsAndImplementsInterface(): void
    {
        $this->assertTrue(class_exists(RelationManager::class, true));
        $this->assertTrue(interface_exists(RelationManagerInterface::class, true));
        $this->assertTrue(is_subclass_of(RelationManager::class, RelationManagerInterface::class));
    }

    public function testRelationManagerInterfaceHasRequiredMethods(): void
    {
        $reflection = new ReflectionClass(RelationManagerInterface::class);
        $methods = array_map(fn($m) => $m->getName(), $reflection->getMethods());

        $this->assertContains('get', $methods);
        $this->assertContains('earlyLoad', $methods);
        $this->assertContains('lazyLoad', $methods);
    }

    public function testRelationManagerClassHasRequiredMethods(): void
    {
        $reflection = new ReflectionClass(RelationManager::class);
        $methods = array_map(fn($m) => $m->getName(), $reflection->getMethods());

        $this->assertContains('get', $methods);
        $this->assertContains('earlyLoad', $methods);
        $this->assertContains('lazyLoad', $methods);
    }
}


