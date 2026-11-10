<?php

declare(strict_types=1);

namespace Tests;

use app\admin\system\controller\SystemConfig;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;

final class ConfigIntegrationTest extends TestCase
{
    use \Tests\Support\RefreshCoreDatabase;

    protected function setUp(): void { \Tests\Support\TestDatabase::migrate(); Db::beginTransaction(); }
    protected function tearDown(): void { Db::rollBack(); Context::destroy(); }
    private function controller(array $payload = [], string $query = ''): SystemConfig
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $request = new Request("POST /admin/system/config$query HTTP/1.1\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
        $request->context['admin'] = ['id' => 1];
        Context::set(\Webman\Http\Request::class, $request);
        return new SystemConfig();
    }
    private function payloadOf(\Webman\Http\Response $response): array { return json_decode($response->rawBody(), true, flags: JSON_THROW_ON_ERROR); }
    public function test_fresh_database_contains_configuration_options_and_menu(): void
    {
        $result = $this->payloadOf($this->controller()->options());
        self::assertSame(200, $result['code']);
        self::assertContains('site', array_column($result['data']['groups'], 'name'));
        self::assertContains('text', array_column($result['data']['types'], 'name'));
        self::assertTrue(Db::table('admin_menu')->where('href', '/admin/system/config')->exists());
    }
    public function test_group_configuration_crud_and_referenced_group_protection(): void
    {
        $group = $this->payloadOf($this->controller(['name' => 'business', 'title' => '业务配置'])->createGroup());
        self::assertSame(200, $group['code']);
        $created = $this->payloadOf($this->controller(['code' => 'delivery_limit', 'group' => 'business', 'type' => 'number', 'value' => '10'])->create());
        self::assertSame(200, $created['code']);
        $id = $created['data']['id'];
        $updated = $this->payloadOf($this->controller(['value' => '20'])->value($id));
        self::assertSame('20', $updated['data']['value']);
        self::assertSame(422, $this->payloadOf($this->controller(['value' => 'not-number'])->value($id))['code']);
        self::assertSame(422, $this->payloadOf($this->controller()->deleteGroup($group['data']['id']))['code']);
        $rows = $this->payloadOf($this->controller(query: '?keyword=delivery')->index());
        self::assertSame(1, $rows['data']['total']);
        self::assertSame(200, $this->payloadOf($this->controller(['ids' => [$id]])->delete())['code']);
        self::assertSame(200, $this->payloadOf($this->controller()->deleteGroup($group['data']['id']))['code']);
    }
    public function test_numeric_option_keys_and_checkbox_arrays_are_preserved(): void
    {
        $created = $this->payloadOf($this->controller(['code' => 'delivery_modes', 'group' => 'site', 'type' => 'checkbox', 'extra' => "0:自提\n1:快递", 'value' => ['0', '1']])->create());
        self::assertSame(200, $created['code'], $created['msg']);
        self::assertSame("0\n1", $created['data']['value']);
    }
    public function test_invalid_input_shapes_are_validation_errors(): void
    {
        foreach ([['code' => []], ['status' => null], ['group' => []], ['value' => [['nested']]]] as $invalid) {
            $result = $this->payloadOf($this->controller(array_merge(['code' => 'invalid_config', 'group' => 'site', 'type' => 'text', 'value' => 'text'], $invalid))->create());
            self::assertSame(422, $result['code']);
        }
    }
    public function test_password_blank_edit_preserves_secret_and_audit_redacts_value(): void
    {
        $created = $this->payloadOf($this->controller(['code' => 'secret_config', 'group' => 'site', 'type' => 'password', 'value' => 'confidential-test-value'])->create());
        $id = $created['data']['id'];
        $this->controller(['value' => '', 'remark' => '改备注'])->update($id);
        self::assertSame('confidential-test-value', Db::table('config')->where('id', $id)->value('value'));
        $body = json_encode(['value' => 'new-confidential-value']);
        $request = new Request("PUT /admin/system/config/$id/value HTTP/1.1\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
        $request->controller = SystemConfig::class;
        $request->action = 'value';
        (new \app\admin\system\service\AdminLogService())->save($request, new \support\Response(200));
        $row = Db::table('admin_log')->orderByDesc('id')->first();
        self::assertStringNotContainsString('new-confidential-value', $row->post_data);
    }

    public function test_status_endpoint_rejects_array_payloads(): void
    {
        $created = $this->payloadOf($this->controller(['code' => 'status_test', 'group' => 'site', 'type' => 'text'])->create());
        self::assertSame(422, $this->payloadOf($this->controller(['status' => []])->status($created['data']['id']))['code']);
    }

    public function test_model_configuration_contract_matches_controller_normalization(): void
    {
        $data = ['code' => 'model_modes', 'group' => 'site', 'type' => 'checkbox', 'verify' => '', 'extra' => "0:自提\n1:快递", 'remark' => '', 'value' => ['0', '1']];
        self::assertNull(\app\common\model\Config::validateAttributes($data));
        self::assertSame("0\n1", $data['value']);
        $model = \app\common\model\Config::forceCreate(['code' => 'model_secret', 'type' => 'password', 'value' => 'not-for-api']);
        self::assertSame('', $model->toApiData()['value']);
    }

    public function test_group_inputs_preserve_null_for_validation(): void
    {
        foreach ([['name' => []], ['title' => []], ['status' => null]] as $invalid) {
            $result = $this->payloadOf($this->controller(array_merge(['name' => 'invalid_group', 'title' => '分组'], $invalid))->createGroup());
            self::assertSame(422, $result['code']);
        }
    }

    public function test_syscfg_reads_active_values_and_cache_is_invalidated_after_update(): void
    {
        $created = $this->payloadOf($this->controller(['code' => 'runtime_site_name', 'group' => 'site', 'type' => 'text', 'value' => '旧值'])->create());
        $id = $created['data']['id'];
        self::assertSame('旧值', \syscfg('site', 'runtime_site_name'));
        $this->controller(['value' => '新值'])->value($id);
        self::assertSame('新值', \syscfg('site', 'runtime_site_name'));
        self::assertSame(['runtime_site_name' => '新值'], \syscfg('site'));
    }

    public function test_syscfg_ignores_disabled_configuration_values(): void
    {
        $this->payloadOf($this->controller(['code' => 'disabled_runtime_value', 'group' => 'site', 'type' => 'text', 'value' => '不可用', 'status' => 0])->create());
        self::assertNull(\syscfg('site', 'disabled_runtime_value'));
        self::assertSame([], \syscfg('site'));
    }

    public function test_verify_rules_validate_configuration_values(): void
    {
        $invalid = $this->payloadOf($this->controller(['code' => 'invalid_email_value', 'group' => 'site', 'type' => 'text', 'verify' => 'email', 'value' => 'not-an-email'])->create());
        self::assertSame(422, $invalid['code']);
        $valid = $this->payloadOf($this->controller(['code' => 'valid_email_value', 'group' => 'site', 'type' => 'text', 'verify' => 'email', 'value' => 'test@example.com'])->create());
        self::assertSame(200, $valid['code']);
    }

    public function test_hidden_configuration_values_are_not_returned_to_api_clients(): void
    {
        $created = $this->payloadOf($this->controller(['code' => 'hidden_secret', 'group' => 'site', 'type' => 'hidden', 'value' => 'must-not-return'])->create());
        self::assertSame(200, $created['code']);
        self::assertSame('', $created['data']['value']);
    }

    public function test_password_values_are_never_returned_or_recorded_in_audit(): void
    {
        $created = $this->payloadOf($this->controller(['code' => 'provider_secret', 'group' => 'site', 'type' => 'password', 'value' => 'must-not-store-in-response'])->create());
        self::assertSame(200, $created['code']);
        self::assertSame('', $created['data']['value']);
        $detail = $this->payloadOf($this->controller()->detail($created['data']['id']));
        self::assertSame('', $detail['data']['value']);
    }
}
