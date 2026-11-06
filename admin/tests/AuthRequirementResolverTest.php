<?php

declare(strict_types=1);

namespace Tests;

use app\common\annotation\NoNeedLogin;
use app\common\annotation\NoNeedPermission;
use app\common\annotation\Permission;
use app\common\auth\AuthRequirement;
use app\common\auth\AuthRequirementResolver;
use PHPUnit\Framework\TestCase;

#[NoNeedLogin]
abstract class AuthResolverPublicBase
{
}

final class AuthResolverPublicChild extends AuthResolverPublicBase
{
    public function index(): void {}
}

#[NoNeedPermission]
final class AuthResolverLoginOnly
{
    public function plain(): void {}

    #[Permission('system:admin:index')]
    public function guarded(): void {}

    #[NoNeedLogin]
    public function open(): void {}
}

final class AuthResolverMethods
{
    public function plain(): void {}

    #[NoNeedLogin]
    public function open(): void {}

    #[Permission(['a:b:c', 'a:b:d'])]
    public function any(): void {}

    #[Permission('demo:item:list')]
    public function plugin(): void {}
}

#[Permission('demo:item:view')]
final class AuthResolverPluginClass
{
    public function inherited(): void {}

    #[Permission('demo:item:edit')]
    public function overridden(): void {}
}

#[NoNeedLogin]
final class AuthResolverPublicClassWithGuardedMethod
{
    public function open(): void {}

    #[Permission('system:x:edit')]
    public function guarded(): void {}
}

#[Permission('demo:page:view')]
final class AuthResolverGuardedClassWithOpenMethod
{
    public function guarded(): void {}

    #[NoNeedPermission]
    public function catalog(): void {}
}

final class AuthRequirementResolverTest extends TestCase
{
    private function resolve(string $class, string $method): AuthRequirement
    {
        return (new AuthRequirementResolver())->for($class, $method);
    }

    public function test_unannotated_method_requires_login_and_permission(): void
    {
        self::assertEquals(new AuthRequirement(true, true, null), $this->resolve(AuthResolverMethods::class, 'plain'));
    }

    public function test_method_no_need_login_makes_it_public(): void
    {
        self::assertFalse($this->resolve(AuthResolverMethods::class, 'open')->login);
    }

    public function test_method_annotation_overrides_class_annotation(): void
    {
        self::assertEquals(new AuthRequirement(true, false, null), $this->resolve(AuthResolverLoginOnly::class, 'plain'));
        self::assertEquals(new AuthRequirement(true, true, 'system:admin:index'), $this->resolve(AuthResolverLoginOnly::class, 'guarded'));
        self::assertFalse($this->resolve(AuthResolverLoginOnly::class, 'open')->login);
    }

    public function test_class_annotation_is_inherited_from_parent(): void
    {
        self::assertFalse($this->resolve(AuthResolverPublicChild::class, 'index')->login);
    }

    public function test_permission_accepts_any_of_several_codes(): void
    {
        self::assertSame(['a:b:c', 'a:b:d'], $this->resolve(AuthResolverMethods::class, 'any')->code);
    }

    public function test_class_permission_is_inherited_and_method_permission_overrides(): void
    {
        self::assertSame('demo:item:list', $this->resolve(AuthResolverMethods::class, 'plugin')->code);
        self::assertSame('demo:item:view', $this->resolve(AuthResolverPluginClass::class, 'inherited')->code);
        self::assertSame('demo:item:edit', $this->resolve(AuthResolverPluginClass::class, 'overridden')->code);
    }

    public function test_class_no_need_login_never_loosens_a_method_that_names_a_permission(): void
    {
        self::assertFalse($this->resolve(AuthResolverPublicClassWithGuardedMethod::class, 'open')->login);
        self::assertEquals(new AuthRequirement(true, true, 'system:x:edit'), $this->resolve(AuthResolverPublicClassWithGuardedMethod::class, 'guarded'));
    }

    public function test_method_no_need_permission_overrides_class_permission(): void
    {
        self::assertEquals(new AuthRequirement(true, true, 'demo:page:view'), $this->resolve(AuthResolverGuardedClassWithOpenMethod::class, 'guarded'));
        self::assertEquals(new AuthRequirement(true, false, null), $this->resolve(AuthResolverGuardedClassWithOpenMethod::class, 'catalog'), '方法注解优先于类注解');
    }

    public function test_unresolvable_target_fails_closed(): void
    {
        self::assertEquals(new AuthRequirement(true, true, null), $this->resolve('Missing\\Controller', 'index'));
        self::assertEquals(new AuthRequirement(true, true, null), $this->resolve(AuthResolverMethods::class, 'missing'));
        self::assertEquals(new AuthRequirement(true, true, null), $this->resolve('', ''));
    }
}
