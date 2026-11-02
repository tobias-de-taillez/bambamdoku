<?php

namespace App\Domain;

use InvalidArgumentException;

/**
 * Zeitfenster innerhalb eines Tages in Minuten seit Mitternacht.
 */
final readonly class TimeWindow
{
    public function __construct(public int $start, public int $end)
    {
        if ($end <= $start) {
            throw new InvalidArgumentException('Das Ende muss nach dem Beginn liegen.');
        }
    }

    public static function fromStrings(string $start, string $end): self
    {
        return new self(self::minutes($start), self::minutes($end));
    }

    public static function minutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }

    public static function format(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    public function overlaps(self $other): bool
    {
        return $this->start < $other->end && $other->start < $this->end;
    }

    public function contains(self $other): bool
    {
        return $this->start <= $other->start && $other->end <= $this->end;
    }

    /**
     * Ob die Fenster zusammen dieses Fenster lückenlos abdecken.
     *
     * @param  iterable<self>  $windows
     */
    public function isCoveredBy(iterable $windows): bool
    {
        $windows = collect($windows)->sortBy('start')->values();
        $reached = $this->start;

        foreach ($windows as $window) {
            if ($window->start > $reached) {
                break;
            }

            $reached = max($reached, $window->end);

            if ($reached >= $this->end) {
                return true;
            }
        }

        return $reached >= $this->end;
    }

    public function startString(): string
    {
        return self::format($this->start);
    }

    public function endString(): string
    {
        return self::format($this->end);
    }

    public function duration(): int
    {
        return $this->end - $this->start;
    }
}
