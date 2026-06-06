<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Entity;

class TestArticleTestTag extends Entity
{
    public int $article_id;

    public int $tag_id;
}
