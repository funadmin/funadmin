<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\service\PermissionMigrationPlan;
use PHPUnit\Framework\TestCase;

final class PermissionMigrationPlanTest extends TestCase
{
    private static function before(): array
    {
        return json_decode((string) file_get_contents(__DIR__ . '/fixtures/auth-requirements-before.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_coarse_plugin_code_expands_to_every_method_it_guarded(): void
    {
        $plan = PermissionMigrationPlan::expansion(self::before(), [PermissionMigrationPlan::class, 'isPluginRoute']);
        self::assertSame([
            'market:plugin:delete', 'market:plugin:delete-version', 'market:plugin:package',
            'market:plugin:publish', 'market:plugin:republish', 'market:plugin:update',
            'market:plugin:update-version', 'market:plugin:upload', 'market:plugin:withdraw',
        ], $plan['market:plugin:manage']);
    }

    public function test_method_accepting_any_of_several_codes_is_granted_to_holders_of_each(): void
    {
        $plan = PermissionMigrationPlan::expansion(self::before(), [PermissionMigrationPlan::class, 'isPluginRoute']);
        foreach (['cms:site:list', 'cms:category:list', 'cms:content:list', 'cms:menu:list', 'cms:tag:list', 'cms:model:list', 'cms:site:grant', 'cms:submission:list'] as $old) {
            self::assertContains('cms:sites:context', $plan[$old], $old);
        }
    }

    public function test_unregistered_or_public_routes_are_never_granted(): void
    {
        $plan = PermissionMigrationPlan::expansion(self::before(), static fn (): bool => true);
        foreach (array_keys($plan) as $old) {
            self::assertStringStartsNotWith('!', $old);
        }
        self::assertArrayNotHasKey('', $plan);
    }
}
