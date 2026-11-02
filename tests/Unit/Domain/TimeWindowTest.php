<?php

namespace Tests\Unit\Domain;

use App\Domain\TimeWindow;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TimeWindowTest extends TestCase
{
    public function test_parses_and_formats()
    {
        $window = TimeWindow::fromStrings('17:30', '19:00:00');

        $this->assertSame(1050, $window->start);
        $this->assertSame('19:00', $window->endString());
        $this->assertSame(90, $window->duration());
    }

    public function test_rejects_empty_windows()
    {
        $this->expectException(InvalidArgumentException::class);

        TimeWindow::fromStrings('18:00', '18:00');
    }

    public function test_overlap_is_exclusive_at_the_edges()
    {
        $a = TimeWindow::fromStrings('17:00', '18:00');

        $this->assertFalse($a->overlaps(TimeWindow::fromStrings('18:00', '19:00')));
        $this->assertTrue($a->overlaps(TimeWindow::fromStrings('17:59', '19:00')));
    }

    public function test_coverage_by_several_windows()
    {
        $booking = TimeWindow::fromStrings('17:00', '20:00');

        $this->assertTrue($booking->isCoveredBy([
            TimeWindow::fromStrings('18:30', '21:00'),
            TimeWindow::fromStrings('16:00', '18:30'),
        ]));
        $this->assertFalse($booking->isCoveredBy([
            TimeWindow::fromStrings('16:00', '18:00'),
            TimeWindow::fromStrings('18:15', '21:00'),
        ]));
        $this->assertFalse($booking->isCoveredBy([]));
    }
}
