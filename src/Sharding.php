<?php

declare(strict_types=1);

namespace Switon\Orm;

use Switon\Core\Attribute\Autowired;
use Switon\Core\Exception\RuntimeException;
use Switon\Sharding\Exception\ShardingTooManyException;
use Switon\Sharding\ShardingManagerInterface;

use function array_keys;
use function count;
use function implode;
use function strcspn;
use function strlen;

/**
 * Default shard resolver for ORM entities.
 *
 * Road-signs:
 * - read connection and table metadata
 * - resolve one many or all shards
 * - delegate expression expansion
 * - fail on multi-shard writes
 *
 * Guidance: Route writes through <code>getUniqueShard()</code> so multi-target operations fail before SQL execution.
 *
 * @see \Switon\Orm\ShardingInterface
 * @see \Switon\Orm\EntityManagerInterface
 * @see \Switon\Orm\EntityMetadataInterface::getConnection()
 * @see \Switon\Orm\EntityMetadataInterface::getTable()
 * @see \Switon\Sharding\ShardingManagerInterface
 * @see \Switon\Sharding\Exception\ShardingTooManyException
 */
class Sharding implements ShardingInterface
{
    #[Autowired] protected EntityMetadataInterface $entityMetadata;
    #[Autowired] protected ShardingManagerInterface $shardingManager;

    /**
     * Returns any usable shard for the entity class, typically for read paths that do not need shard uniqueness.
     *
     * @param class-string<Entity> $entityClass
     */
    public function getAnyShard(string $entityClass): array
    {
        $shards = $this->getAllShards($entityClass);

        if (empty($shards)) {
            RuntimeException::raise('No shards found for entity: {entityClass}', ['entityClass' => $entityClass]);
        }

        $database = array_key_first($shards);
        $tables = $database !== null ? $shards[$database] : null;
        if (empty($tables)) {
            RuntimeException::raise('No tables found in shard for entity: {entityClass}', ['entityClass' => $entityClass]);
        }

        return [$database, $tables[0]];
    }

    /**
     * Resolves exactly one shard for the given write context and fails when multiple targets would be touched.
     *
     * @param class-string<Entity> $entityClass
     *
     * @throws ShardingTooManyException If operation spans multiple databases or tables
     */
    public function getUniqueShard(string $entityClass, array|Entity $context): array
    {
        $shards = $this->getMultipleShards($entityClass, $context);
        if (count($shards) !== 1) {
            ShardingTooManyException::raise('Operation spans multiple databases ({databases}), single database required', ['databases' => array_keys($shards)]);
        }

        $database = array_key_first($shards);
        $tables = $database !== null ? $shards[$database] : null;
        if (empty($tables)) {
            RuntimeException::raise('No tables found in shard for entity: {entityClass}', ['entityClass' => $entityClass]);
        }
        if (count($tables) !== 1) {
            ShardingTooManyException::raise('Operation spans multiple tables ({tables}), single table required', ['tables' => implode(', ', $tables)]);
        }

        return [$database, $tables[0]];
    }

    /**
     * Returns every shard selected by the given entity or filter context.
     *
     * @param class-string<Entity> $entityClass
     */
    public function getMultipleShards(string $entityClass, array|Entity $context): array
    {
        $connection = $this->entityMetadata->getConnection($entityClass);
        $table = $this->entityMetadata->getTable($entityClass);

        if (strcspn($connection, ':,') === strlen($connection) && strcspn($table, ':,') === strlen($table)) {
            return [$connection => [$table]];
        } else {
            return $this->shardingManager->multiple($connection, $table, $context);
        }
    }

    /**
     * Returns all shards known for the entity class regardless of a specific write context.
     *
     * @param class-string<Entity> $entityClass
     */
    public function getAllShards(string $entityClass): array
    {
        $connection = $this->entityMetadata->getConnection($entityClass);
        $table = $this->entityMetadata->getTable($entityClass);

        if (strcspn($connection, ':,') === strlen($connection) && strcspn($table, ':,') === strlen($table)) {
            return [$connection => [$table]];
        } else {
            return $this->shardingManager->all($connection, $table);
        }
    }
}
