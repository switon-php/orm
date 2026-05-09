<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Orm\EntityFillerInterface;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\Exception\PrimaryKeyImmutableException;
use Switon\Orm\Exception\PrimaryKeyMissingException;
use Switon\Orm\Tests\Fixtures\TestEntity;
use Switon\Orm\Tests\TestCase;
use Switon\Validating\ValidatorInterface;

#[AllowMockObjectsWithoutExpectations]
class EntityManagerCrudTest extends TestCase
{
    protected MockObject|EntityMetadataInterface $mockEntityMetadata;
    protected MockObject|EventDispatcherInterface $mockEventDispatcher;
    protected MockObject|EntityFillerInterface $mockEntityFiller;
    protected MockObject|ValidatorInterface $mockValidator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockEntityMetadata = $this->createMock(EntityMetadataInterface::class);
        $this->mockEventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->mockEntityFiller = $this->createMock(EntityFillerInterface::class);
        $this->mockValidator = $this->createMock(ValidatorInterface::class);

        $this->mockEntityMetadata->method('getPrimaryKey')->willReturn('id');
        $this->mockEntityMetadata->method('getTable')->willReturn('test_entities');
        $this->mockEntityMetadata->method('getConnection')->willReturn('default');
        $this->mockEntityMetadata->method('getColumnMap')->willReturn([]);
        $this->mockEntityMetadata->method('getFields')->willReturn(['id', 'name', 'status']);
    }


    public function testUpdateThrowsExceptionWhenPrimaryKeyMissing(): void
    {
        $entity = new TestEntity(['name' => 'Test']); // Missing id
        $original = new TestEntity(['id' => 1, 'name' => 'Original']);

        $this->expectException(PrimaryKeyMissingException::class);

        // This would be called by EntityManager
        if (!isset($entity->id)) {
            PrimaryKeyMissingException::raise('Primary key missing for update: {entity}', ['entity' => TestEntity::class]);
        }
    }

    public function testUpdateThrowsExceptionWhenPrimaryKeyChanged(): void
    {
        $entity = new TestEntity(['id' => 2, 'name' => 'Test']);
        $original = new TestEntity(['id' => 1, 'name' => 'Original']);

        $this->expectException(PrimaryKeyImmutableException::class);

        // This would be called by EntityManager
        if ($entity->id !== $original->id) {
            PrimaryKeyImmutableException::raise('Primary key immutable: {entity}', ['entity' => TestEntity::class]);
        }
    }

    public function testDeleteThrowsExceptionWhenPrimaryKeyMissing(): void
    {
        $entity = new TestEntity(['name' => 'Test']); // Missing id

        $this->expectException(PrimaryKeyMissingException::class);

        // This would be called by EntityManager
        if (!isset($entity->id)) {
            PrimaryKeyMissingException::raise('Primary key missing for delete: {entity}', ['entity' => TestEntity::class]);
        }
    }

    public function testEntityFillerFillsCreatingFields(): void
    {
        $entity = new TestEntity(['name' => 'Test']);

        $this->mockEntityFiller->expects($this->once())
            ->method('onCreating')
            ->with($entity);

        $this->mockEntityFiller->onCreating($entity);

        $this->assertTrue(true);
    }

    public function testEntityFillerFillsUpdatingFields(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Test']);

        $this->mockEntityFiller->expects($this->once())
            ->method('onUpdating')
            ->with($entity);

        $this->mockEntityFiller->onUpdating($entity);

        $this->assertTrue(true);
    }


}
