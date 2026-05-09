<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Id;
use Switon\Orm\Entity;

class TestEntity extends Entity
{
    #[Id]
    public int $id;

    public ?string $name;

    public ?int $status;
    // Properties for float conversion tests
    public ?float $price;

    // Properties for bool conversion tests
    public bool $active;
    public bool $disabled;
    public bool $enabled;
    public bool $locked;
    public bool $active1;
    public bool $active2;
    public bool $active3;
    public bool $active4;
    public bool $active5;
    public bool $active6;
}
