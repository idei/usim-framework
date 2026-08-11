<?php
namespace App\Http\Controllers;

use Illuminate\Support\Str;

class TermsController extends Controller
{
    public function __invoke(): \Illuminate\View\View
    {
        $locale = app()->getLocale();

        $path = resource_path("legal/terms.$locale.md");

        if (!file_exists($path)) {
            abort(404);
        }

        $markdown = file_get_contents($path);
        if ($markdown === false) {
            abort(500);
        }

        $html = Str::markdown($markdown);

        return view('terms', compact('html'));
    }
}
