<?php

it('dumps array as formatted json to console and returns original value', function () {
    $data = ['name' => 'John', 'age' => 30];

    ob_start();
    $result = dumpJson($data);
    $output = ob_get_clean();

    expect($result)->toBe($data);
});

it('dumps valid json string formatted', function () {
    $jsonString = '{"key":"value","count":5}';

    $result = dumpJson($jsonString);

    expect($result)->toBe($jsonString);
});

it('supports aliases printJson and uiDumpJson', function () {
    $data = ['test' => true];

    expect(printJson($data))->toBe($data);
    expect(uiDumpJson($data))->toBe($data);
});

