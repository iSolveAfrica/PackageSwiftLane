<?php
/**
 * TrackUiHelpersTest – unit tests for helpers/track_ui.php, the pure
 * presentation helpers behind the public tracking pages' journey rail.
 */

use PHPUnit\Framework\TestCase;

class TrackUiHelpersTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $root = dirname(__DIR__, 2);
        require_once $root . '/helpers/track_ui.php';
        require_once $root . '/helpers/track_progress.php';
        require_once $root . '/helpers/air_journey.php';
    }

    // ── Stage summary ────────────────────────────────────────────────────────

    public function testSeaSummaryAtFirstStage(): void
    {
        $s = cdp_trackStageSummary(cdp_trackProgress(1));
        $this->assertSame(1, $s['current']);
        $this->assertSame(7, $s['count']);
        $this->assertSame(0, $s['percent']);
        $this->assertSame('Received Office', $s['label']);
        $this->assertSame('Approved For Shipping', $s['next']);
        $this->assertFalse($s['done']);
    }

    public function testSeaSummaryAtLastStageIsDone(): void
    {
        $s = cdp_trackStageSummary(cdp_trackProgress(8));
        $this->assertSame(7, $s['current']);
        $this->assertSame(100, $s['percent']);
        $this->assertNull($s['next']);
        $this->assertTrue($s['done']);
    }

    public function testAirSummaryMidJourney(): void
    {
        $p = cdp_airJourney(0, 'In Transit to London', [], 'Swift Lane');
        $s = cdp_trackStageSummary($p);
        $this->assertSame(7, $s['current']);
        $this->assertSame(16, $s['count']);
        $this->assertSame(40, $s['percent']); // 6/15
        $this->assertSame('Arrived in London', $s['next']);
    }

    public function testSummaryOfEmptyProgressIsSafe(): void
    {
        $s = cdp_trackStageSummary(['index' => 3, 'steps' => []]);
        $this->assertSame(0, $s['count']);
        $this->assertSame('', $s['label']);
    }

    public function testEverySeaStageHasADescription(): void
    {
        foreach (cdp_trackProgress(1)['steps'] as $step) {
            $this->assertNotSame('', trim($step['desc']), $step['key'] . ' has no desc');
        }
    }

    // ── Fold ─────────────────────────────────────────────────────────────────

    public function testShortSeaRailNeverFolds(): void
    {
        $this->assertSame(0, cdp_trackFoldCount(cdp_trackProgress(8)));
    }

    public function testAirRailFoldsOnlyOnceWellUnderway(): void
    {
        $early = cdp_airJourney(0, 'Prepared for Airline', [], '');
        $this->assertSame(0, cdp_trackFoldCount($early));

        $mid = cdp_airJourney(0, 'In Transit to London', [], ''); // index 6
        $this->assertSame(4, cdp_trackFoldCount($mid));            // keeps 2 done stages visible

        $done = cdp_airJourney(0, 'Ready for Collection', [], ''); // index 15
        $this->assertSame(13, cdp_trackFoldCount($done));
    }

    // ── Dates ────────────────────────────────────────────────────────────────

    public function testRelativeDates(): void
    {
        $now = strtotime('2026-11-08 12:00:00');
        $this->assertSame('just now', cdp_trackRelativeDate('2026-11-08 11:59:30', $now));
        $this->assertSame('1 minute ago', cdp_trackRelativeDate('2026-11-08 11:58:50', $now));
        $this->assertSame('12 minutes ago', cdp_trackRelativeDate('2026-11-08 11:48:00', $now));
        $this->assertSame('3 hours ago', cdp_trackRelativeDate('2026-11-08 09:00:00', $now));
        $this->assertSame('yesterday', cdp_trackRelativeDate('2026-11-07 10:00:00', $now));
        $this->assertSame('4 days ago', cdp_trackRelativeDate('2026-11-04 12:00:00', $now));
        $this->assertSame('Sep 01, 2026', cdp_trackRelativeDate('2026-09-01 12:00:00', $now));
        $this->assertSame('just now', cdp_trackRelativeDate('2026-11-08 12:05:00', $now)); // clock ahead
    }

    public function testBlankAndZeroDatesAreEmpty(): void
    {
        $this->assertSame('', cdp_trackRelativeDate(''));
        $this->assertSame('', cdp_trackFmtDate('0000-00-00 00:00:00'));
        $this->assertSame('', cdp_trackIsoDate(null));
    }

    public function testIsoDateIsMachineReadable(): void
    {
        $this->assertMatchesRegularExpression('/^2026-11-08T09:00:00[+-]\d\d:\d\d$/', cdp_trackIsoDate('2026-11-08 09:00:00'));
    }

    // ── Colours ──────────────────────────────────────────────────────────────

    public function testSafeColorNormalisesAndRejects(): void
    {
        $this->assertSame('#F2B21B', cdp_trackSafeColor('f2b21b'));
        $this->assertSame('#FFCC00', cdp_trackSafeColor('#fc0'));
        $this->assertSame('#FFCB01', cdp_trackSafeColor('red; background:url(x)'));
        $this->assertSame('#FFCB01', cdp_trackSafeColor(''));
    }

    public function testInkIsDarkOnBrightAndWhiteOnDark(): void
    {
        $this->assertSame('#192A3E', cdp_trackColorInk('#FFCB01')); // brand amber
        $this->assertSame('#192A3E', cdp_trackColorInk('#86E23E')); // lime
        $this->assertSame('#FFFFFF', cdp_trackColorInk('#192A3E')); // ink
        $this->assertSame('#FFFFFF', cdp_trackColorInk('#B81F35')); // deep red
    }
}
