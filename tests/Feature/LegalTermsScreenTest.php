<?php

use App\UI\Screens\Auth\LegalTerms;

it('loads legal terms screen with expected components and rendered markdown', function () {
    /** @var \Tests\TestCase $this */
    $uiResponse = getScreenJson($this, LegalTerms::class);
    $uiResponse->assertOk();

    $payload = $uiResponse->json();

    $title = findComponentByName($payload, 'lbl_title');
    $document = findComponentByName($payload, 'legal_terms_document');
    $closeBtn = findComponentByName($payload, 'btn_close_terms');

    expect($title)->not->toBeNull()
        ->and($title['type'])->toBe('label')
        ->and($document)->not->toBeNull()
        ->and($document['type'])->toBe('label')
        ->and($document['html'])->toContain('usim-markdown-content')
        ->and($closeBtn)->not->toBeNull()
        ->and($closeBtn['type'])->toBe('button')
        ->and($closeBtn['action'])->toBe('close_legal_terms');
});

it('handles closing legal terms screen by redirecting to home when not opened as modal', function () {
    /** @var \Tests\TestCase $this */
    $uiResponse = getScreenJson($this, LegalTerms::class);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'close_legal_terms',
        'parameters' => [],
    ]);

    $response->assertOk();
    expect($response->json('redirect'))->toBe('/');
});

