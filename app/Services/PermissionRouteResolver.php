<?php

namespace App\Services;

use App\Attributes\RequiresPermission;
use Illuminate\Routing\Router;
use ReflectionMethod;

class PermissionRouteResolver
{
    /** @var array<string, string>|null */
    private ?array $cache = null;

    public function __construct(private Router $router) {}

    public function permissionFor(string $controller, string $method): string
    {
        try {
            $reflectionMethod = new ReflectionMethod($controller, $method);
            $attributes = $reflectionMethod->getAttributes(RequiresPermission::class);
            if (! empty($attributes)) {
                return $attributes[0]->newInstance()->permission;
            }
        } catch (\ReflectionException) {
            // 反射失敗時 fallback 自動推導
        }

        $module = str_replace('Controller', '', class_basename($controller));

        return "{$module}.{$method}";
    }

    public function routeNameFor(string $permission): ?string
    {
        return $this->getMap()[$permission] ?? null;
    }

    /** @return array<string, string> */
    private function getMap(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $map = [];

        foreach ($this->router->getRoutes() as $route) {
            $action = $route->getAction();
            $name = $route->getName();
            if (! $name || ! isset($action['controller'])) {
                continue;
            }

            [$controller, $method] = explode('@', $action['controller']);
            $key = $this->permissionFor($controller, $method);
            $map[$key] ??= $name;
        }

        return $this->cache = $map;
    }
}
