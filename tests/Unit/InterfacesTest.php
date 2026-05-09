<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use ReflectionClass;
use Switon\Orm\EntityFillerInterface;
use Switon\Orm\EntityHydratorInterface;
use Switon\Orm\EntityManagerInterface;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\NamingStrategyInterface;
use Switon\Orm\QueryBuilderInterface;
use Switon\Orm\RelationManagerInterface;
use Switon\Orm\RepositoryInterface;
use Switon\Orm\ShardingInterface;
use Switon\Orm\Tests\TestCase;
use function array_map;

class InterfacesTest extends TestCase
{
    public function testEntityFillerInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(EntityFillerInterface::class, true));

        $reflection = new ReflectionClass(EntityFillerInterface::class);
        $methods = array_map(fn($m) => $m->getName(), $reflection->getMethods());

        $this->assertContains('onCreating', $methods);
        $this->assertContains('onUpdating', $methods);
    }

    public function testEntityHydratorInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(EntityHydratorInterface::class, true));

        $reflection = new ReflectionClass(EntityHydratorInterface::class);
        $methods = array_map(fn($m) => $m->getName(), $reflection->getMethods());

        $this->assertContains('hydrate', $methods);
        $this->assertContains('hydrateInto', $methods);
        $this->assertContains('dehydrate', $methods);
    }

    public function testEntityMetadataInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(EntityMetadataInterface::class, true));

        $reflection = new ReflectionClass(EntityMetadataInterface::class);
        $methods = array_map(fn($m) => $m->getName(), $reflection->getMethods());

        $this->assertContains('getPrimaryKey', $methods);
        $this->assertContains('getTable', $methods);
        $this->assertContains('getConnection', $methods);
        $this->assertContains('getColumnMap', $methods);
    }

    public function testEntityManagerInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(EntityManagerInterface::class, true));

        $reflection = new ReflectionClass(EntityManagerInterface::class);
        $methods = array_map(fn($m) => $m->getName(), $reflection->getMethods());

        $this->assertContains('create', $methods);
        $this->assertContains('update', $methods);
        $this->assertContains('delete', $methods);
        $this->assertContains('put', $methods);
        $this->assertContains('query', $methods);
    }

    public function testNamingStrategyInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(NamingStrategyInterface::class, true));

        $reflection = new ReflectionClass(NamingStrategyInterface::class);
        $methods = array_map(fn($m) => $m->getName(), $reflection->getMethods());

        $this->assertContains('classToTableName', $methods);
        $this->assertContains('propertyToColumnName', $methods);
    }

    public function testQueryBuilderInterfaceExists(): void
    {

        $reflection = new ReflectionClass(QueryBuilderInterface::class);
        $methods = array_map(fn($m) => $m->getName(), $reflection->getMethods());

        $this->assertContains('create', $methods);
    }

    public function testRelationManagerInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(RelationManagerInterface::class, true));

        $reflection = new ReflectionClass(RelationManagerInterface::class);
        $methods = array_map(fn($m) => $m->getName(), $reflection->getMethods());

        $this->assertContains('get', $methods);
        $this->assertContains('earlyLoad', $methods);
        $this->assertContains('lazyLoad', $methods);
    }

    public function testRepositoryInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(RepositoryInterface::class, true));

        $reflection = new ReflectionClass(RepositoryInterface::class);
        $methods = array_map(fn($m) => $m->getName(), $reflection->getMethods());

        $this->assertContains('find', $methods);
        $this->assertContains('all', $methods);
        $this->assertContains('get', $methods);
        $this->assertContains('create', $methods);
        $this->assertContains('update', $methods);
        $this->assertContains('delete', $methods);
    }

    public function testShardingInterfaceExists(): void
    {

        $reflection = new ReflectionClass(ShardingInterface::class);
        $methods = array_map(fn($m) => $m->getName(), $reflection->getMethods());

        $this->assertContains('getAnyShard', $methods);
        $this->assertContains('getUniqueShard', $methods);
        $this->assertContains('getMultipleShards', $methods);
        $this->assertContains('getAllShards', $methods);
    }
}

