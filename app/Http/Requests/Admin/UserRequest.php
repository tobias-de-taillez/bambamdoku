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
            // Optional: Mitglieder ohne E-Mail haben kein Konto, werden aber am Stand eingetragen.
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user?->id)],
            'role' => ['required', Rule::in([Role::Admin->value, Role::Member->value])],
            'member_number' => ['nullable', 'string', 'max:50', Rule::unique(User::class)->ignore($user?->id)],
            'member_since' => ['nullable', 'date', 'before_or_equal:today'],
            'active' => ['boolean'],
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
            'member_since' => __('Mitglied seit'),
        ];
    }
}
