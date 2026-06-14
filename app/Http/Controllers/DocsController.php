<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocsController extends Controller
{
    public function index(): View
    {
        $docs = collect(File::glob(base_path('docs/*.html')))
            ->map(function (string $path): array {
                $name = basename($path, '.html');
                $title = preg_match('/<title>(.*?)<\/title>/s', File::get($path), $matches)
                    ? trim($matches[1])
                    : $name;

                $isSpec = str_contains($title, 'Specification');
                $heading = $title;
                if (str_contains($title, '—')) {
                    [$left, $right] = array_map('trim', explode('—', $title, 2));
                    $heading = $isSpec ? $right : $left;
                }

                return [
                    'name' => $name,
                    'heading' => $heading,
                    'category' => $isSpec ? 'spec' : 'flow',
                    'modified' => date('Y-m-d', File::lastModified($path)),
                ];
            })
            ->sortBy('heading')
            ->values();

        return view('docs.index', ['docs' => $docs]);
    }

    public function show(string $name): BinaryFileResponse
    {
        $path = base_path("docs/{$name}.html");

        abort_unless(File::exists($path), 404);

        return response()->file($path);
    }
}
