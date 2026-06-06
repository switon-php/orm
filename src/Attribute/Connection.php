<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Attribute;

/**
 * Declares the database connection or connection-sharding rule for an entity.
 *
 * Road-signs:
 * - overrides default connection
 * - supports database sharding expression
 * - combines with Table for full shard routing
 *
 * Guidance: Keep connection expressions deterministic and prefer shard keys that are already available before writes.
 *
 * @see \Switon\Orm\EntityMetadataInterface::getConnection()
 * @see \Switon\Orm\Attribute\Table
 * @see \Switon\Orm\ShardingInterface
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Connection
{
    /**
     * Creates a new Connection attribute instance.
     *
     * @param string $name The database connection name. Can be a simple identifier (e.g., 'secondary_db')
     *                     or a sharding rule:
     *                     - Modulo: <code>'db:user_id%4'</code>
     *                     - Range: <code>'db:user_id:range:0-1000,1001-2000'</code>
     *                     - CRC32: <code>'db:email:crc32:16'</code>
     *                     - List: <code>'db:0,1,2'</code> or <code>'db_0,db_1'</code>
     */
    public function __construct(public string $name)
    {
    }
}
