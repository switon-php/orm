<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Id;
use Switon\Orm\Entity;

class TestComment extends Entity
{
    #[Id]
    public int $comment_id;

    public int $post_id;

    public string $content;
}
