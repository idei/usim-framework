<?php

use App\UI\Screens\Demo\CalendarDemo;

it('loads calendar demo with expected defaults', function () {
    $ui = uiScenario($this, CalendarDemo::class, ['reset' => true]);

    $calendar = $ui->component('academic_calendar')->data();

    expect($calendar['type'] ?? null)->toBe('calendar');
    expect($calendar['year'] ?? null)->toBe(2026);
    expect($calendar['month'] ?? null)->toBe(4);
    expect($calendar['show_saturday_info'] ?? null)->toBeFalse();
    expect($calendar['show_sunday_info'] ?? null)->toBeFalse();
    expect($calendar['cell_size'] ?? null)->toBe('60px');
    expect($calendar['event_border_radius'] ?? null)->toBe('50%');
    expect($calendar['events'] ?? null)->toBe([]);

    $ui->assertNoIssues();
});

it('loads expected academic events when month changes to april 2026', function () {
    $ui = uiScenario($this, CalendarDemo::class, ['reset' => true]);

    $response = $ui->change('academic_calendar', 'month_changed', [
        'year' => 2026,
        'month' => 4,
    ]);

    $response->assertOk();

    $calendar = $ui->component('academic_calendar')->data();
    $events = $calendar['events'] ?? [];

    expect($events)->toBeArray();
    expect($events)->not->toBeEmpty();
    expect(calendarEventsContainDate($events, '2026-04-02'))->toBeTrue();
    expect(calendarEventsContainTitle($events, 'Jueves Santo'))->toBeTrue();

    $ui->assertNoIssues();
});

it('updates calendar events when changing between different months', function () {
    $ui = uiScenario($this, CalendarDemo::class, ['reset' => true]);

    $ui->change('academic_calendar', 'month_changed', [
        'year' => 2026,
        'month' => 4,
    ])->assertOk();

    $aprilEvents = $ui->component('academic_calendar')->data()['events'] ?? [];
    expect(calendarEventsContainDate($aprilEvents, '2026-04-02'))->toBeTrue();

    $ui->change('academic_calendar', 'month_changed', [
        'year' => 2026,
        'month' => 7,
    ])->assertOk();

    $julyEvents = $ui->component('academic_calendar')->data()['events'] ?? [];
    expect($julyEvents)->toBeArray();
    expect($julyEvents)->not->toBeEmpty();
    expect(calendarEventsContainDate($julyEvents, '2026-04-02'))->toBeFalse();
    expect(calendarEventsContainTitle($julyEvents, 'Receso Invernal'))->toBeTrue();

    $ui->assertNoIssues();
});

it('returns empty events for a month without configured entries', function () {
    $ui = uiScenario($this, CalendarDemo::class, ['reset' => true]);

    $response = $ui->change('academic_calendar', 'month_changed', [
        'year' => 2026,
        'month' => 1,
    ]);

    $response->assertOk();

    $events = $ui->component('academic_calendar')->data()['events'] ?? null;
    expect($events)->toBe([]);

    $ui->assertNoIssues();
});

if (!function_exists('calendarEventsContainDate')) {
    function calendarEventsContainDate(array $events, string $date): bool
    {
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            if (($event['date'] ?? null) === $date) {
                return true;
            }

            if (($event['start'] ?? null) === $date || ($event['end'] ?? null) === $date) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('calendarEventsContainTitle')) {
    function calendarEventsContainTitle(array $events, string $fragment): bool
    {
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $title = (string) ($event['title'] ?? '');
            if ($title !== '' && str_contains($title, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
