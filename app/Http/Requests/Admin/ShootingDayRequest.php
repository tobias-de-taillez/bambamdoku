<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShootingDayRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'date' => ['required', 'date', ...($creating ? ['after_or_equal:today'] : [])],
            'repeat_until' => ['nullable', 'date', 'after:date', 'before_or_equal:'.now()->addYear()->toDateString()],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'title' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'ranges' => ['required', 'array', 'min:1'],
            'ranges.*' => ['integer', 'exists:ranges,id'],
            'restricted_to_groups' => ['boolean'],
            'groups' => ['array', 'required_if_accepted:restricted_to_groups'],
            'groups.*' => ['integer', 'exists:groups,id'],
            'guests_allowed' => ['boolean'],
            'apply_to_series' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'date' => __('Datum'),
            'repeat_until' => __('Wiederholen bis'),
            'starts_at' => __('Beginn'),
            'ends_at' => __('Ende'),
            'title' => __('Titel'),
            'notes' => __('Hinweise'),
            'ranges' => __('Stände'),
            'groups' => __('Gruppen'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'groups.required_if_accepted' => __('Wählen Sie mindestens eine Gruppe oder geben Sie den Tag für alle frei.'),
        ];
    }
}
