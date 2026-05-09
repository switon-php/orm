<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Entity;

class TestRolePermission extends Entity
{
    public int $role_id;

    public int $permission_id;
}
