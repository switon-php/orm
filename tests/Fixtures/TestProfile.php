<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Id;
use Switon\Orm\Entity;

class TestProfile extends Entity
{
    #[Id]
    public int $profile_id;

    public int $user_id;

    public string $bio;
}
