<?php

namespace App\Http\Controllers;

use App\Services\PostService;

class PostController extends Controller
{
    public function __construct(private PostService $postService) {}

    public function test()
    {
        return response()->json($this->postService->runCrudTest());
    }
}
