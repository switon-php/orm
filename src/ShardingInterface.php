<?php

declare(strict_types=1);

namespace Switon\Orm;

use Switon\Sharding\Exception\ShardingTooManyException;

/**
 * Contract for resolving database and table shards for an entity.
 *
 * Guidance:
 * - use <code>getUniqueShard()</code> on write paths
 * - use the broader shard methods only when the calling path is intentionally multi-shard aware
 *
 * Road-signs:
 * - getAnyShard is the fallback
 * - getUniqueShard is the write path
 * - getShards expands by context
 * - getAllShards expands everything
 *
 * @see \Switon\Orm\Sharding
 * @see \Switon\Orm\EntityManagerInterface
 * @see \Switon\Orm\EntityMetadataInterface::getConnection()
 * @see \Switon\Orm\EntityMetadataInterface::getTable()
 * @see \Switon\Sharding\ShardingManagerInterface
 */
interface ShardingInterface
{
    /**
     * Get any single shard for the entity class.
     *
     * Returns any available shard for the entity, typically used for operations
     * that don't require specific shard selection (e.g., schema operations).
     *
     * For non-sharded entities, returns the single database and table.
     * For sharded entities, returns one of the available shards.
     *
     * @param string $entityClass The entity class name
     *
     * @return array{0: string, 1: string} Array with [database_name, table_name]
     */
    public function getAnyShard(string $entityClass): array;

    /**
     * Get the unique shard for the entity based on context.
     *
     * Resolves to exactly one shard and one table based on the provided context.
     * Throws exception if the operation would span multiple shards or tables.
     *
     * Used for operations that must target a single shard (create, update, delete).
     *
     * @param string $entityClass The entity class name
     * @param array<string, mixed>|Entity $context Entity instance or array with sharding key values
     *
     * @return array{0: string, 1: string} Array with [database_name, table_name]
     *
     * @throws ShardingTooManyException If operation spans multiple shards/tables
     */
    public function getUniqueShard(string $entityClass, array|Entity $context): array;

    /**
     * Get multiple shards for the entity based on context.
     *
     * Resolves to one or more shards and tables based on the provided context.
     * Returns all shards that match the context criteria.
     *
     * Used for query operations that may span multiple shards.
     *
     * @param string $entityClass The entity class name
     * @param array<string, mixed>|Entity $context Entity instance or array with sharding key values
     *
     * @return array<string, array<int, string>> Associative array: [database_name => [table_name1, table_name2, ...], ...]
     */
    public function getMultipleShards(string $entityClass, array|Entity $context): array;

    /**
     * Get all shards for the entity class.
     *
     * Returns all available shards and tables for the entity class.
     * Used for operations that need to access all data (migrations, full queries).
     *
     * For non-sharded entities, returns the single database and table.
     * For sharded entities, returns all configured shards and tables.
     *
     * @param string $entityClass The entity class name
     *
     * @return array<string, array<int, string>> Associative array: [database_name => [table_name1, table_name2, ...], ...]
     */
    public function getAllShards(string $entityClass): array;
}
