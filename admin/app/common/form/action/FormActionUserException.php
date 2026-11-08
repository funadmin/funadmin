<?php

declare(strict_types=1);

namespace app\common\form\action;

use RuntimeException;

/**
 * 自定义动作处理器主动拒绝执行时抛出，消息直接展示给操作者（如「订单已关闭，不能审核」）。
 * 列表动作入口把它映射为 422 与 data.code = FORM_ACTION_USER_ERROR；其他异常文本一律不外露。
 * 消息须由处理器自行编写，不要拼接底层异常、SQL 或连接信息。
 *
 * 必须在做出任何更改之前抛出：列表动作执行链把它视为「未发生」并释放幂等占位，同一请求可原样重试；
 * 若已产生更改后再抛出，重试时该请求可能被执行两次。
 */
final class FormActionUserException extends RuntimeException
{
}
