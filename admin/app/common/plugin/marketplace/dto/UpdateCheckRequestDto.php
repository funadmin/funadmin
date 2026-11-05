<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace\dto;

use InvalidArgumentException;

/**
 * 插件市场批量更新检查请求。
 */
final class UpdateCheckRequestDto
{
    /** @param list<array{code:string, code_version:string, db_version:string, modified:bool}> $installed */
    public function __construct(public readonly array $installed)
    {
        if (!array_is_list($installed)) {
            throw new InvalidArgumentException('installed 必须是列表');
        }
        foreach ($installed as $item) {
            if (!is_array($item) || array_keys($item) !== ['code', 'code_version', 'db_version', 'modified']) {
                throw new InvalidArgumentException('installed item 必须严格包含 code、code_version、db_version、modified');
            }
            if (!is_string($item['code']) || !is_string($item['code_version']) || !is_string($item['db_version']) || !is_bool($item['modified'])) {
                throw new InvalidArgumentException('installed item 字段类型无效');
            }
            MarketplaceDtoValidator::pluginCode($item['code']);
            MarketplaceDtoValidator::version($item['code_version']);
            MarketplaceDtoValidator::databaseCapability($item['db_version']);
        }
    }
}
