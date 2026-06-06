<?php

declare(strict_types=1);

namespace Switon\Orm\Exception;

use Switon\Orm\Exception;

/**
 * Exception for invalid eager-load relation payloads.
 *
 * Raised when relation eager-load data does not match supported payload shapes.
 *
 * @see \Switon\Orm\Exception
 * @see \Switon\Orm\RelationManagerInterface Typical consumer
 * @see \Switon\Orm\RelationManager::getQuery() Typical raise site
 * @see \Switon\Orm\RelationManager::earlyLoad() Typical raise site
 */
class RelationPayloadInvalidException extends Exception
{
}
