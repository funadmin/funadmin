<?php

declare (strict_types=1);
namespace app\cms\controller;

use app\BaseController;
use app\common\service\authentication\BearerTokenExtractor;
use app\common\service\member\MemberApiSessionService;
use InvalidArgumentException;
use support\Response;
/** CMS 扩展的会员接口基类：按会员 API v2 会话（access token）识别会员，并统一私有响应格式。 */
abstract class MemberEndpoint extends BaseController
{
    protected function member(): string
    {
        $token = (new BearerTokenExtractor())->extract($this->request) ?? '';
        $member = $token === '' ? null : (new MemberApiSessionService())->authenticate($token);
        $id = $member['id'] ?? null;
        if ($id === null) {
            throw new InvalidArgumentException('会员未登录或令牌已失效', 401);
        }
        return (string) $id;
    }
    protected function input(): array
    {
        return array_replace($this->request->get(), $this->request->post());
    }
    protected function action(callable $callback): Response
    {
        try {
            $response = json(['code' => 200, 'msg' => 'success', 'data' => $callback()]);
        } catch (InvalidArgumentException $e) {
            $code = in_array($e->getCode(), [401, 403, 404, 409, 422, 429], true) ? $e->getCode() : 422;
            $response = json(['code' => $code, 'msg' => $e->getMessage(), 'data' => null])->withStatus($code);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            $response = json(['code' => 409, 'msg' => '记录冲突，请刷新后重试', 'data' => null])->withStatus(409);
        } catch (\Throwable) {
            $response = json(['code' => 500, 'msg' => '投稿服务暂时不可用', 'data' => null])->withStatus(500);
        }
        return $response->withHeaders(['Cache-Control' => 'private, no-store, no-cache, must-revalidate', 'Pragma' => 'no-cache', 'Vary' => 'Authorization']);
    }
}
