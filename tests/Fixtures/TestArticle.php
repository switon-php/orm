<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Id;
use Switon\Orm\Entity;

class TestArticle extends Entity
{
    #[Id]
    public int $article_id;

    public string $title;
}
