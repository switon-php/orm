<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Id;
use Switon\Orm\Attribute\Table;
use Switon\Orm\Entity;

#[Table('test_users')]
class TestUser extends Entity
{
    #[Id]
    public int $user_id;
    public ?string $name = null;

    public string $username;

    public string $email;

    public int $created_at;
}
