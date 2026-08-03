<?php

namespace App\Domain\Messaging\Contracts;

use App\Domain\Messaging\DataObjects\InstanceProvisioning;

interface ProvisionsInstance
{
    public function isProvisioned(): bool;

    public function provision(): InstanceProvisioning;
}
