<?php

namespace Tests\Feature\Admin;

use App\Domain\Recurrence;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Erwartete Termine nach dem Kalender (cal), nicht nachgerechnet.
 */
class RecurrenceTest extends TestCase
{
    public function test_without_repetition_only_the_start_date()
    {
        $this->assertSame(['2026-11-07'], Recurrence::dates('2026-11-07', Recurrence::NONE, '2027-01-01'));
        $this->assertSame(['2026-11-07'], Recurrence::dates('2026-11-07', Recurrence::WEEKLY, null));
    }

    public function test_weekly_until_inclusive()
    {
        $this->assertSame(
            ['2026-11-07', '2026-11-14', '2026-11-21', '2026-11-28'],
            Recurrence::dates('2026-11-07', Recurrence::WEEKLY, '2026-11-28'),
        );
    }

    public function test_biweekly()
    {
        $this->assertSame(
            ['2026-11-04', '2026-11-18', '2026-12-02', '2026-12-16'],
            Recurrence::dates('2026-11-04', Recurrence::BIWEEKLY, '2026-12-16'),
        );
    }

    public function test_until_limit_cuts_the_series()
    {
        $this->assertSame(
            ['2026-11-04', '2026-11-18', '2026-12-02'],
            Recurrence::dates('2026-11-04', Recurrence::BIWEEKLY, '2026-12-15'),
        );
        $this->assertSame(
            ['2026-11-07', '2026-11-21', '2026-12-05'],
            Recurrence::dates('2026-11-07', Recurrence::MONTHLY_WEEKDAY, '2026-12-18', ['1', '3']),
        );
    }

    public function test_first_and_third_saturday_over_several_months()
    {
        $this->assertSame(
            [
                '2026-11-07', '2026-11-21',
                '2026-12-05', '2026-12-19',
                '2027-01-02', '2027-01-16',
                '2027-02-06', '2027-02-20',
            ],
            Recurrence::dates('2026-11-07', Recurrence::MONTHLY_WEEKDAY, '2027-02-28', [1, 3]),
        );
    }

    public function test_series_starting_on_the_third_saturday_skips_the_first_of_that_month()
    {
        $this->assertSame(
            ['2026-11-21', '2026-12-05', '2026-12-19'],
            Recurrence::dates('2026-11-21', Recurrence::MONTHLY_WEEKDAY, '2026-12-31', ['1', '3']),
        );
    }

    public function test_last_friday_including_months_with_five_fridays()
    {
        // Januar und April 2027 haben fünf Freitage.
        $this->assertSame(
            ['2026-11-27', '2026-12-25', '2027-01-29', '2027-02-26', '2027-03-26', '2027-04-30'],
            Recurrence::dates('2026-11-27', Recurrence::MONTHLY_WEEKDAY, '2027-04-30', ['last']),
        );
    }

    public function test_fourth_and_last_are_never_created_twice()
    {
        // November und Dezember 2026: der 4. Freitag ist der letzte; im Januar 2027 nicht.
        $this->assertSame(
            ['2026-11-27', '2026-12-25', '2027-01-22', '2027-01-29'],
            Recurrence::dates('2026-11-27', Recurrence::MONTHLY_WEEKDAY, '2027-01-31', ['4', 'last']),
        );
    }

    public function test_monthly_on_the_31st_skips_shorter_months()
    {
        $this->assertSame(
            ['2026-12-31', '2027-01-31', '2027-03-31', '2027-05-31'],
            Recurrence::dates('2026-12-31', Recurrence::MONTHLY_DAY, '2027-05-31'),
        );
    }

    public function test_monthly_on_the_same_day()
    {
        $this->assertSame(
            ['2026-11-07', '2026-12-07', '2027-01-07'],
            Recurrence::dates('2026-11-07', Recurrence::MONTHLY_DAY, '2027-01-07'),
        );
        $this->assertSame(
            ['2026-11-07', '2026-12-07'],
            Recurrence::dates('2026-11-07', Recurrence::MONTHLY_DAY, '2027-01-06'),
        );
    }

    public function test_start_date_must_match_a_ticked_week()
    {
        $second = CarbonImmutable::parse('2026-11-14');
        $fourthAndLast = CarbonImmutable::parse('2026-11-28');
        $fifth = CarbonImmutable::parse('2026-10-31');

        $this->assertFalse(Recurrence::matchesWeeks($second, ['1', '3']));
        $this->assertTrue(Recurrence::matchesWeeks($second, [2]));
        $this->assertTrue(Recurrence::matchesWeeks($fourthAndLast, ['4']));
        $this->assertTrue(Recurrence::matchesWeeks($fourthAndLast, ['last']));
        $this->assertFalse(Recurrence::matchesWeeks($fifth, ['4']));
        $this->assertTrue(Recurrence::matchesWeeks($fifth, ['last']));

        $this->assertSame('2', Recurrence::defaultWeek($second));
        $this->assertSame('last', Recurrence::defaultWeek($fifth));

        $this->assertSame('Der 14.11.2026 ist der 2. Samstag im Monat. Bitte diese Woche ankreuzen oder ein anderes Startdatum wählen.', Recurrence::mismatchMessage($second));
        $this->assertStringContainsString('der 4. und letzte Samstag', Recurrence::mismatchMessage($fourthAndLast));
        $this->assertStringContainsString('der letzte Samstag', Recurrence::mismatchMessage($fifth));
    }

    public function test_description_names_the_pattern()
    {
        $this->assertSame('jeden 1. und 3. Samstag bis 30.10.2027', Recurrence::describe('2026-11-07', Recurrence::MONTHLY_WEEKDAY, '2027-10-30', ['3', '1']));
        $this->assertSame('jeden 2., 4. und letzten Freitag bis 30.04.2027', Recurrence::describe('2026-11-13', Recurrence::MONTHLY_WEEKDAY, '2027-04-30', ['last', '2', '4']));
        $this->assertSame('jeden letzten Freitag bis 30.04.2027', Recurrence::describe('2026-11-27', Recurrence::MONTHLY_WEEKDAY, '2027-04-30', ['last']));
        $this->assertSame('jede Woche bis 28.11.2026', Recurrence::describe('2026-11-07', Recurrence::WEEKLY, '2026-11-28'));
        $this->assertSame('alle zwei Wochen bis 16.12.2026', Recurrence::describe('2026-11-04', Recurrence::BIWEEKLY, '2026-12-16'));
        $this->assertSame('monatlich am 31. bis 31.05.2027', Recurrence::describe('2026-12-31', Recurrence::MONTHLY_DAY, '2027-05-31'));
    }
}
