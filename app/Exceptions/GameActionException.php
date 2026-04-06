<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Expected gameplay failure such as low stamina, bad target selection or route denial.
 *
 * Controllers catch this and show the message to the player instead of treating it as a server crash.
 */
class GameActionException extends RuntimeException {}
