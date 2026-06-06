<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Id;
use Switon\Orm\Attribute\ReferencedKey;
use Switon\Orm\Entity;

#[ReferencedKey('custom_ref_id')]
class TestEntityWithReferencedKey extends Entity
{
    #[Id]
    public int $id;

    public string $name;
}
