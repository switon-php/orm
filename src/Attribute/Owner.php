<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Attribute;

/**
 * Declares the ownership field used for identity-aware entity binding.
 *
 * Guidance: Use <code>#[Owner]</code> for the default <code>created_by</code> ownership field, pass another
 * field name when ownership uses a different column, or pass <code>null</code> to disable the implicit
 * <code>created_by</code> fallback for that entity.
 *
 * @see \Switon\Orm\EntityMetadataInterface::getOwnerField()
 * @see \Switon\Orm\EntityResolver
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Owner
{
    public function __construct(public ?string $field = 'created_by')
    {
    }
}
