<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */
namespace app\admin\model;
use app\admin\authorization\service\DataScopeService;
use app\admin\traits\AdminDataFormat;
use app\common\model\concern\LaravelSoftDelete;

class AdminLog extends BackendModel
{

    /**
     * @var bool
     */
    use LaravelSoftDelete;
    use AdminDataFormat;


    


    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

    /**
     * 审计日志查询边界：非超级管理员只能看到数据范围内管理员的日志。
     */
    public static function scopedQuery()
    {
        $scope = (new DataScopeService())->resolve();
        if ($scope['all']) {
            return self::where('id', '>', 0);
        }
        return self::whereIn('admin_id', (new DataScopeService())->visibleAdminIds() ?: [0]);
    }

    public function toApiData(bool $detail = false): array
    {
        $data = [
            'id' => (int) $this->id,
            'username' => (string) $this->username,
            'appName' => (string) $this->app_name,
            'sourceType' => (string) $this->source_type,
            'sourceName' => (string) $this->source_name,
            'controller' => (string) $this->controller,
            'action' => (string) $this->action,
            'name' => (string) $this->name,
            'method' => strtoupper((string) $this->method),
            'url' => (string) $this->url,
            'ip' => (string) $this->ip,
            'status' => (int) $this->status,
            'responseCode' => (int) $this->response_code,
            'durationMs' => (int) $this->duration_ms,
            'requestId' => (string) $this->request_id,
            'createdAt' => $this->formatTime($this->created_at),
        ];
        if ($detail) {
            $data['getData'] = (string) $this->get_data;
            $data['postData'] = (string) $this->post_data;
            $data['agent'] = (string) $this->agent;
            $data['errorMessage'] = (string) $this->error_message;
        }
        return $data;
    }
}