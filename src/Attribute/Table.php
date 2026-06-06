<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Attribute;

/**
 * Declares the database table or table-sharding rule for an entity.
 *
 * Road-signs:
 * - overrides naming-strategy inference
 * - supports table sharding expression
 * - prefixes apply outside this attribute
 *
 * Guidance: Keep table names prefix-free here and use explicit table mapping when schema names differ from inference.
 *
 * @see \Switon\Orm\EntityMetadataInterface::getTable()
 * @see \Switon\OrmCodegen\EntityScanner
 * @see \Switon\Orm\NamingStrategyInterface
 * @see \Switon\Orm\Attribute\Connection
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Table
{
    /**
     * Creates a new Table attribute instance.
     *
     * @param string $name The database table name. Can include sharding syntax:
     *                     - Modulo: <code>'user:user_id%8'</code>
     *                     - Range: <code>'user:user_id:range:0-1000,1001-2000'</code>
     *                     - CRC32: <code>'user:email:crc32:16'</code>
     *                     - List: <code>'user:0,1,2'</code> or <code>'user_0,user_1'</code>
     */
    public function __construct(public string $name)
    {
    }
}
