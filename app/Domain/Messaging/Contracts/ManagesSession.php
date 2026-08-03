<?php

namespace App\Domain\Messaging\Contracts;

use App\Domain\Messaging\DataObjects\ConnectionStatusUpdate;

interface ManagesSession
{
    public function connect(): ConnectionStatusUpdate;

    public function disconnect(): ConnectionStatusUpdate;

    public function status(): ConnectionStatusUpdate;
}
