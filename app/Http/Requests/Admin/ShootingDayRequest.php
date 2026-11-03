<?php

namespace App\Http\Requests\Admin;

use App\Domain\Recurrence;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ShootingDayRequest extends FormRequest
{
    /**
     * Ältere Formulare schickten „repeat“ als Ja/Nein (oder gar nicht) und meinten mit
     * „repeat_until“ immer „jede Woche“.
     */
    protected function prepareForValidation(): void
    {
        $repeat = $this->input('repeat');

        if ($this->isMethod('post') && in_array($repeat, [null, '', true, false, 0, 1, '0', '1'], true)) {
            $this->merge(['repeat' => $this->filled('repeat_until') ? Recurrence::WEEKLY : Recurrence::NONE]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'date' => ['required', 'date', ...($creating ? ['after_or_equal:today'] : [])],
            ...($creating ? $this->repeatRules() : []),
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'title' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'ranges' => ['required', 'array', 'min:1'],
            'ranges.*' => ['integer', 'exists:ranges,id'],
            'guests_allowed' => ['boolean'],
        ];
    }

    /**
     * Wiederholung gibt es nur beim Anlegen; bearbeitet wird immer ein einzelner Termin.
     *
     * @return array<string, array<mixed>>
     */
    private function repeatRules(): array
    {
        return [
            'repeat' => ['required', 'string', Rule::in(Recurrence::PATTERNS)],
            'repeat_until' => ['exclude_if:repeat,none', 'required', 'date', 'after:date', 'before_or_equal:'.now()->addYear()->toDateString()],
            'repeat_weeks' => ['exclude_unless:repeat,monthly_weekday', 'required', 'array', 'min:1'],
            'repeat_weeks.*' => ['distinct', Rule::in(Recurrence::WEEKS)],
        ];
    }

    /**
     * Monatlich am selben Wochentag: Das Startdatum muss selbst zu einer angekreuzten Woche passen.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $this->isMethod('post')
                || $this->input('repeat') !== Recurrence::MONTHLY_WEEKDAY
                || $validator->errors()->hasAny(['date', 'repeat', 'repeat_weeks', 'repeat_weeks.*'])) {
                return;
            }

            $date = CarbonImmutable::parse((string) $this->input('date'));

            if (! Recurrence::matchesWeeks($date, (array) $this->input('repeat_weeks'))) {
                $validator->errors()->add('repeat_weeks', Recurrence::mismatchMessage($date));
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'repeat_until.required' => __('Bitte angeben, bis wann wiederholt wird.'),
            'repeat_until.before_or_equal' => __('Serien reichen höchstens ein Jahr im Voraus.'),
            'repeat_weeks.required' => __('Bitte mindestens eine Woche im Monat ankreuzen.'),
            'repeat_weeks.min' => __('Bitte mindestens eine Woche im Monat ankreuzen.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'date' => __('Datum'),
            'repeat' => __('Wiederholen'),
            'repeat_until' => __('Wiederholen bis'),
            'repeat_weeks' => __('Wochen im Monat'),
            'starts_at' => __('Beginn'),
            'ends_at' => __('Ende'),
            'title' => __('Titel'),
            'notes' => __('Hinweise'),
            'ranges' => __('Stände'),
        ];
    }
}
