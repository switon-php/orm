<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use Switon\Orm\NamingStrategy\CamelNamingStrategy;
use Switon\Orm\NamingStrategy\DefaultNamingStrategy;
use Switon\Orm\Tests\TestCase;

class NamingStrategyTest extends TestCase
{
    public function testDefaultNamingStrategyPropertyToColumn(): void
    {
        $strategy = new DefaultNamingStrategy();

        $this->assertSame('userName', $strategy->propertyToColumnName('userName'));
        $this->assertSame('createdAt', $strategy->propertyToColumnName('createdAt'));
        $this->assertSame('id', $strategy->propertyToColumnName('id'));
        $this->assertSame('userId', $strategy->propertyToColumnName('userId'));
    }

    public function testDefaultNamingStrategyColumnToProperty(): void
    {
        $strategy = new DefaultNamingStrategy();

        // DefaultNamingStrategy keeps column names as-is (no conversion)
        $this->assertSame('user_name', $strategy->columnToPropertyName('user_name'));
        $this->assertSame('created_at', $strategy->columnToPropertyName('created_at'));
        $this->assertSame('id', $strategy->columnToPropertyName('id'));
        $this->assertSame('user_id', $strategy->columnToPropertyName('user_id'));
    }

    public function testDefaultNamingStrategyClassToTable(): void
    {
        $strategy = new DefaultNamingStrategy();

        $this->assertSame('user', $strategy->classToTableName('User'));
        $this->assertSame('user_profile', $strategy->classToTableName('UserProfile'));
        $this->assertSame('order_item', $strategy->classToTableName('OrderItem'));
        $this->assertSame('', $strategy->classToTableName(''));
    }

    public function testCamelNamingStrategyPropertyToColumn(): void
    {
        $strategy = new CamelNamingStrategy();

        $this->assertSame('userName', $strategy->propertyToColumnName('userName'));
        $this->assertSame('createdAt', $strategy->propertyToColumnName('createdAt'));
        $this->assertSame('id', $strategy->propertyToColumnName('id'));
        $this->assertSame('userId', $strategy->propertyToColumnName('userId'));
    }

    public function testCamelNamingStrategyColumnToProperty(): void
    {
        $strategy = new CamelNamingStrategy();

        $this->assertSame('userName', $strategy->columnToPropertyName('userName'));
        $this->assertSame('createdAt', $strategy->columnToPropertyName('createdAt'));
        $this->assertSame('id', $strategy->columnToPropertyName('id'));
        $this->assertSame('userId', $strategy->columnToPropertyName('userId'));
    }

    public function testCamelNamingStrategyClassToTable(): void
    {
        $strategy = new CamelNamingStrategy();

        // CamelNamingStrategy converts PascalCase to camelCase (first letter lowercase)
        $this->assertSame('user', $strategy->classToTableName('User'));
        $this->assertSame('userProfile', $strategy->classToTableName('UserProfile'));
        $this->assertSame('orderItem', $strategy->classToTableName('OrderItem'));
        $this->assertSame('', $strategy->classToTableName(''));
    }

    public function testDefaultNamingStrategyHandlesEdgeCases(): void
    {
        $strategy = new DefaultNamingStrategy();

        // Single character
        $this->assertSame('a', $strategy->propertyToColumnName('a'));
        $this->assertSame('a', $strategy->columnToPropertyName('a'));

        // Already snake_case - kept as-is
        $this->assertSame('user_name', $strategy->propertyToColumnName('user_name'));

        // Multiple consecutive capitals - kept as-is
        $this->assertSame('HTMLParser', $strategy->propertyToColumnName('HTMLParser'));
    }

    public function testCamelNamingStrategyHandlesEdgeCases(): void
    {
        $strategy = new CamelNamingStrategy();

        // Single character
        $this->assertSame('a', $strategy->propertyToColumnName('a'));
        $this->assertSame('a', $strategy->columnToPropertyName('a'));

        // Already camelCase
        $this->assertSame('userName', $strategy->propertyToColumnName('userName'));
    }

    public function testDefaultNamingStrategyWithNumbers(): void
    {
        $strategy = new DefaultNamingStrategy();

        $this->assertSame('userId2', $strategy->propertyToColumnName('userId2'));
        $this->assertSame('user2Name', $strategy->propertyToColumnName('user2Name'));
        $this->assertSame('user_id2', $strategy->columnToPropertyName('user_id2'));
    }

    public function testDefaultNamingStrategyWithAcronyms(): void
    {
        $strategy = new DefaultNamingStrategy();

        $this->assertSame('APIKey', $strategy->propertyToColumnName('APIKey'));
        $this->assertSame('HTTPUrl', $strategy->propertyToColumnName('HTTPUrl'));
        $this->assertSame('api_key', $strategy->columnToPropertyName('api_key'));
    }

    public function testDefaultNamingStrategyClassToTableWithNamespace(): void
    {
        $strategy = new DefaultNamingStrategy();

        // Should only use class name, not namespace
        $this->assertSame('user', $strategy->classToTableName('App\\Entity\\User'));
        $this->assertSame('user_profile', $strategy->classToTableName('App\\Entity\\UserProfile'));
    }

    public function testDefaultNamingStrategyTableToClassName(): void
    {
        $strategy = new DefaultNamingStrategy();

        $this->assertSame('User', $strategy->tableToClassName('user'));
        $this->assertSame('UserProfile', $strategy->tableToClassName('user_profile'));
        $this->assertSame('OrderItem', $strategy->tableToClassName('order_item'));
        $this->assertSame('', $strategy->tableToClassName(''));
    }

    public function testDefaultNamingStrategyTableToClassNameRoundTripWithClassToTable(): void
    {
        $strategy = new DefaultNamingStrategy();

        foreach (['User', 'UserProfile', 'BlogPost', 'OrderItem'] as $classShort) {
            $table = $strategy->classToTableName($classShort);
            $this->assertSame(
                $classShort,
                $strategy->tableToClassName($table),
                "round-trip failed for {$classShort} -> {$table}"
            );
        }
    }

    public function testCamelNamingStrategyTableToClassName(): void
    {
        $strategy = new CamelNamingStrategy();

        $this->assertSame('User', $strategy->tableToClassName('user'));
        $this->assertSame('UserProfile', $strategy->tableToClassName('userProfile'));
        $this->assertSame('BlogPost', $strategy->tableToClassName('blogPost'));
        $this->assertSame('', $strategy->tableToClassName(''));
    }

    public function testCamelNamingStrategyTableToClassNameRoundTripWithClassToTable(): void
    {
        $strategy = new CamelNamingStrategy();

        foreach (['User', 'UserProfile', 'BlogPost'] as $classShort) {
            $table = $strategy->classToTableName($classShort);
            $this->assertSame(
                $classShort,
                $strategy->tableToClassName($table),
                "round-trip failed for {$classShort} -> {$table}"
            );
        }
    }

    public function testCamelNamingStrategyClassToTableWithNamespace(): void
    {
        $strategy = new CamelNamingStrategy();

        // Should only use class name, not namespace, and convert to camelCase
        $this->assertSame('user', $strategy->classToTableName('App\\Entity\\User'));
        $this->assertSame('userProfile', $strategy->classToTableName('App\\Entity\\UserProfile'));
    }
}
