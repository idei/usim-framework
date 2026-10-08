<?php

it('dumps array as formatted json to console and returns original value', function () {
    $data = ['name' => 'John', 'age' => 30];

    $stream = fopen('php://memory', 'r+');
    $result = dumpJson($data, false, $stream);
    rewind($stream);
    $output = stream_get_contents($stream);
    fclose($stream);

    expect($result)->toBe($data)
        ->and($output)->toContain('"name": "John"');
});

it('dumps valid json string formatted', function () {
    $jsonString = '{"key":"value","count":5}';

    $stream = fopen('php://memory', 'r+');
    $result = dumpJson($jsonString, false, $stream);
    rewind($stream);
    $output = stream_get_contents($stream);
    fclose($stream);

    expect($result)->toBe($jsonString)
        ->and($output)->toContain('"key": "value"');
});

it('supports aliases printJson and uiDumpJson', function () {
    $data = ['test' => true];

    $stream = fopen('php://memory', 'r+');
    expect(printJson($data, false, $stream))->toBe($data);
    expect(uiDumpJson($data, false, $stream))->toBe($data);
    fclose($stream);
});
