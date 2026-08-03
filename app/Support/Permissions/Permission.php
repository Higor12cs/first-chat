<?php

namespace App\Support\Permissions;

readonly class Permission
{
    /**
     * @param  array<int, string>  $routes
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $group,
        public array $routes = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'group' => $this->group,
            'routes' => $this->routes,
        ];
    }
}
