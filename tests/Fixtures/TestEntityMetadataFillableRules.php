<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Fillable;
use Switon\Orm\Attribute\Id;
use Switon\Orm\Entity;
use Switon\Validating\Attribute\Length;

class TestEntityMetadataFillableRules extends Entity
{
    #[Id]
    public int $id;

    #[Length(1, 10)]
    #[Fillable(false)]
    public string $name;

    #[Length(1, 10)]
    public string $title;

    #[Fillable]
    public string $explicit;

    /** @see \Switon\Orm\Attribute\Fillable */
    #[Fillable]
    public string $fillableAttribute;

    public string $plain;
}
