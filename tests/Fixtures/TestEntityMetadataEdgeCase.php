<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Fillable;
use Switon\Orm\Attribute\Id;
use Switon\Orm\Attribute\Transient;
use Switon\Orm\Entity;

class TestEntityMetadataEdgeCase extends Entity
{
    #[Id]
    public int $id;

    #[Fillable]
    public int|string $union_fillable;

    #[Transient]
    #[Fillable]
    public string $transient_fillable = '';

    public static int $static_field = 1;

    public readonly string $readonly_field;
}
