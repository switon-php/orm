<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\BelongsTo;
use Switon\Orm\Entity;

class TestUserRoleWithBelongsTo extends Entity
{
    public int $user_id;

    public int $role_id;

    #[BelongsTo(foreignKey: 'user_id')]
    public ?TestUser $user = null;

    #[BelongsTo(foreignKey: 'role_id')]
    public ?TestRole $role = null;
}
