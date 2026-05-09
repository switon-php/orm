<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\BelongsTo;
use Switon\Orm\Attribute\JunctionMany;
use Switon\Orm\Entity;

class TestRolePermissionWithBelongsTo extends Entity
{
    public int $permission_id;

    public int $role_id;

    #[BelongsTo]
    public ?TestPermission $permission = null;

    #[BelongsTo]
    public ?TestRole $role = null;

    #[JunctionMany(orderBy: ['role_id' => SORT_ASC])]
    public array $roles = [];
}
