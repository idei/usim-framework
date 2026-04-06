<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Idei\Usim\Models\UsimTextValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TranslationExportController extends Controller
{
    public function download(Request $request): StreamedResponse
    {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'hasAnyRole') || !$user->hasAnyRole('admin')) {
            abort(403, 'Unauthorized');
        }

        $languageCode = trim((string) $request->query('language', ''));
        $group = trim((string) $request->query('group', ''));

        if ($languageCode === '') {
            abort(422, 'Language is required');
        }

        $rows = UsimTextValue::query()
            ->select([
                'usim_text_keys.key as key',
                'usim_text_values.text_value as text_value',
            ])
            ->join('usim_languages', 'usim_languages.id', '=', 'usim_text_values.language_id')
            ->join('usim_text_keys', 'usim_text_keys.id', '=', 'usim_text_values.text_key_id')
            ->where('usim_languages.code', $languageCode)
            ->where('usim_languages.is_active', true)
            ->where('usim_text_keys.is_active', true)
            ->where('usim_text_values.needs_review', false)
            ->when($group !== '' && $group !== 'all', function ($query) use ($group): void {
                $query->where('usim_text_keys.group', $group);
            })
            ->orderBy('usim_text_keys.key')
            ->get();

        $payload = [];
        foreach ($rows as $row) {
            $payload[(string) $row->key] = (string) ($row->text_value ?? '');
        }

        $safeLanguage = Str::slug($languageCode, '_');
        $safeGroup = ($group !== '' && $group !== 'all') ? Str::slug($group, '_') : null;
        $fileName = $safeGroup
            ? "translations_{$safeGroup}_{$safeLanguage}.json"
            : "translations_{$safeLanguage}.json";

        return response()->streamDownload(function () use ($payload): void {
            echo json_encode(
                $payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }, $fileName, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }
}
