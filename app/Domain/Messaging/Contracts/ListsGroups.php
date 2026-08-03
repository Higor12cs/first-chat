<?php

namespace App\Domain\Messaging\Contracts;

use App\Domain\Messaging\DataObjects\ContactIdentity;

interface ListsGroups
{
    /**
     * @return array<int, ContactIdentity>
     */
    public function listGroups(): array;
}
