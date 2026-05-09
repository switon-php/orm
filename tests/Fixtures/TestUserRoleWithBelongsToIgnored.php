<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\BelongsTo;
use Switon\Orm\Entity;

class TestUserRoleWithBelongsToIgnored extends Entity
{
    public int $user_id;

    public int $role_id;

    #[BelongsTo(foreignKey: 'user_id')]
    public ?TestUser $user = null;

    /**
     * Must be ignored by inference because it is readonly.
     * Use a foreignKey that does not exist on the junction to make wrong inference obvious.
     */
    #[BelongsTo(foreignKey: 'role_readonly_id')]
    public readonly ?TestRole $roleReadOnly;

    /**
     * Must be ignored by inference because it is static.
     * Use a foreignKey that does not exist on the junction to make wrong inference obvious.
     */
    #[BelongsTo(foreignKey: 'role_static_id')]
    public static ?TestRole $roleStatic = null;

    #[BelongsTo(foreignKey: 'role_id')]
    public ?TestRole $role = null;
}
