<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Permission extends Component
{
    public function __construct(public string $name) {}

    public function shouldRender(): bool
    {
        return auth()->check() && auth()->user()->hasPermissionTo($this->name);
    }

    public function render(): View
    {
        return view('components.permission');
    }
}
