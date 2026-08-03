<?php

namespace App\Domain\Messaging\Contracts;

use App\Domain\Messaging\DataObjects\ConnectionStatusUpdate;

interface SupportsPairingCode
{
    /**
     * @param  string  $phone  Digits only, in international format.
     */
    public function pairWithPhone(string $phone): ConnectionStatusUpdate;
}
