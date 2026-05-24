<?php

use App\UI\Screens\Demo\TableDemo;
use App\Models\Movie;
use Database\Seeders\GenreSeeder;
use Database\Seeders\MovieSeeder;

const MOVIES_TOTAL = 17;
const MOVIES_PER_PAGE = 7;
const MOVIES_TOTAL_PAGES = 3;

function firstTableRowComponent(array $payload, int $rowIndex = 0): array
{
    foreach ($payload as $key => $component) {
        if (!is_array($component)) {
            continue;
        }

        if (($component['type'] ?? null) !== 'tablerow') {
            continue;
        }

        if (($component['row'] ?? null) !== $rowIndex) {
            continue;
        }

        $component['_json_key'] = (int) $key;
        return $component;
    }

    throw new RuntimeException("Table row {$rowIndex} not found in payload.");
}

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    $this->seed([GenreSeeder::class, MovieSeeder::class]);
});

it('loads movies table with expected configuration', function () {
    $ui = uiScenario($this, TableDemo::class, ['reset' => true]);

    $table = $ui->component('movies_table')->data();

    expect($table['type'] ?? null)->toBe('table');
    expect($table['title'] ?? null)->toBe(t('screen.demo.table_demo.table_title'));
    expect($table['pagination']['enabled'] ?? null)->toBeTrue();
    expect($table['pagination']['per_page'] ?? null)->toBe(MOVIES_PER_PAGE);
    expect($table['pagination']['current_page'] ?? null)->toBe(1);
    expect($table['pagination']['total_items'] ?? null)->toBe(MOVIES_TOTAL);
    expect($table['pagination']['total_pages'] ?? null)->toBe(MOVIES_TOTAL_PAGES);
    expect($table['pagination']['can_next'] ?? null)->toBeTrue();
    expect($table['pagination']['can_prev'] ?? null)->toBeFalse();
    expect($table['pagination']['show_controls'] ?? null)->toBeTrue();

    $ui->assertNoIssues();
});

it('movies table initializes with sort by title ascending', function () {
    $ui = uiScenario($this, TableDemo::class, ['reset' => true]);

    $table = $ui->component('movies_table')->data();

    expect($table['sort_column'] ?? null)->toBe('title');
    expect($table['sort_direction'] ?? null)->toBe('asc');

    $ui->assertNoIssues();
});

it('changes page and updates pagination flags', function () {
    $ui = uiScenario($this, TableDemo::class, ['reset' => true]);

    $response = $ui->action('movies_table', 'change_page', ['page' => MOVIES_TOTAL_PAGES]);
    $response->assertOk();

    $table = $ui->component('movies_table')->data();
    expect($table['pagination']['current_page'] ?? null)->toBe(MOVIES_TOTAL_PAGES);
    expect($table['pagination']['can_prev'] ?? null)->toBeTrue();
    expect($table['pagination']['can_next'] ?? null)->toBeFalse();
    expect($table['pagination']['show_controls'] ?? null)->toBeTrue();

    $ui->assertNoIssues();
});

it('sorts movies by column and resets to page 1', function () {
    $ui = uiScenario($this, TableDemo::class, ['reset' => true]);

    // Trigger page change first; with pagination disabled, page remains 1.
    $ui->action('movies_table', 'change_page', ['page' => 2]);

    $response = $ui->action('movies_table', 'movies_table_column_clicked', ['sort_by' => 'release_year']);
    $response->assertOk();

    $table = $ui->component('movies_table')->data();
    expect($table['pagination']['current_page'] ?? null)->toBe(1);
    expect($table['sort_column'] ?? null)->toBe('release_year');

    $ui->assertNoIssues();
});

it('configures row click actions using the table name and model id', function () {
    $response = getScreenJson($this, TableDemo::class, ['reset' => true]);
    $response->assertOk();

    $firstRow = firstTableRowComponent($response->json(), 0);

    expect($firstRow['action'] ?? null)->toBe('movies_table_row_clicked');
    expect($firstRow['parameters']['model_id'] ?? null)->toBeInt();
});

it('handles movie row click through the table name convention', function () {
    /** @var \Tests\TestCase $this */
    $response = getScreenJson($this, TableDemo::class, ['reset' => true]);
    $response->assertOk();

    $payload = $response->json();
    $firstRow = firstTableRowComponent($payload, 0);
    $movieId = $firstRow['parameters']['model_id'] ?? null;
    $storageKey = config('usim.app_id', config('ui-services.app_id'));
    $storage = $payload['storage'][$storageKey] ?? null;

    expect($movieId)->toBeInt();
    expect($storage)->toBeString();

    $movieTitle = Movie::query()->findOrFail($movieId)->title;
    expect($movieTitle)->toBeString()->not->toBe('');

    $eventResponse = $this->postJson('/api/ui-event', [
        'component_id' => $firstRow['_json_key'],
        'event' => 'click',
        'action' => 'movies_table_row_clicked',
        'parameters' => ['model_id' => $movieId],
        $storageKey => $storage,
    ]);

    $eventResponse->assertOk();
    expect($eventResponse->json('toast.message'))->toBe(
        t('screen.demo.table_demo.row_clicked_toast', ['name' => t($movieTitle)])
    );
});
