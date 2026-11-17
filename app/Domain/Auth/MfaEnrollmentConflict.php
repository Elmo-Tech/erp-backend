<?php

namespace App\Domain\Auth;

use RuntimeException;

/** Expected concurrent enrollment conflict: abort grant consumption as well. */
final class MfaEnrollmentConflict extends RuntimeException
{
}
