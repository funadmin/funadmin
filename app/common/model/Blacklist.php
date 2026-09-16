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
namespace app\common\model;

use app\admin\traits\AdminDataFormat;
use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;

class Blacklist extends BaseModel {
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
     * 回收站或正常列表查询边界；筛选条件由控制器从请求传入。
     */
    public static function filteredQuery(bool $recycled, string $ip = '', $status = null)
    {
        $query = $recycled ? self::onlyTrashed() : self::order('id', 'desc');
        if ($ip !== '') {
            $query->whereLike('ip', '%' . $ip . '%');
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        return $query;
    }

    public static function validateAttributes(array $data): ?string
    {
        $ipLength = function_exists('mb_strlen') ? mb_strlen($data['ip']) : strlen($data['ip']);
        $remarkLength = function_exists('mb_strlen') ? mb_strlen($data['remark']) : strlen($data['remark']);
        if ($data['ip'] === '') {
            return 'IP/规则不能为空';
        }
        if ($ipLength > 50) {
            return 'IP/规则不能超过 50 个字符';
        }
        if ($remarkLength > 200) {
            return '备注不能超过 200 个字符';
        }
        return null;
    }

    public function toApiData(): array
    {
        return [
            'id' => (int) $this->id,
            'ip' => (string) $this->ip,
            'remark' => (string) ($this->remark ?? ''),
            'status' => (int) $this->status,
            'createdAt' => $this->formatTime($this->created_at),
            'updatedAt' => $this->formatTime($this->updated_at),
            'deletedAt' => $this->formatTime($this->deleted_at),
        ];
    }

}
