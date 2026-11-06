<?php

declare(strict_types=1);

namespace app\common\auth;

use app\common\annotation\NoNeedLogin;
use app\common\annotation\NoNeedPermission;
use app\common\annotation\Permission;
use ReflectionClass;
use ReflectionMethod;

/**
 * 读取控制器与方法上的鉴权注解，后台与会员中间件共用。
 *
 * 规则：方法注解优先于类注解，类注解沿父类链查找；目标无法解析时返回最严格要求（失败即安全）。
 * 结果按进程缓存，注解在运行期不会变化。
 */
final class AuthRequirementResolver
{
    /** @var array<string, AuthRequirement> */
    private static array $cache = [];

    public function for(string $controller, string $action): AuthRequirement
    {
        return self::$cache[$controller . '::' . $action] ??= $this->resolve($controller, $action);
    }

    private function resolve(string $controller, string $action): AuthRequirement
    {
        if ($controller === '' || $action === '' || !class_exists($controller) || !method_exists($controller, $action)) {
            return AuthRequirement::strictest();
        }
        $method = new ReflectionMethod($controller, $action);
        $class = new ReflectionClass($controller);

        // 方法注解先于类注解：方法自身的声明决定结果，类注解只在方法未声明时生效；
        // 方法声明了权限码时，类上的 NoNeedLogin / NoNeedPermission 都不能放宽它。
        $methodCode = $this->code($method);
        $methodOpen = $method->getAttributes(NoNeedPermission::class) !== [];
        $login = $method->getAttributes(NoNeedLogin::class) === []
            && ($methodCode !== null || !$this->classHas($class, NoNeedLogin::class));
        if ($methodCode !== null) {
            return new AuthRequirement($login, true, $methodCode);
        }
        if ($methodOpen) {
            return new AuthRequirement($login, false, null);
        }
        $classCode = $this->classCode($class);
        $permission = $classCode !== null || !$this->classHas($class, NoNeedPermission::class);

        return new AuthRequirement($login, $permission, $classCode);
    }

    private function classHas(ReflectionClass $class, string $attribute): bool
    {
        for ($current = $class; $current !== false; $current = $current->getParentClass()) {
            if ($current->getAttributes($attribute) !== []) {
                return true;
            }
        }
        return false;
    }

    private function code(ReflectionMethod|ReflectionClass $target): string|array|null
    {
        $found = $target->getAttributes(Permission::class);
        return $found === [] ? null : $found[0]->newInstance()->code;
    }

    private function classCode(ReflectionClass $class): string|array|null
    {
        for ($current = $class; $current !== false; $current = $current->getParentClass()) {
            $code = $this->code($current);
            if ($code !== null) {
                return $code;
            }
        }
        return null;
    }
}
