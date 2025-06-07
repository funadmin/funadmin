<?php

/**
 * This file is part of the ramsey/uuid library
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Copyright (c) Ben Ramsey <ben@benramsey.com>
 * @license http://opensource.org/licenses/MIT MIT
 */

declare(strict_types=1);

namespace Ramsey\Uuid\Rfc4122;

use Ramsey\Uuid\Uuid;

/**
 * The nil UUID is a special form of UUID that has all 128 bits set to zero (`0`)
 *
 * @link https://www.rfc-editor.org/rfc/rfc9562#section-5.9 RFC 9562, 5.9. Nil UUID
 *
 * @immutable
 */
final class NilUuid extends Uuid implements UuidInterface
{
}
