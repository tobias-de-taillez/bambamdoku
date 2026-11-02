<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user?->id)],
            'role' => ['required', Rule::enum(Role::class)],
            'member_number' => ['nullable', 'string', 'max:50', Rule::unique(User::class)->ignore($user?->id)],
            'supervisor_until' => ['nullable', 'date'],
            'active' => ['boolean'],
            'groups' => ['array'],
            'groups.*' => ['integer', 'exists:groups,id'],
            'send_invitation' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'member_number' => __('Mitgliedsnummer'),
            'supervisor_until' => __('Aufsicht gültig bis'),
            'groups' => __('Gruppen'),
        ];
    }
}
