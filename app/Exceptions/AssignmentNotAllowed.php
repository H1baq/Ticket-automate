<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A ticket cannot be assigned the way it was asked. The message is a plain sentence meant to be shown.
 */
class AssignmentNotAllowed extends RuntimeException
{
}
