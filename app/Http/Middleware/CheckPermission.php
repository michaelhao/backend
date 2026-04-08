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
        [$module, $action] = explode('.', $permissionName);
        $controllerClass = "App\\Http\\Controllers\\{$module}Controller";

        $routes = Route::getRoutes();
        foreach ($routes as $route) {
            $routeAction = $route->getAction();
            if (isset($routeAction['controller'])) {
                [$controller, $method] = explode('@', $routeAction['controller']);
                if ($controller === $controllerClass && $method === $action && $route->getName()) {
                    return $route->getName();
                }
            }
        }

        abort(403, 'Unauthorized action.');
    }
}
