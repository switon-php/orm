<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\HasMany;
use Switon\Orm\Attribute\Id;
use Switon\Orm\Entity;

class TestEntityWithHasManyOnArray extends Entity
{
    #[Id]
    public int $post_id;

    /** @var list<TestComment> */
    #[HasMany(TestComment::class, foreignKey: 'post_id')]
    public array $comments = [];
}
