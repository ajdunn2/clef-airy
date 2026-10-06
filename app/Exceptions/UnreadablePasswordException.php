<?php

namespace App\Exceptions;

use InvalidArgumentException;

class UnreadablePasswordException extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('Re-enter your API password in Configuration before sending a request.');
    }
}
