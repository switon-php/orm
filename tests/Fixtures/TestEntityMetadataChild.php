<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Transient;

class TestEntityMetadataChild extends TestEntityMetadataParent
{
    use TestEntityMetadataTrait;

    public string $child_field;

    #[Transient]
    public string $transient_field = '';
}
