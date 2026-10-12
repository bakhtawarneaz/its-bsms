<?php

namespace App\Exceptions;

use RuntimeException;

class OtpDeliveryFailed extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The OTP could not be delivered to any recipient.');
    }
}