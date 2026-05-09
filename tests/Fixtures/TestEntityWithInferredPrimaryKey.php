<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Table;
use Switon\Orm\Entity;

#[Table('test_admins')]
class TestEntityWithInferredPrimaryKey extends Entity
{
    // No #[Id] attribute, no 'id' field
    // Should infer 'admin_id' from table name 'test_admins' -> last segment 'admins' -> singularized 'admin' + '_id'
    public int $admin_id;

    public string $name;
}
