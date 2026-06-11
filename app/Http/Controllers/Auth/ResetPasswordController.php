<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class ResetPasswordController extends Controller
{
    public function __construct(private UserRepository $userRepository) {}

    public function showResetForm(string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => request()->query('email', ''),
        ]);
    }

    public function reset(ResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password]);
                $user->save();

                // 密碼重設後讓該帳號既有登入全部失效
                $this->userRepository->deleteSessionsByUserId($user->id);

                event(new PasswordReset($user));
            },
        );

        if ($status === Password::PasswordReset) {
            return redirect()->route('login')->with('status', '密碼已重設，請重新登入');
        }

        $message = match ($status) {
            Password::InvalidToken,
            Password::InvalidUser => '重設連結已過期或無效，請重新申請',
            default => '密碼重設失敗',
        };

        return back()->withErrors(['email' => $message]);
    }
}
