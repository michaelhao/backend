<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\PermissionRouteResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function __construct(private PermissionRouteResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();

        $action = $request->route()->getAction();
        if (! isset($action['controller'])) {
            abort(403, 'Unauthorized action.');
        }

        [$controller, $method] = explode('@', $action['controller']);
        $permissionName = $this->resolver->permissionFor($controller, $method);

        if (! $user->role_id) {
            return redirect()->route('no-role');
        }

        if ($user->permissionsSessionIsStale()) {
            $user->loadPermissionsToSession();
        }

        if (! $user->hasPermissionTo($permissionName)) {
            $defaultPermission = $user->getDefaultRoute();

            if ($defaultPermission && $defaultPermission !== $permissionName) {
                $defaultRouteName = $this->resolver->routeNameFor($defaultPermission);

                if ($defaultRouteName) {
                    return redirect()->route($defaultRouteName);
                }
            }

            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
