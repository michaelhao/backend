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

                return ['name' => $name, 'title' => $title];
            })
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
