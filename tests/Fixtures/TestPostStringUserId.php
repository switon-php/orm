<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Id;
use Switon\Orm\Entity;

class TestPostStringUserId extends Entity
{
    #[Id]
    public int $post_id;

    public string $user_id;

    public string $title;

    public ?string $content = null;
}
