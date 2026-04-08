<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /** @var array<string, string> */
    private array $methodMap = [
        'store' => 'create',
        'edit' => 'update',
        'destroy' => 'delete',
    ];

    /** @var array<string, string> */
    private static array $permissionRouteCache = [];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $action = $request->route()->getAction();
        if (isset($action['controller'])) {
            [$controller, $method] = explode('@', $action['controller']);
            $className = class_basename($controller);
            $module = str_replace('Controller', '', $className);
            $mappedMethod = $this->methodMap[$method] ?? $method;
            $permissionName = "{$module}.{$mappedMethod}";
        } else {
            abort(403, 'Unauthorized action.');
        }

        if (! $user->role_id) {
            return redirect()->route('no-role');
        }

        if (! $user->hasPermissionTo($permissionName)) {
            $defaultPermission = $user->getDefaultRoute();

            if ($defaultPermission && $defaultPermission !== $permissionName) {
                return redirect()->route($this->permissionToRoute($defaultPermission));
            }

            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }

    private function permissionToRoute(string $permissionName): string
    {
        if (empty(self::$permissionRouteCache)) {
            $this->buildPermissionRouteCache();
        }

        if (isset(self::$permissionRouteCache[$permissionName])) {
            return self::$permissionRouteCache[$permissionName];
        }

        abort(403, 'Unauthorized action.');
    }

    private function buildPermissionRouteCache(): void
    {
        foreach (Route::getRoutes() as $route) {
            $routeAction = $route->getAction();
            if (isset($routeAction['controller']) && $route->getName()) {
                [$controller, $method] = explode('@', $routeAction['controller']);
                $className = class_basename($controller);
                $module = str_replace('Controller', '', $className);
                $mappedMethod = $this->methodMap[$method] ?? $method;
                $key = "{$module}.{$mappedMethod}";
                self::$permissionRouteCache[$key] = $route->getName();
            }
        }
    }
}
