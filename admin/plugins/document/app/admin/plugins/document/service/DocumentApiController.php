<?php

declare(strict_types=1);

namespace app\admin\plugins\document\service;

use app\common\controller\AdminController;
use support\Response;

/** 文档插件控制器共用的输入读取与错误映射。 */
abstract class DocumentApiController extends AdminController
{
    protected function input(): array
    {
        $json = json_decode((string) $this->request->rawBody(), true);
        return array_replace($this->request->get(), is_array($json) ? $json : $this->request->post());
    }

    protected function lockVersion(array $input): int
    {
        $raw = $input['lockVersion'] ?? null;
        if (is_int($raw) && $raw > 0) return $raw;
        if (is_string($raw) && preg_match('/\A[1-9][0-9]{0,9}\z/D', $raw)) return (int) $raw;
        throw new \InvalidArgumentException('lockVersion 无效', 422);
    }

    /** 领域异常按协议码返回；其他异常不向前端暴露内部信息。 */
    protected function action(callable $callback, string $message = '操作成功'): Response
    {
        try {
            return $this->ok($message, $callback());
        } catch (\InvalidArgumentException $e) {
            $code = in_array($e->getCode(), [403, 404, 409, 422], true) ? $e->getCode() : 422;
            return $this->fail(msg: $e->getMessage(), code: $code);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return $this->fail(msg: '标识已存在，请刷新后重试', code: 409);
        } catch (\Throwable) {
            return $this->fail(msg: '文档服务暂时不可用', code: 500);
        }
    }
}
