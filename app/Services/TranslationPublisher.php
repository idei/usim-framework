<?php

namespace App\Services;

use Idei\Usim\Models\UsimTextValue;
use Illuminate\Support\Facades\File;

class TranslationPublisher
{
    /**
     * Publish reviewed translations to lang/{locale}/{group}.php files.
     *
     * Only keys with needs_review = false are included.
     * If $group is null, all active groups are exported (one file each).
     *
     * @return array{files: list<string>, keys: int}
     */
    public function publish(string $languageCode, ?string $group = null): array
    {
        $rows = UsimTextValue::query()
            ->select([
                'usim_text_keys.key',
                'usim_text_keys.group',
                'usim_text_values.text_value',
            ])
            ->join('usim_languages', 'usim_languages.id', '=', 'usim_text_values.language_id')
            ->join('usim_text_keys', 'usim_text_keys.id', '=', 'usim_text_values.text_key_id')
            ->where('usim_languages.code', $languageCode)
            ->where('usim_languages.is_active', true)
            ->where('usim_text_keys.is_active', true)
            ->where('usim_text_values.needs_review', false)
            ->when($group !== null && $group !== '' && $group !== 'all', fn ($q) => $q->where('usim_text_keys.group', $group))
            ->orderBy('usim_text_keys.group')
            ->orderBy('usim_text_keys.key')
            ->get();

        // Bucket rows by group
        $byGroup = [];
        foreach ($rows as $row) {
            $grp = (string) ($row->group ?? '_ungrouped');
            $byGroup[$grp][(string) $row->key] = (string) ($row->text_value ?? '');
        }

        $langPath = lang_path($languageCode);
        File::ensureDirectoryExists($langPath);

        $publishedFiles = [];
        $totalKeys = 0;

        foreach ($byGroup as $grp => $flatKeys) {
            $nested = $this->buildNestedArray($flatKeys, $grp);
            $content = "<?php\n\nreturn " . $this->formatPhpArray($nested) . ";\n";
            File::put($langPath . DIRECTORY_SEPARATOR . $grp . '.php', $content);
            $publishedFiles[] = "lang/{$languageCode}/{$grp}.php";
            $totalKeys += count($flatKeys);
        }

        return ['files' => $publishedFiles, 'keys' => $totalKeys];
    }

    /**
     * Convert flat dot-notation keys to a nested array, stripping the group prefix.
     *
     * e.g. group="auth", key="auth.login.title" → ['login' => ['title' => '...']]
     */
    private function buildNestedArray(array $flatKeys, string $group): array
    {
        $result = [];
        $prefix = $group . '.';

        foreach ($flatKeys as $key => $value) {
            $subKey = str_starts_with($key, $prefix) ? substr($key, strlen($prefix)) : $key;
            $parts = explode('.', $subKey);
            $ref = &$result;

            foreach ($parts as $i => $part) {
                if ($i === array_key_last($parts)) {
                    $ref[$part] = $value;
                } else {
                    if (!isset($ref[$part]) || !is_array($ref[$part])) {
                        $ref[$part] = [];
                    }
                    $ref = &$ref[$part];
                }
            }

            unset($ref);
        }

        return $result;
    }

    /**
     * Render a PHP array as formatted source code (matches Laravel lang file style).
     */
    private function formatPhpArray(array $array, int $depth = 0): string
    {
        if (empty($array)) {
            return '[]';
        }

        $pad = str_repeat('    ', $depth + 1);
        $closePad = str_repeat('    ', $depth);
        $lines = [];

        foreach ($array as $key => $value) {
            $k = "'" . str_replace(["\\", "'"], ['\\\\', "\\'"], (string) $key) . "'";

            if (is_array($value)) {
                $lines[] = "{$pad}{$k} => " . $this->formatPhpArray($value, $depth + 1) . ',';
            } else {
                $v = "'" . str_replace(["\\", "'"], ['\\\\', "\\'"], (string) $value) . "'";
                $lines[] = "{$pad}{$k} => {$v},";
            }
        }

        return "[\n" . implode("\n", $lines) . "\n{$closePad}]";
    }
}
