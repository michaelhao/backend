<?php

namespace App\Services;

use App\Models\Post;
use App\Repositories\PostRepository;

class PostService
{
    public function __construct(private PostRepository $postRepository) {}

    /**
     * 執行 CRUD 測試並回傳結果
     *
     * @return array{status: string, current_count: int, latest_data: ?Post, message: string}
     */
    public function runCrudTest(): array
    {
        $this->postRepository->create([
            'title' => '我的第一篇測試文章 '.now(),
            'content' => '這是一段由 Laravel 13 自動產生的內容。',
        ]);

        $count = $this->postRepository->count();

        $lastPost = $this->postRepository->getLatest();
        if ($lastPost) {
            $this->postRepository->update($lastPost, ['title' => $lastPost->title.' (已更新)']);
        }

        if ($count > 5) {
            $this->postRepository->deleteOldest();
        }

        return [
            'status' => 'success',
            'current_count' => $this->postRepository->count(),
            'latest_data' => $lastPost,
            'message' => 'CRUD 測試完成！',
        ];
    }
}
