<?php

namespace App\View\Components;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

class Permission extends Component
{
    public function __construct(public string $name) {}

    public function shouldRender(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return Auth::check() && $user->hasPermissionTo($this->name);
    }

    public function render(): View
    {
        return view('components.permission');
    }
}
