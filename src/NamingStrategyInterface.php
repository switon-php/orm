<?php

declare(strict_types=1);

namespace Switon\Orm;

/**
 * Contract for converting class and property names to inferred table and column names.
 *
 * Road-signs:
 * - classToTableName and tableToClassName are reverse hops
 * - propertyToColumnName and columnToPropertyName are reverse hops
 * - metadata uses this only when explicit names are absent
 *
 * Guidance: Keep conversions stable and reversible enough for inference and code generation.
 *
 * @see \Switon\Orm\EntityMetadataInterface::getNamingStrategy()
 * @see \Switon\Orm\Attribute\NamingStrategy
 */
interface NamingStrategyInterface
{
    /**
     * Converts a class name to a database table name.
     *
     * **Implementation Requirements:**
     * - Must handle fully qualified class names (with namespaces)
     * - Should extract only the class name portion (ignore namespace)
     * - Must apply consistent naming convention transformation
     * - Should handle edge cases like single-word class names
     *
     * **Examples:**
     * - <code>User</code> → <code>user</code>
     * - <code>UserProfile</code> → <code>user_profile</code>
     * - <code>AdminRole</code> → <code>admin_role</code>
     * - <code>App\Entity\User</code> → <code>user</code> (namespace ignored)
     *
     * @param string $className PHP class name (may include namespace)
     *
     * @return string Database table name
     */
    public function classToTableName(string $className): string;

    /**
     * Converts a property name to a database column name.
     *
     * **Implementation Requirements:**
     * - Must apply consistent naming convention transformation
     * - Should handle single-word and multi-word property names
     * - May use className parameter for context-specific naming rules
     * - Must return valid database column name (no special characters unless escaped)
     *
     * **Examples:**
     * - <code>id</code> → <code>id</code>
     * - <code>firstName</code> → <code>first_name</code>
     * - <code>createdAt</code> → <code>created_at</code>
     * - <code>isActive</code> → <code>is_active</code>
     *
     * @param string $propertyName PHP property name
     * @param string|null $className Optional class name for context-specific naming
     *
     * @return string Database column name
     */
    public function propertyToColumnName(string $propertyName, ?string $className = null): string;

    /**
     * Converts a database table name to a PHP class name.
     *
     * This is the reverse operation of {@see classToTableName()}.
     * Used when inferring entity class names from database table names (e.g., code generation).
     *
     * **Implementation Requirements:**
     * - Must apply reverse transformation of classToTableName
     * - Should convert to PascalCase format
     * - Should handle edge cases like single-word table names
     *
     * **Examples:**
     * - <code>user</code> → <code>User</code>
     * - <code>user_profile</code> → <code>UserProfile</code>
     * - <code>admin_role</code> → <code>AdminRole</code>
     * - <code>userProfile</code> → <code>UserProfile</code>
     *
     * @param string $tableName Database table name
     *
     * @return string PHP class name (PascalCase)
     */
    public function tableToClassName(string $tableName): string;

    /**
     * Converts a database column name to a PHP property name.
     *
     * This is the reverse operation of {@see propertyToColumnName()}.
     * Used when inferring property names from database column names (e.g., code generation).
     *
     * **Implementation Requirements:**
     * - Must apply reverse transformation of propertyToColumnName
     * - Should handle both snake_case and camelCase column names
     * - Should return valid PHP property name
     *
     * **Examples:**
     * - <code>id</code> → <code>id</code>
     * - <code>first_name</code> → <code>firstName</code> (if strategy converts to camelCase)
     * - <code>created_at</code> → <code>createdAt</code> (if strategy converts to camelCase)
     * - <code>userId</code> → <code>userId</code> (if strategy keeps as-is)
     *
     * @param string $columnName Database column name
     * @param string|null $className Optional class name for context-specific naming
     *
     * @return string PHP property name
     */
    public function columnToPropertyName(string $columnName, ?string $className = null): string;
}
