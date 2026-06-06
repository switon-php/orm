<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures\Pivot;

use Switon\Orm\Attribute\Table;
use Switon\Orm\Entity;

/**
 * Junction without BelongsTo in a sub-namespace for strict relation inference tests.
 */
#[Table('pivot_ns_user_roles')]
class UserRole extends Entity
{
    public int $user_id;

    public int $role_id;
}
