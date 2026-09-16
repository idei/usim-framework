<?php

namespace Tests\Unit;

use Idei\Usim\Components\Table;
use Tests\TestCase;

class TableFitContainerTest extends TestCase
{
    public function test_fit_container_with_defaults(): void
    {
        $table = new Table('test_table');
        $table->fitContainer();

        $pagination = $table->getPaginationData();
        $this->assertTrue($pagination['enabled']);
        // availableHeight=550, overhead = 60 (toolbar) + 48 (header) + 56 (pagination) = 164
        // availableBody = 386, rowHeight = 48 -> rowCount = 8
        $this->assertSame(8, $pagination['per_page']);
        $this->assertSame('384px', $table->get('body_height'));
        $this->assertSame('384px', $table->get('body_min_height'));
        $this->assertSame('384px', $table->get('body_max_height'));
        $this->assertSame('488px', (string) $table->getMinHeight());
        $this->assertSame('hidden', $table->get('body_overflow_x'));
        $this->assertSame('hidden', $table->get('body_overflow_y'));
        $this->assertSame('center', $table->get('align'));
    }

    public function test_fit_container_without_toolbar(): void
    {
        $table = new Table('roles_table');
        $table->fitContainer(availableHeight: 550, hasToolbar: false, paginated: true);

        $pagination = $table->getPaginationData();
        $this->assertTrue($pagination['enabled']);
        // overhead = 0 + 48 + 56 = 104
        // availableBody = 446, rowHeight = 48 -> rowCount = 9
        $this->assertSame(9, $pagination['per_page']);
        $this->assertSame('432px', $table->get('body_height'));
        $this->assertSame('536px', (string) $table->getMinHeight());
        $this->assertSame('hidden', $table->get('body_overflow_y'));
    }

    public function test_fit_container_unpaginated_scrollable(): void
    {
        $table = new Table('permissions_table');
        $table->fitContainer(availableHeight: 550, hasToolbar: false, paginated: false);

        $pagination = $table->getPaginationData();
        $this->assertFalse($pagination['enabled']);
        $this->assertSame(0, $pagination['per_page']);
        // overhead = 0 + 48 (header) + 0 = 48
        // availableBody = 502, rowHeight = 48 -> rowCount = 10
        $this->assertSame('480px', $table->get('body_height'));
        $this->assertSame('528px', (string) $table->getMinHeight());
        $this->assertSame('auto', $table->get('body_overflow_y'));
    }

    public function test_fit_container_with_custom_row_height_and_padding(): void
    {
        $table = new Table('custom_table');
        $table->fitContainer(
            availableHeight: 600,
            hasToolbar: true,
            paginated: true,
            rowHeight: 50,
            paddingVertical: 20
        );

        $pagination = $table->getPaginationData();
        $this->assertTrue($pagination['enabled']);
        // overhead = 60 + 48 + 56 + 20 = 184
        // availableBody = 600 - 184 = 416, rowHeight = 50 -> rowCount = 8
        $this->assertSame(8, $pagination['per_page']);
        $this->assertSame('400px', $table->get('body_height'));
        // table minHeight = 400 + 48 + 56 = 504px
        $this->assertSame('504px', (string) $table->getMinHeight());
        $this->assertSame('hidden', $table->get('body_overflow_y'));
    }
}
