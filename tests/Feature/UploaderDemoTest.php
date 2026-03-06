<?php

use App\UI\Screens\Demo\UploaderDemo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('loads uploader demo with expected base components', function () {
    $ui = uiScenario($this, UploaderDemo::class, ['reset' => true]);

    $ui->component('uploader_profile')->expect('type')->toBe('uploader');
    $ui->component('uploader_profile')->expect('max_files')->toBe(1);
    $ui->component('uploader_profile')->expect('aspect_ratio')->toBe('1:1');

    $ui->component('uploader_images')->expect('type')->toBe('uploader');
    $ui->component('uploader_images')->expect('max_files')->toBe(3);

    $ui->component('uploader_documents')->expect('type')->toBe('uploader');
    $ui->component('uploader_documents')->expect('max_files')->toBe(2);

    $ui->component('btn_confirm_profile')->expect('action')->toBe('process_profile');
    $ui->component('btn_confirm_documents')->expect('action')->toBe('process_documents');

    $ui->component('lbl_result')->expect('text')->toBe('Sube archivos para ver el resultado');
    $ui->component('lbl_result')->expect('style')->toBe('secondary');

    $ui->assertNoIssues();
});

it('shows validation error when processing profile without uploaded file', function () {
    $ui = uiScenario($this, UploaderDemo::class, ['reset' => true]);

    $response = $ui->click('btn_confirm_profile', [
        'uploader_profile_temp_ids' => '[]',
    ]);

    $response->assertOk();

    $ui->component('lbl_result')->expect('text')->toBe('❌ No hay foto de perfil para procesar');
    $ui->component('lbl_result')->expect('style')->toBe('danger');
    expect($response->json('clear_uploaders'))->toBeNull();

    $ui->assertNoIssues();
});

it('processes profile upload, moves file, clears temporary row and requests uploader clear', function () {
    Storage::fake('local');

    $ui = uiScenario($this, UploaderDemo::class, ['reset' => true]);

    $tempId = createTemporaryUploadRecord(
        componentId: (string) ($ui->component('uploader_profile')->data()['_id'] ?? 'uploader_profile'),
        originalFilename: 'avatar.jpg',
        storedFilename: 'avatar-temp.jpg',
        type: 'image',
        mimeType: 'image/jpeg'
    );

    $response = $ui->click('btn_confirm_profile', [
        'uploader_profile_temp_ids' => json_encode([$tempId]),
    ]);

    $response->assertOk();

    $resultText = $ui->component('lbl_result')->data()['text'] ?? '';
    expect($resultText)->toContain('Foto de perfil procesada exitosamente');
    expect($resultText)->toContain('avatar.jpg');
    $ui->component('lbl_result')->expect('style')->toBe('success');

    $uploaderProfileId = $ui->component('uploader_profile')->data()['_id'] ?? null;
    expect($response->json('clear_uploaders'))->toBe([$uploaderProfileId]);

    expect(DB::table('temporary_uploads')->where('id', $tempId)->exists())->toBeFalse();
    expect(Storage::disk('local')->exists('temp/avatar-temp.jpg'))->toBeFalse();
    expect(Storage::disk('local')->exists('uploads/images/avatar-temp.jpg'))->toBeTrue();

    $ui->assertNoIssues();
});

it('processes multiple documents and clears document uploader', function () {
    Storage::fake('local');

    $ui = uiScenario($this, UploaderDemo::class, ['reset' => true]);

    $componentId = (string) ($ui->component('uploader_documents')->data()['_id'] ?? 'uploader_documents');

    $firstId = createTemporaryUploadRecord(
        componentId: $componentId,
        originalFilename: 'manual.pdf',
        storedFilename: 'manual-temp.pdf',
        type: 'document',
        mimeType: 'application/pdf'
    );

    $secondId = createTemporaryUploadRecord(
        componentId: $componentId,
        originalFilename: 'budget.xlsx',
        storedFilename: 'budget-temp.xlsx',
        type: 'document',
        mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );

    $response = $ui->click('btn_confirm_documents', [
        'uploader_documents_temp_ids' => json_encode([$firstId, $secondId]),
    ]);

    $response->assertOk();

    $resultText = $ui->component('lbl_result')->data()['text'] ?? '';
    expect($resultText)->toContain('Documentos procesados exitosamente');
    expect($resultText)->toContain('manual.pdf');
    expect($resultText)->toContain('budget.xlsx');
    $ui->component('lbl_result')->expect('style')->toBe('success');

    $uploaderDocumentsId = $ui->component('uploader_documents')->data()['_id'] ?? null;
    expect($response->json('clear_uploaders'))->toBe([$uploaderDocumentsId]);

    expect(DB::table('temporary_uploads')->where('id', $firstId)->exists())->toBeFalse();
    expect(DB::table('temporary_uploads')->where('id', $secondId)->exists())->toBeFalse();

    expect(Storage::disk('local')->exists('temp/manual-temp.pdf'))->toBeFalse();
    expect(Storage::disk('local')->exists('temp/budget-temp.xlsx'))->toBeFalse();
    expect(Storage::disk('local')->exists('uploads/documents/manual-temp.pdf'))->toBeTrue();
    expect(Storage::disk('local')->exists('uploads/documents/budget-temp.xlsx'))->toBeTrue();

    $ui->assertNoIssues();
});

if (!function_exists('createTemporaryUploadRecord')) {
    function createTemporaryUploadRecord(
        string $componentId,
        string $originalFilename,
        string $storedFilename,
        string $type,
        string $mimeType
    ): string {
        $tempId = (string) Str::uuid();
        $tempPath = "temp/{$storedFilename}";

        Storage::disk('local')->put($tempPath, 'dummy-content');

        DB::table('temporary_uploads')->insert([
            'id' => $tempId,
            'user_id' => null,
            'component_id' => $componentId,
            'original_filename' => $originalFilename,
            'stored_filename' => $storedFilename,
            'path' => $tempPath,
            'mime_type' => $mimeType,
            'size' => 1024,
            'type' => $type,
            'metadata' => json_encode(['source' => 'test']),
            'expires_at' => now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $tempId;
    }
}
