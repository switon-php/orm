<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Switon\Core\MakerInterface;
use Switon\Db\TransactionManagerInterface;
use Switon\Orm\Attribute\DateFormat;
use Switon\Orm\Attribute\HasMany;
use Switon\Orm\Attribute\HasManyThrough;
use Switon\Orm\Attribute\HasManyToMany;
use Switon\Orm\Attribute\HasOne;
use Switon\Orm\Attribute\MorphMany;
use Switon\Orm\Attribute\MorphTo;
use Switon\Orm\Attribute\NamingStrategy;
use Switon\Orm\Attribute\Owner;
use Switon\Orm\Attribute\PageLimit;
use Switon\Orm\Attribute\ReferencedKey;
use Switon\Orm\Attribute\Transactional;
use Switon\Orm\Attribute\Transient;
use Switon\Orm\Attribute\Transiently;
use Switon\Orm\Relation\HasManyRelation;
use Switon\Orm\Relation\HasManyThroughRelation;
use Switon\Orm\Relation\HasManyToManyRelation;
use Switon\Orm\Relation\HasOneRelation;
use Switon\Orm\Relation\MorphManyRelation;
use Switon\Orm\Relation\MorphToRelation;
use Switon\Orm\RelationInterface;
use ReflectionClass;
use RuntimeException;

#[AllowMockObjectsWithoutExpectations]
class AttributeBasicsTest extends TestCase
{
    public function testDateFormatStoresAndReturnsFormat(): void
    {
        $attribute = new DateFormat('Y-m-d H:i:s');

        $this->assertSame('Y-m-d H:i:s', $attribute->get());
    }

    public function testNamingStrategyStoresConfiguredStrategy(): void
    {
        $attribute = new NamingStrategy(NamingStrategy::CAMEL);

        $this->assertSame(NamingStrategy::CAMEL, $attribute->strategy);
    }

    public function testReferencedKeyStoresConfiguredName(): void
    {
        $attribute = new ReferencedKey('custom_ref_id');

        $this->assertSame('custom_ref_id', $attribute->name);
    }

    public function testOwnerDefaultsToCreatedBy(): void
    {
        $attribute = new Owner();

        $this->assertSame('created_by', $attribute->field);
    }

    public function testOwnerCanUseCustomField(): void
    {
        $attribute = new Owner('admin_id');

        $this->assertSame('admin_id', $attribute->field);
    }

    public function testOwnerCanDisableImplicitOwnership(): void
    {
        $attribute = new Owner(null);

        $this->assertNull($attribute->field);
    }

    public function testPageLimitStoresConfiguredMax(): void
    {
        $attribute = new PageLimit(1000);

        $this->assertSame(1000, $attribute->max);
    }

    public function testTransientImplementsTransientlyMarker(): void
    {
        $attribute = new Transient();

        $this->assertInstanceOf(Transiently::class, $attribute);
    }

    public function testHasManyCreateRelationPassesExpectedArguments(): void
    {
        $attribute = new HasMany('App\\Entity\\Order', 'user_id', ['id' => SORT_DESC], HasManyRelation::class);
        $relation = $this->createMock(RelationInterface::class);
        $maker = $this->createMock(MakerInterface::class);
        $maker->expects($this->once())
            ->method('make')
            ->with(HasManyRelation::class, ['App\\Entity\\Order', 'user_id', ['id' => SORT_DESC]])
            ->willReturn($relation);

        $this->assertSame($relation, $attribute->createRelation($maker));
    }

    public function testHasOneCreateRelationPassesExpectedArguments(): void
    {
        $attribute = new HasOne('user_id', HasOneRelation::class);
        $relation = $this->createMock(RelationInterface::class);
        $maker = $this->createMock(MakerInterface::class);
        $maker->expects($this->once())
            ->method('make')
            ->with(HasOneRelation::class, ['user_id'])
            ->willReturn($relation);

        $this->assertSame($relation, $attribute->createRelation($maker));
    }

    public function testHasManyThroughCreateRelationPassesExpectedArguments(): void
    {
        $attribute = new HasManyThrough('App\\Entity\\Comment', 'App\\Entity\\Post', 'user_id', 'post_id', ['id' => SORT_ASC], HasManyThroughRelation::class);
        $relation = $this->createMock(RelationInterface::class);
        $maker = $this->createMock(MakerInterface::class);
        $maker->expects($this->once())
            ->method('make')
            ->with(HasManyThroughRelation::class, ['App\\Entity\\Comment', 'App\\Entity\\Post', 'user_id', 'post_id', ['id' => SORT_ASC]])
            ->willReturn($relation);

        $this->assertSame($relation, $attribute->createRelation($maker));
    }

    public function testHasManyToManyCreateRelationPassesExpectedArguments(): void
    {
        $attribute = new HasManyToMany('App\\Entity\\AdminRole', 'App\\Entity\\Role', ['id' => SORT_ASC], HasManyToManyRelation::class);
        $relation = $this->createMock(RelationInterface::class);
        $maker = $this->createMock(MakerInterface::class);
        $maker->expects($this->once())
            ->method('make')
            ->with(HasManyToManyRelation::class, ['App\\Entity\\AdminRole', 'App\\Entity\\Role', ['id' => SORT_ASC]])
            ->willReturn($relation);

        $this->assertSame($relation, $attribute->createRelation($maker));
    }

    public function testMorphManyCreateRelationPassesExpectedArguments(): void
    {
        $attribute = new MorphMany('App\\Entity\\Comment', 'commentable_table', 'commentable_id', ['id' => SORT_DESC], MorphManyRelation::class);
        $relation = $this->createMock(RelationInterface::class);
        $maker = $this->createMock(MakerInterface::class);
        $maker->expects($this->once())
            ->method('make')
            ->with(MorphManyRelation::class, ['App\\Entity\\Comment', 'commentable_table', 'commentable_id', ['id' => SORT_DESC]])
            ->willReturn($relation);

        $this->assertSame($relation, $attribute->createRelation($maker));
    }

    public function testMorphToCreateRelationPassesExpectedArguments(): void
    {
        $attribute = new MorphTo('commentable_table', 'commentable_id', MorphToRelation::class);
        $relation = $this->createMock(RelationInterface::class);
        $maker = $this->createMock(MakerInterface::class);
        $maker->expects($this->once())
            ->method('make')
            ->with(MorphToRelation::class, ['tableField' => 'commentable_table', 'idField' => 'commentable_id'])
            ->willReturn($relation);

        $this->assertSame($relation, $attribute->createRelation($maker));
    }

    public function testTransactionalInterceptorDelegatesToTransactionManager(): void
    {
        $transactionManager = $this->createMock(TransactionManagerInterface::class);
        $attribute = new Transactional('default');
        $this->injectProperty($attribute, 'transactionManager', $transactionManager);
        $method = new ReflectionMethod(self::class, __FUNCTION__);

        $transactionManager->expects($this->once())->method('begin')->with('default');
        $transactionManager->expects($this->once())->method('commitAll');
        $transactionManager->expects($this->once())->method('rollbackAll');

        $this->assertTrue($attribute->preHandle($method));
        $return = 'ok';
        $attribute->postHandle($method, $return);
        $attribute->onException($method, new RuntimeException('fail'));
    }

    private function injectProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setValue($object, $value);
    }
}
