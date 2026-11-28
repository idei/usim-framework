<?php

use Idei\Usim\Components\TableCell;
use Idei\Usim\Components\TableRow;
use Idei\Usim\UI;

describe('TableRow::cells', function () {
    it('returns the row instance for fluent chaining and handles an empty list', function () {
        $row = new TableRow(null, 'row_empty');

        $returned = $row->cells([]);

        expect($returned)->toBe($row)
            ->and($row->getCells())->toBe([])
            ->and($row->getRowConfig()['cells'])->toBe([]);
    });

    it('creates TableCell components for scalar and null values', function (mixed $input, ?string $expectedText, mixed $expectedRawConfigText) {
        $row = new TableRow(null, 'row_scalar');
        $row->cells([$input]);

        $cells = $row->getCells();
        expect($cells)->toHaveCount(1);

        $cell = $cells[0];
        expect($cell)->toBeInstanceOf(TableCell::class)
            ->and($cell->getName())->toBe('cell_0')
            ->and($cell->getParent())->toBe($row->getId())
            ->and($cell->getText())->toBe($expectedText)
            ->and($cell->get('text'))->toBe($expectedRawConfigText)
            ->and($cell->getChild())->toBeNull();
    })->with([
        'string value' => ['Alice', 'Alice', 'Alice'],
        'empty string' => ['', '', ''],
        'integer value' => [42, '42', 42],
        'zero integer' => [0, '0', 0],
        'float value' => [3.14, '3.14', 3.14],
        'null value' => [null, null, null],
        'boolean true' => [true, '1', '1'],
        'boolean false' => [false, '0', '0'],
    ]);

    it('attaches UIComponent instances (including leaf components and TableCell) as children of the generated TableCell', function () {
        $row = new TableRow(null, 'row_components');
        $badge = UI::newLabel('lbl_badge')->text('Active');
        $button = UI::button('btn_edit')->label('Edit');
        $existingCell = (new TableCell(null, 'custom_cell'))->text('Nested Cell');

        $row->cells([$badge, $button, $existingCell]);

        $cells = $row->getCells();
        expect($cells)->toHaveCount(3);

        expect($cells[0]->getName())->toBe('cell_0')
            ->and($cells[0]->getChild())->toBe($badge)
            ->and($badge->getParent())->toBe($cells[0]->getId())
            ->and($cells[0]->getText())->toBeNull();

        expect($cells[1]->getName())->toBe('cell_1')
            ->and($cells[1]->getChild())->toBe($button)
            ->and($button->getParent())->toBe($cells[1]->getId());

        // TableCell extends UIComponent, so cells() wraps it as the child of cell_2
        expect($cells[2]->getName())->toBe('cell_2')
            ->and($cells[2]->getChild())->toBe($existingCell)
            ->and($existingCell->getParent())->toBe($cells[2]->getId());
    });

    it('JSON-encodes associative and nested array values as cell text and sets null when JSON encoding fails', function () {
        $row = new TableRow(null, 'row_arrays');
        $assocConfig = ['label' => 'Edit', 'action' => 'edit_user', 'id' => 7];
        $nestedList = [1, ['two' => 2]];
        $unencodableArray = ["\xB1\x31"];

        $row->cells([$assocConfig, $nestedList, $unencodableArray]);

        $cells = $row->getCells();
        expect($cells)->toHaveCount(3)
            ->and($cells[0]->getText())->toBe(json_encode($assocConfig))
            ->and($cells[1]->getText())->toBe(json_encode($nestedList))
            ->and($cells[2]->getText())->toBeNull()
            ->and($cells[2]->get('text'))->toBeNull();
    });

    it('JSON-encodes non-UIComponent objects and falls back to null for unencodable objects or resources', function () {
        $row = new TableRow(null, 'row_objects');

        $encodableObj = (object) ['status' => 'ok', 'count' => 3];
        $jsonSerializable = new class implements JsonSerializable
        {
            /** @return array<string, mixed> */
            public function jsonSerialize(): array
            {
                return ['custom' => true];
            }
        };
        $unencodableObj = (object) ['bad' => NAN];
        $resource = fopen('php://memory', 'r');

        try {
            $row->cells([$encodableObj, $jsonSerializable, $unencodableObj, $resource]);

            $cells = $row->getCells();
            expect($cells)->toHaveCount(4)
                ->and($cells[0]->getText())->toBe('{"status":"ok","count":3}')
                ->and($cells[1]->getText())->toBe('{"custom":true}')
                ->and($cells[2]->getText())->toBeNull()
                ->and($cells[3]->getText())->toBeNull();
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }
    });

    it('uses array keys in generated cell names, stores raw data in config, and excludes raw cells from toJson output', function () {
        $row = new TableRow(null, 'row_mixed');
        $btn = UI::button('btn_row_action')->label('Go');
        $input = [
            'name' => 'Jane',
            'score' => 99.5,
            'active' => true,
            'action' => $btn,
        ];

        /** @phpstan-ignore argument.type */
        $row->cells($input);

        $cells = $row->getCells();
        expect($cells)->toHaveCount(4)
            ->and(array_map(fn (TableCell $c) => $c->getName(), $cells))
            ->toBe(['cell_name', 'cell_score', 'cell_active', 'cell_action'])
            ->and($row->getRowConfig()['cells'])->toBe($input);

        $json = $row->toJson();
        expect($json)->toHaveKey($row->getId())
            ->and($json[$row->getId()])->not->toHaveKey('cells')
            ->and($json)->toHaveKey($cells[0]->getId())
            ->and($json[$cells[0]->getId()]['text'])->toBe('Jane')
            ->and($json)->toHaveKey($btn->getId());
    });

    it('overwrites raw config cells while appending new TableCell components on subsequent calls', function () {
        $row = new TableRow(null, 'row_subsequent');

        $row->cells(['First', 'Second']);
        expect($row->getCells())->toHaveCount(2)
            ->and($row->getRowConfig()['cells'])->toBe(['First', 'Second']);

        $row->cells(['Third']);
        $cells = $row->getCells();

        expect($row->getRowConfig()['cells'])->toBe(['Third'])
            ->and($cells)->toHaveCount(3)
            ->and($cells[0]->getText())->toBe('First')
            ->and($cells[1]->getText())->toBe('Second')
            ->and($cells[2]->getText())->toBe('Third')
            ->and($cells[2]->getName())->toBe('cell_0');
    });
});
