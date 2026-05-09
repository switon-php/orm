<?php

declare(strict_types=1);

namespace Switon\Orm\NamingStrategy;

use Switon\Core\ClassName;
use Switon\Core\Naming;
use Switon\Orm\NamingStrategyInterface;

/**
 * Default naming strategy for ORM inference.
 *
 * Road-signs:
 * - class names become snake_case tables
 * - property names stay unchanged
 * - reverse methods support code generation
 *
 * Guidance: Use this default unless the database naming style truly differs.
 *
 * @see \Switon\Orm\NamingStrategyInterface
 * @see \Switon\Orm\Attribute\NamingStrategy
 */
class DefaultNamingStrategy implements NamingStrategyInterface
{
    /**
     * {@inheritDoc}
     */
    public function classToTableName(string $className): string
    {
        $className = ClassName::short($className);

        // Convert PascalCase to snake_case
        return Naming::snake($className);
    }

    /**
     * {@inheritDoc}
     */
    public function tableToClassName(string $tableName): string
    {
        // Convert snake_case to PascalCase
        return Naming::pascal($tableName);
    }

    /**
     * {@inheritDoc}
     */
    public function propertyToColumnName(string $propertyName, ?string $className = null): string
    {
        // Default behavior: keep property name as-is (no conversion)
        return $propertyName;
    }

    /**
     * {@inheritDoc}
     */
    public function columnToPropertyName(string $columnName, ?string $className = null): string
    {
        // Default behavior: keep column name as-is (no conversion)
        return $columnName;
    }
}
