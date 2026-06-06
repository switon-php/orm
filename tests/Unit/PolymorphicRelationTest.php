<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Switon\Orm\Attribute\MorphMany;
use Switon\Orm\Attribute\MorphTo;
use Switon\Orm\Entity;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\Relation\MorphManyRelation;
use Switon\Orm\Relation\MorphToRelation;
use Switon\Orm\Tests\TestCase;
use Switon\Query\QueryInterface;
use ReflectionMethod;

/**
 * Unit tests for polymorphic relationships (MorphTo and MorphMany).
 */
#[AllowMockObjectsWithoutExpectations]
class PolymorphicRelationTest extends TestCase
{
    /**
     * Test MorphTo attribute creation.
     */
    public function testMorphToAttributeCreation(): void
    {
        $attribute = new MorphTo(
            tableField: 'commentable_table',
            idField: 'commentable_id'
        );

        $this->assertSame('commentable_table', $attribute->tableField);
        $this->assertSame('commentable_id', $attribute->idField);
        $this->assertSame(MorphToRelation::class, $attribute->handler);
    }

    /**
     * Test MorphTo relation direct instantiation.
     */
    public function testMorphToRelationInstantiation(): void
    {
        $relation = new MorphToRelation('owner_table', 'owner_id');

        $this->assertInstanceOf(MorphToRelation::class, $relation);
        $this->assertSame('owner_table', $relation->getTableField());
        $this->assertSame('owner_id', $relation->getIdField());
    }

    /**
     * Test MorphMany attribute creation.
     */
    public function testMorphManyAttributeCreation(): void
    {
        $attribute = new MorphMany(
            relatedEntity: 'App\Entity\Comment',
            tableField: 'commentable_table',
            idField: 'commentable_id',
            orderBy: ['created_at' => SORT_DESC]
        );

        $this->assertSame('App\Entity\Comment', $attribute->relatedEntity);
        $this->assertSame('commentable_table', $attribute->tableField);
        $this->assertSame('commentable_id', $attribute->idField);
        $this->assertSame(['created_at' => SORT_DESC], $attribute->orderBy);
        $this->assertSame(MorphManyRelation::class, $attribute->handler);
    }

    /**
     * Test MorphMany relation direct instantiation.
     */
    public function testMorphManyRelationInstantiation(): void
    {
        $relation = new MorphManyRelation(
            'App\Entity\Like',
            'likeable_table',
            'likeable_id'
        );

        $this->assertInstanceOf(MorphManyRelation::class, $relation);
        $this->assertSame('likeable_table', $relation->getTableField());
        $this->assertSame('likeable_id', $relation->getIdField());
    }

    /**
     * Test MorphToRelation binds correctly.
     */
    public function testMorphToRelationBind(): void
    {
        $relation = new MorphToRelation('commentable_table', 'commentable_id');
        $relation->bind('App\Entity\Comment', '');

        $this->assertSame('App\Entity\Comment', $relation->getSelfEntityClass());
        $this->assertSame('', $relation->getRelatedEntityClass()); // Empty for polymorphic
    }

    /**
     * Test MorphManyRelation binds correctly.
     */
    public function testMorphManyRelationBind(): void
    {
        $relation = new MorphManyRelation(
            'App\Entity\Comment',
            'commentable_table',
            'commentable_id'
        );
        $relation->bind('App\Entity\Post', 'App\Entity\Comment');

        $this->assertSame('App\Entity\Post', $relation->getSelfEntityClass());
        $this->assertSame('App\Entity\Comment', $relation->getRelatedEntityClass());
    }

    /**
     * Test MorphToRelation handles null values gracefully.
     */
    public function testMorphToRelationHandlesNullValues(): void
    {
        $relation = new MorphToRelation('commentable_table', 'commentable_id');
        $relation->bind('App\Entity\Comment', '');

        $metadata = $this->createMock(EntityMetadataInterface::class);
        $query = $this->createMock(QueryInterface::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $metadata,
        ]);

        $entities = [
            ['commentable_table' => null, 'commentable_id' => null],
            ['commentable_table' => 'posts', 'commentable_id' => null],
        ];

        $result = $relation->earlyLoad($entities, $query, 'commentable');

