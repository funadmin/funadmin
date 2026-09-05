<?php

declare(strict_types=1);

namespace app\backend\model;

/**
 * CRUD 生成审计记录，不包含确认 token 或数据库凭据。
 */
final class CrudGeneration extends BackendModel
{
    protected $name = 'crud_generation';

    protected $json = ['definition', 'manifest', 'error'];

    protected $jsonAssoc = true;
}
