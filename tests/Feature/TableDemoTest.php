<?php

use App\UI\Screens\Demo\TableDemo;
use Database\Seeders\GenreSeeder;
use Database\Seeders\MovieSeeder;

// TableDemo disables pagination (pagination(0)), so all movies are rendered in one logical page.
const MOVIES_TOTAL = 17;
const MOVIES_TOTAL_PAGES = 1;

beforeEach(/** @param Tests\TestCase $this */ function () {
    $this->seed([GenreSeeder::class, MovieSeeder::class]);
});

it('loads movies table with expected configuration', function () {
    $ui = uiScenario($this, TableDemo::class, ['reset' => true]);

    $table = $ui->component('movies_table')->data();

    expect($table['type'] ?? null)->toBe('table');
    expect($table['title'] ?? null)->toBe(t('screen.demo.table_demo.table_title'));
    expect($table['pagination']['enabled'] ?? null)->toBeFalse();
    expect($table['pagination']['per_page'] ?? null)->toBe(MOVIES_TOTAL);
    expect($table['pagination']['current_page'] ?? null)->toBe(1);
    expect($table['pagination']['total_items'] ?? null)->toBe(MOVIES_TOTAL);
    expect($table['pagination']['total_pages'] ?? null)->toBe(MOVIES_TOTAL_PAGES);
    expect($table['pagination']['can_next'] ?? null)->toBeFalse();
    expect($table['pagination']['can_prev'] ?? null)->toBeFalse();
    expect($table['pagination']['show_controls'] ?? null)->toBeFalse();

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
    expect($table['pagination']['current_page'] ?? null)->toBe(1);
    expect($table['pagination']['can_prev'] ?? null)->toBeFalse();
    expect($table['pagination']['can_next'] ?? null)->toBeFalse();
    expect($table['pagination']['show_controls'] ?? null)->toBeFalse();

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
