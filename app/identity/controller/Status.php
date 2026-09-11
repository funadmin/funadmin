<?php

declare(strict_types=1);

namespace app\identity\controller;

use app\identity\service\IssuerService;
use think\response\Json;

/**
 * 身份应用 Phase 0 状态入口。
 */
final class Status
{
    /**
     * 返回已建立的协议边界，不宣告尚未启用的授权能力。
     */
    public function index(): Json
    {
        $issuer = (new IssuerService((string) config('identity.issuer', '')))->getIssuer();

        return json([
            'code' => 200,
            'msg' => 'identity boundary ready',
            'data' => [
                'issuer' => $issuer,
                'phase' => 0,
                'capabilities' => [],
            ],
            'time' => time(),
        ]);
    }
}