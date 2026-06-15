<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocsController extends Controller
{
    public function index(): View
    {
        $docs = collect(File::allFiles(base_path('docs')))
            ->filter(fn ($file): bool => $file->getExtension() === 'html')
            ->map(function ($file): array {
                // 相對 docs/ 的名稱(含子資料夾、去掉 .html),例如 chat/chat-spec
                $name = substr(str_replace('\\', '/', $file->getRelativePathname()), 0, -strlen('.html'));
                $title = preg_match('/<title>(.*?)<\/title>/s', $file->getContents(), $matches)
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
                    'modified' => date('Y-m-d', $file->getMTime()),
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
