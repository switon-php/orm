<?php

declare(strict_types=1);

namespace Switon\Orm\NamingStrategy;

use Switon\Core\ClassName;
use Switon\Orm\NamingStrategyInterface;

use function strtolower;
use function substr;

/**
 * Camel-case naming strategy for databases that keep camel-style identifiers.
 *
 * Road-signs:
 * - class names become camelCase tables
 * - property names stay unchanged
 * - reverse methods support code generation
 *
 * Guidance: Use this only when the database really stores camelCase identifiers end to end.
 *
 * @see \Switon\Orm\NamingStrategyInterface
 * @see \Switon\Orm\Attribute\NamingStrategy
 */
class CamelNamingStrategy implements NamingStrategyInterface
{
    /**
     * {@inheritDoc}
     */
    public function classToTableName(string $className): string
    {
        $className = ClassName::short($className);

        // Convert first letter to lowercase (PascalCase -> camelCase)
        if ($className !== '') {
            return strtolower($className[0]) . substr($className, 1);
        }

        return $className;
    }

    /**
     * {@inheritDoc}
     */
    public function tableToClassName(string $tableName): string
    {
        // Convert camelCase to PascalCase (first letter uppercase)
        if ($tableName !== '') {
            return ucfirst($tableName);
        }
        return $tableName;
    }

    /**
     * {@inheritDoc}
     */
    public function propertyToColumnName(string $propertyName, ?string $className = null): string
    {
        // Keep property name as-is (no conversion)
        return $propertyName;
    }

    /**
     * {@inheritDoc}
     */
    public function columnToPropertyName(string $columnName, ?string $className = null): string
    {
        // Keep column name as-is (no conversion, assuming it's already camelCase)
        return $columnName;
    }
}