        $this->assertNull($result[0]['commentable']);
        $this->assertNull($result[1]['commentable']);
    }

    /**
     * Test MorphManyRelation returns empty array when no matches.
     */
    public function testMorphManyRelationReturnsEmptyArrayWhenNoMatches(): void
    {
        $relation = new MorphManyRelation(
            'App\Entity\Comment',
            'commentable_table',
            'commentable_id'
        );
        $relation->bind('App\Entity\Post', 'App\Entity\Comment');

        $metadata = $this->createMock(EntityMetadataInterface::class);
        $metadata->method('getPrimaryKey')->willReturn('id');
        $metadata->method('getTable')->willReturn('posts');

        $query = $this->createMock(QueryInterface::class);
        $query->method('where')->willReturnSelf();
        $query->method('whereIn')->willReturnSelf();
        $query->method('fetch')->willReturn([]); // No comments

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $metadata,
        ]);

        $entities = [
            ['id' => 1, 'title' => 'Post 1'],
        ];

        $result = $relation->earlyLoad($entities, $query, 'comments');

        $this->assertCount(1, $result);
        $this->assertIsArray($result[0]['comments']);
        $this->assertEmpty($result[0]['comments']);
    }

    /**
     * Test resolveEntityClass detects table name format (contains underscore).
     */
    public function testResolveEntityClassWithTableName(): void
    {
        $relation = new MorphToRelation('type_field', 'id_field');

        $metadata = $this->createMock(EntityMetadataInterface::class);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $metadata,
            'morphs' => [
                'blog_posts' => 'App\\Entity\\Post',
            ],
        ]);

        // Use reflection to call protected method
        $method = new ReflectionMethod($relation, 'resolveEntityClass');

        $result = $method->invoke($relation, 'blog_posts');
        $this->assertSame('App\Entity\Post', $result);
    }

    /**
     * Test resolveEntityClass detects class name format (contains backslash).
     */
    public function testResolveEntityClassWithClassName(): void
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

        $result = $method->invoke($relation, 'App\Entity\Post');
        $this->assertSame('App\Entity\Post', $result);
    }

    /**
     * Test resolveEntityClass with simple table name (no underscore).
     */
    public function testResolveEntityClassWithSimpleTableName(): void
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

        // Simple name without underscore or backslash = treated as table name
        $result = $method->invoke($relation, 'posts');
        $this->assertSame('App\Entity\Post', $result);
    }

    /**
     * Test MorphToRelation lazyLoad returns configured query.
     */
    public function testMorphToRelationLazyLoad(): void
    {
        $relation = new MorphToRelation('owner_table', 'owner_id');
        $relation->bind('App\Entity\Tag', '');

        $metadata = $this->createMock(EntityMetadataInterface::class);
        $metadata->method('getPrimaryKey')
            ->with('App\\Entity\\Post')
            ->willReturn('id');

        $query = $this->createMock(QueryInterface::class);
        $query->method('where')->willReturnSelf();
        $query->method('setFetchType')->willReturnSelf();

        $metadata->method('createQuery')
            ->with('App\Entity\Post')
            ->willReturn($query);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $metadata,
            'morphs' => [
                'posts' => 'App\\Entity\\Post',
            ],
        ]);

        // Create an anonymous entity class with required properties
        $entity = new class (['owner_table' => 'posts', 'owner_id' => 123]) extends Entity {
            public string $owner_table;
            public int $owner_id;
        };

        $result = $relation->lazyLoad($entity);

        $this->assertInstanceOf(QueryInterface::class, $result);
    }

    /**
     * Test MorphManyRelation lazyLoad returns configured query.
     */
    public function testMorphManyRelationLazyLoad(): void
    {
        $relation = new MorphManyRelation(
            'App\Entity\Comment',
            'commentable_table',
            'commentable_id'
        );
        $relation->bind('App\Entity\Post', 'App\Entity\Comment');

        $metadata = $this->createMock(EntityMetadataInterface::class);
        $metadata->method('getPrimaryKey')
            ->with('App\Entity\Post')
            ->willReturn('id');
        $metadata->method('getTable')
            ->with('App\\Entity\\Post', true)
            ->willReturn('posts');

        $query = $this->createMock(QueryInterface::class);
        $query->method('orderBy')->willReturnSelf();
        $query->method('where')->willReturnSelf();
        $query->method('setFetchType')->willReturnSelf();

        $metadata->method('createQuery')
            ->with('App\Entity\Comment')
            ->willReturn($query);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $metadata,
        ]);

        // Create an anonymous entity class with required property
        $entity = new class (['id' => 42]) extends Entity {
            public int $id;
        };

        $result = $relation->lazyLoad($entity);

        $this->assertInstanceOf(QueryInterface::class, $result);
    }

    /**
     * Test MorphManyRelation with orderBy configuration.
     */
    public function testMorphManyRelationWithOrderBy(): void
    {
        $relation = new MorphManyRelation(
            'App\Entity\Comment',
            'commentable_table',
            'commentable_id',
            ['created_at' => SORT_DESC]
        );
        $relation->bind('App\Entity\Post', 'App\Entity\Comment');

        $metadata = $this->createMock(EntityMetadataInterface::class);

        $query = $this->createMock(QueryInterface::class);
        $query->expects($this->once())
            ->method('orderBy')
            ->with(['created_at' => SORT_DESC])
            ->willReturnSelf();

        $metadata->method('createQuery')
            ->with('App\Entity\Comment')
            ->willReturn($query);

        $this->injectRelationDependencies($relation, [
            'entityMetadata' => $metadata,
        ]);

        $relation->getRelatedQuery();
    }

    /**
     * Test MorphToRelation getRelatedEntityClass returns empty string.
     */
    public function testMorphToRelationGetRelatedEntityClassReturnsEmpty(): void
    {
        $relation = new MorphToRelation('type_field', 'id_field');
        $relation->bind('App\Entity\Comment', '');

        $this->assertSame('', $relation->getRelatedEntityClass());
    }

    /**
     * Test MorphTo attribute with default values.
     */
    public function testMorphToAttributeDefaultHandler(): void
    {
        $attribute = new MorphTo(
            tableField: 'parent_table',
            idField: 'parent_id'
        );

        $this->assertSame(MorphToRelation::class, $attribute->handler);
    }

    /**
     * Test MorphMany attribute with default orderBy.
     */
    public function testMorphManyAttributeDefaultOrderBy(): void
    {
        $attribute = new MorphMany(
            relatedEntity: 'App\Entity\Tag',
            tableField: 'taggable_table',
            idField: 'taggable_id'
        );

        $this->assertSame([], $attribute->orderBy);
        $this->assertSame(MorphManyRelation::class, $attribute->handler);
    }
}
