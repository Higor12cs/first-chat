<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    private const MEMBERSHIP_FIELDS = [
        'is_active',
        'hides_other_conversations',
        'signs_messages',
        'work_days',
        'work_starts_at',
        'work_ends_at',
        'blocked_until',
    ];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => [$user === null ? 'required' : 'nullable', 'confirmed', Password::min(8)],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['boolean'],
            'hides_other_conversations' => ['boolean'],
            'signs_messages' => ['nullable', 'boolean'],
            'work_days' => ['nullable', 'array'],
            'work_days.*' => ['integer', 'min:0', 'max:6'],
            'work_starts_at' => ['nullable', 'date_format:H:i'],
            'work_ends_at' => ['nullable', 'date_format:H:i', 'after:work_starts_at'],
            'auto_lock_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'blocked_until' => ['nullable', 'date'],
            'roles' => ['array'],
            'roles.*' => ['uuid', 'exists:roles,id'],
            'service_queues' => ['array'],
            'service_queues.*' => ['uuid', 'exists:service_queues,id'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function accountAttributes(): array
    {
        return collect($this->validated())
            ->except([...self::MEMBERSHIP_FIELDS, 'roles', 'service_queues'])
            ->reject(fn (mixed $value, string $field): bool => $field === 'password' && blank($value))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function membershipAttributes(): array
    {
        return collect($this->validated())
            ->only(self::MEMBERSHIP_FIELDS)
            ->all();
    }
}
