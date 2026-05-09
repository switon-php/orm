<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Attribute;

/**
 * Marks a property as non-persistent for ORM writes and metadata field lists.
 *
 * Guidance: Use this for runtime-only or computed properties that should stay off the persistence path.
 *
 * @see \Switon\Orm\Attribute\Transiently
 * @see \Switon\Orm\EntityMetadata::warmupFields()
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Transient implements Transiently
{
}