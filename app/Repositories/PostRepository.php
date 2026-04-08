<?php

namespace App\Repositories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Collection;

class PostRepository
{
    public function create(array $data): Post
    {
        return Post::create($data);
    }

    public function getAll(): Collection
    {
        return Post::all();
    }

    public function getLatest(): ?Post
    {
        return Post::latest()->first();
    }

    public function deleteOldest(): void
    {
        Post::oldest()->first()?->delete();
    }

    public function count(): int
    {
        return Post::count();
    }
}
