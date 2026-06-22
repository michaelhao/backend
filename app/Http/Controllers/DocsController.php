<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocsController extends Controller
{
    /**
     * 歸入「技術分析」分類的 feature 目錄(規格文件仍優先依標題判定)。
     *
     * @var list<string>
     */
    private const TECH_DIRS = ['jwt', 'nuxt-vue', 'chat'];

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

                // 判定優先序:規格 → 技術目錄 → 開發流程
                $dir = str_contains($name, '/') ? explode('/', $name)[0] : '';
                if ($isSpec) {
                    $category = 'spec';
                } elseif (in_array($dir, self::TECH_DIRS, true)) {
                    $category = 'tech';
                } else {
                    $category = 'flow';
                }

                return [
                    'name' => $name,
                    'heading' => $heading,
                    'category' => $category,
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
