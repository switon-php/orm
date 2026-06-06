<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Entity;

class TestUserRole extends Entity
{
    public int $user_id;

    public int $role_id;
}
