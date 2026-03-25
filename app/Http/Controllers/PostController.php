<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;    

class PostController extends Controller
{
    public function test()
    {
        // 1. Create (新增)
        $post = Post::create([
            'title' => '我的第一篇測試文章 ' . now(),
            'content' => '這是一段由 Laravel 13 自動產生的內容。'
        ]);

        // 2. Read (查詢全部)
        $allPosts = Post::all();

        // 3. Update (更新最後一筆)
        $lastPost = Post::latest()->first();
        $lastPost->update(['title' => $lastPost->title . ' (已更新)']);

        // 4. Delete (刪除過舊的資料，保持資料庫乾淨)
        if ($allPosts->count() > 5) {
            Post::first()->delete();
        }

        return response()->json([
            'status' => 'success',
            'current_count' => $allPosts->count(),
            'latest_data' => $lastPost,
            'message' => 'CRUD 測試完成！'
        ]);
    }
}
