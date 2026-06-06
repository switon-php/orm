<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\Relation\MorphToRelation;
use Switon\Orm\Tests\TestCase;
use ReflectionMethod;

#[AllowMockObjectsWithoutExpectations]
class MorphToRelationMorphsTest extends TestCase
{
    public function testResolveUsesExplicitMapping(): void
    {
        $relation = new MorphToRelation('type_field', 'id_field');

        $metadata = $this->createMock(EntityMetadataInterface::class);
        $metadata->expects($this->never())->method('getTable');

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $metadata,
            'morphs' => [
                'posts' => 'App\\Entity\\Post',
            ],
        ]);

        $method = new ReflectionMethod($relation, 'resolveEntityClass');
        $this->assertSame('App\\Entity\\Post', $method->invoke($relation, 'posts'));
    }

    public function testResolveSupportsNumericEntityEntries(): void
    {
        $relation = new MorphToRelation('type_field', 'id_field');

        $entityClass = 'App\\Entity\\Post';

        $metadata = $this->createMock(EntityMetadataInterface::class);
        $metadata->method('getTable')
            ->with($entityClass, true)
            ->willReturn('posts');

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $metadata,
            'morphs' => [
                $entityClass,
            ],
        ]);

        $method = new ReflectionMethod($relation, 'resolveEntityClass');
        $this->assertSame($entityClass, $method->invoke($relation, 'posts'));
    }

    public function testResolveNormalizesSchemaQualifiedTable(): void
    {
        $relation = new MorphToRelation('type_field', 'id_field');

        $metadata = $this->createMock(EntityMetadataInterface::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $metadata,
            'morphs' => [
                'posts' => 'App\\Entity\\Post',
            ],
        ]);

        $method = new ReflectionMethod($relation, 'resolveEntityClass');
        $this->assertSame('App\\Entity\\Post', $method->invoke($relation, 'schema.posts'));
    }

    public function testResolveNormalizesShardingSuffix(): void
    {
        $relation = new MorphToRelation('type_field', 'id_field');

        $metadata = $this->createMock(EntityMetadataInterface::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $metadata,
            'morphs' => [
                'posts' => 'App\\Entity\\Post',
            ],
        ]);

        $method = new ReflectionMethod($relation, 'resolveEntityClass');
        $this->assertSame('App\\Entity\\Post', $method->invoke($relation, 'posts:post_id%8'));
    }

    public function testResolveThrowsWhenNotConfigured(): void
    {
        $relation = new MorphToRelation('type_field', 'id_field');

        $metadata = $this->createMock(EntityMetadataInterface::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $metadata,
            'morphs' => [],
        ]);

        $this->expectException(\Switon\Core\Exception\RuntimeException::class);

        $method = new ReflectionMethod($relation, 'resolveEntityClass');
        $method->invoke($relation, 'posts');
    }
}
