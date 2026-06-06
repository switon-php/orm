<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use Switon\Core\Attribute\Autowired;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\Exception\PrimaryKeyNotFoundException;
use Switon\Orm\Exception\RepositoryNotFoundException;
use Switon\Orm\Tests\Fixtures\Entity\AttributeDirectedEntity;
use Switon\Orm\Tests\Fixtures\Entity\ConventionConcreteOnlyEntity;
use Switon\Orm\Tests\Fixtures\Entity\ConventionRepoProbe;
use Switon\Orm\Tests\Fixtures\Repository\AttributeDirectedCustomRepository;
use Switon\Orm\Tests\Fixtures\Repository\ConventionConcreteOnlyEntityRepository;
use Switon\Orm\Tests\Fixtures\Repository\ConventionRepoProbeRepositoryInterface;
use Switon\Orm\Tests\Fixtures\TestEntity;
use Switon\Orm\Tests\Fixtures\TestEntityMetadataEdgeCase;
use Switon\Orm\Tests\Fixtures\TestEntityReadonlyInferredPk;
use Switon\Orm\Tests\Fixtures\TestEntityWithReferencedKey;
use Switon\Orm\Tests\Fixtures\TestEntityWithSchemaTableInferredKey;
use Switon\Orm\Tests\Fixtures\TestEntityWithShardingTableInferredKey;
use Switon\Orm\Tests\TestCase;

class EntityMetadataEdgeCasesTest extends TestCase
{
    #[Autowired] protected EntityMetadataInterface $entityMetadata;

    protected function setUp(): void
    {
        parent::setUp();
        $this->injector->inject($this);
    }

    public function testGetTableBaseStripsSchemaPrefix(): void
    {
        $full = $this->entityMetadata->getTable(TestEntityWithSchemaTableInferredKey::class, false);
        $base = $this->entityMetadata->getTable(TestEntityWithSchemaTableInferredKey::class, true);

        $this->assertSame('schema.test_roles', $full);
        $this->assertSame('test_roles', $base);
    }

    public function testGetTableBaseStripsShardingSuffix(): void
    {
        $full = $this->entityMetadata->getTable(TestEntityWithShardingTableInferredKey::class, false);
        $base = $this->entityMetadata->getTable(TestEntityWithShardingTableInferredKey::class, true);

        $this->assertSame('test_orders:order_id%8', $full);
        $this->assertSame('test_orders', $base);
    }

    public function testGetFieldsSkipsTransientReadonlyStatic(): void
    {
        $fields = $this->entityMetadata->getFields(TestEntityMetadataEdgeCase::class);

        $this->assertContains('id', $fields);
        $this->assertContains('union_fillable', $fields);

        $this->assertNotContains('transient_fillable', $fields);
        $this->assertNotContains('static_field', $fields);
        $this->assertNotContains('readonly_field', $fields);
    }

    public function testGetFillableSkipsTransientReadonlyStaticAndTreatsUnionAsMixed(): void
    {
        $fillable = $this->entityMetadata->getFillable(TestEntityMetadataEdgeCase::class);

        $this->assertArrayHasKey('id', $fillable);

        $this->assertArrayHasKey('union_fillable', $fillable);
        $this->assertSame('mixed', $fillable['union_fillable']);

        $this->assertArrayNotHasKey('transient_fillable', $fillable);
        $this->assertArrayNotHasKey('static_field', $fillable);
        $this->assertArrayNotHasKey('readonly_field', $fillable);
    }

    public function testGetFieldTypesTreatsUnionAsMixedAndSkipsTransientReadonlyStatic(): void
    {
        $fieldTypes = $this->entityMetadata->getFieldTypes(TestEntityMetadataEdgeCase::class);

        $this->assertSame('int', $fieldTypes['id'] ?? null);
        $this->assertSame('mixed', $fieldTypes['union_fillable'] ?? null);

        $this->assertArrayNotHasKey('transient_fillable', $fieldTypes);
        $this->assertArrayNotHasKey('static_field', $fieldTypes);
        $this->assertArrayNotHasKey('readonly_field', $fieldTypes);
    }

    public function testGetFieldTypeReturnsMixedForUnknownField(): void
    {
        $fieldType = $this->entityMetadata->getFieldType(TestEntityMetadataEdgeCase::class, 'non_existing_field');

        $this->assertSame('mixed', $fieldType);
    }

    public function testGetReferencedKeyUsesClassLevelReferencedKeyAttribute(): void
    {
        $this->assertSame('custom_ref_id', $this->entityMetadata->getReferencedKey(TestEntityWithReferencedKey::class));
    }

    public function testPrimaryKeyResolutionFailsWhenInferredKeyPropertyIsReadonly(): void
    {
        $this->expectException(PrimaryKeyNotFoundException::class);

        $this->entityMetadata->getPrimaryKey(TestEntityReadonlyInferredPk::class);
    }

    public function testGetRepositoryUsesExplicitRepositoryAttributeClass(): void
    {
        $stub = $this->createStub(AttributeDirectedCustomRepository::class);
        $this->container->set(AttributeDirectedCustomRepository::class, $stub);

        $resolved = $this->entityMetadata->getRepository(AttributeDirectedEntity::class);

        $this->assertSame($stub, $resolved);
        $this->assertSame($stub, $this->entityMetadata->getRepository(AttributeDirectedEntity::class));
    }

    public function testGetRepositoryThrowsWhenEntityFqcnDoesNotMatchEntitySegmentConvention(): void
    {
        $this->expectException(RepositoryNotFoundException::class);

        $this->entityMetadata->getRepository(TestEntity::class);
    }

    public function testGetRepositoryResolvesEntityNamespaceToRepositoryInterfaceWhenPresent(): void
    {
        $stub = $this->createStub(ConventionRepoProbeRepositoryInterface::class);
        $this->container->set(ConventionRepoProbeRepositoryInterface::class, $stub);

        $resolved = $this->entityMetadata->getRepository(ConventionRepoProbe::class);

        $this->assertSame($stub, $resolved);
    }

    public function testGetRepositoryUsesConcreteRepositoryClassWhenNoInterfaceExists(): void
    {
        $stub = $this->createStub(ConventionConcreteOnlyEntityRepository::class);
        $this->container->set(ConventionConcreteOnlyEntityRepository::class, $stub);

        $resolved = $this->entityMetadata->getRepository(ConventionConcreteOnlyEntity::class);

        $this->assertSame($stub, $resolved);
    }
}
