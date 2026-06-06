<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Attribute;

/**
 * Marks the primary key property of an entity.
 *
 * Road-signs:
 * - one primary key per entity
 * - strategy guides ID generation
 * - metadata resolves writes and lookups from this
 *
 * Guidance: Match the property type to the configured strategy, especially when using generated string IDs.
 *
 * @see \Switon\Orm\EntityMetadataInterface::getPrimaryKey()
 * @see \Switon\Orm\IdGeneratorInterface
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Id
{
    /**
     * ID generation strategy name.
     *
     * @param string $strategy Strategy name: 'auto' (default, database auto-increment), 'uuid', 'uuid-v7', etc.
     */
    public function __construct(public string $strategy = 'auto')
    {
    }
}
