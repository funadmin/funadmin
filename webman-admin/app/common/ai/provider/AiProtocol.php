<?php

declare(strict_types=1);
namespace app\common\ai\provider;

use InvalidArgumentException;

/** 只转换线协议；网络、安全、预算及备用均由共享网关负责。 */
final class AiProtocol
{
    public const VALUES = ['openai-chat', 'openai-responses', 'anthropic-messages'];

    public function __construct(public readonly string $name)
    {
        if (!in_array($name, self::VALUES, true)) throw new InvalidArgumentException('不支持的 AI protocol');
    }

    public function endpoint(): string
    {
        return match ($this->name) {
            'openai-responses' => '/responses',
            'anthropic-messages' => '/messages',
            default => '/chat/completions',
        };
    }

    public function headers(string $key): array
    {
        return $this->name === 'anthropic-messages'
            ? array_filter(['x-api-key'=>$key, 'anthropic-version'=>'2023-06-01'])
            : ($key === '' ? [] : ['Authorization'=>'Bearer ' . $key]);
    }

    public function encode(array $payload): array
    {
        foreach ($payload['messages'] as &$message) {
            $context = $message['protocol_context'] ?? null;
            if ($context === null) continue;
            if (($message['role'] ?? '') !== 'assistant' || ($context['protocol'] ?? '') !== $this->name || !is_array($context['data'] ?? null)) throw new InvalidArgumentException('协议上下文与消息不匹配');
            if ($this->name === 'openai-chat') {
                $message = array_replace($message, array_intersect_key($context['data'], array_flip(['reasoning_content','reasoning','thinking'])));
                unset($message['protocol_context']);
            }
        }
        unset($message);
        if ($this->name === 'openai-chat') return $payload;
        $anthropic = $this->name === 'anthropic-messages';
        $output = $payload['max_tokens'] ?? $payload['max_completion_tokens'] ?? null;
        if ($anthropic && $output === null) throw new InvalidArgumentException('Anthropic Messages 必须明确设置输出 Token 预算');
        if ($anthropic && isset($payload['reasoning_effort'])) throw new InvalidArgumentException('Anthropic Messages 暂不支持显式推理档位，请选择默认');
        $result = ['model'=>$payload['model'], 'stream'=>$payload['stream']];
        if (!$anthropic) {
            $result['store'] = false;
            $result['include'] = ['reasoning.encrypted_content'];
        }
        if ($output !== null) $result[$anthropic ? 'max_tokens' : 'max_output_tokens'] = $output;
        if (isset($payload['reasoning_effort'])) $result['reasoning'] = ['effort'=>$payload['reasoning_effort']];
        $items = [];
        foreach ($payload['messages'] as $message) {
            $role = $message['role'];
            if (isset($message['protocol_context'])) {
                $native = $message['protocol_context']['data'];
                if (!array_is_list($native)) throw new InvalidArgumentException('原始协议内容必须为有序列表');
                if ($anthropic) {
                    foreach ($native as &$block) {
                        if (($block['type'] ?? '') === 'tool_use') $block['input'] = (object) $block['input'];
                    }
                    unset($block);
                    $items[] = ['role'=>'assistant','content'=>$native];
                }
                else $items = array_merge($items, $native);
                continue;
            }
            if ($role === 'tool') {
                $items[] = $anthropic
                    ? ['role'=>'user','content'=>[['type'=>'tool_result','tool_use_id'=>$message['tool_call_id'],'content'=>$message['content']]]]
                    : ['type'=>'function_call_output','call_id'=>$message['tool_call_id'],'output'=>$message['content']];
                continue;
            }
            $content = $message['content'] ?? '';
            $blocks = is_array($content) ? $content : ($content === '' ? [] : [['type'=>'text','text'=>$content]]);
            $converted = [];
            foreach ($blocks as $block) {
                if ($block['type'] === 'text') {
                    $converted[] = ['type'=>$anthropic ? 'text' : ($role === 'assistant' ? 'output_text' : 'input_text'), 'text'=>$block['text']];
                } elseif ($block['type'] === 'image_url') {
                    $url = $block['image_url']['url'];
                    if (!preg_match('~^data:(image/(?:png|jpeg|webp));base64,(.+)$~s', $url, $parts)) throw new InvalidArgumentException('图片必须来自可信读取器');
                    $converted[] = $anthropic ? ['type'=>'image','source'=>['type'=>'base64','media_type'=>$parts[1],'data'=>$parts[2]]] : ['type'=>'input_image','image_url'=>$url];
                } else throw new InvalidArgumentException('不支持的消息块');
            }
            if ($anthropic && in_array($role, ['system','developer'], true)) {
                $result['system'] = array_merge($result['system'] ?? [], $converted);
                continue;
            }
            foreach ($message['tool_calls'] ?? [] as $call) {
                $function = $call['function'];
                if ($anthropic) $converted[] = ['type'=>'tool_use','id'=>$call['id'],'name'=>$function['name'],'input'=>json_decode($function['arguments'], false, 512, JSON_THROW_ON_ERROR)];
            }
            if ($converted !== []) $items[] = ['role'=>$role,'content'=>$converted];
            if (!$anthropic) foreach ($message['tool_calls'] ?? [] as $call) $items[] = ['type'=>'function_call','call_id'=>$call['id'],'name'=>$call['function']['name'],'arguments'=>$call['function']['arguments']];
        }
        // 并行工具结果必须属于同一个 user 回合，且保持原顺序。
        if ($anthropic) {
            $merged = [];
            foreach ($items as $item) {
                $last = count($merged) - 1;
                if ($last >= 0 && $merged[$last]['role'] === $item['role']) $merged[$last]['content'] = array_merge($merged[$last]['content'], $item['content']);
                else $merged[] = $item;
            }
            $items = $merged;
        }
        $result[$anthropic ? 'messages' : 'input'] = $items;
        foreach ($payload['tools'] ?? [] as $tool) {
            if (($tool['type'] ?? '') !== 'function') throw new InvalidArgumentException('只支持 function 工具');
            $f = $tool['function'];
            $result['tools'][] = $anthropic
                ? ['name'=>$f['name'],'description'=>$f['description'] ?? '', 'input_schema'=>$f['parameters']]
                : ['type'=>'function','name'=>$f['name'],'description'=>$f['description'] ?? '', 'parameters'=>$f['parameters'],'strict'=>false];
        }
        return $result;
    }

    /** 流状态由单次请求持有，绝不跨任务复用。 */
    public function streamEvent(array $data, array &$state, array &$calls): array
    {
        $type = $data['type'] ?? '';
        if (($state['done'] ?? false) || !is_string($type)) throw new AiProviderException('invalid_response', '完成后收到额外事件或事件类型无效');
        if ($this->name === 'openai-responses' && !str_starts_with($type, 'response.') && $type !== 'error') throw new AiProviderException('invalid_response', 'Responses 事件协议不匹配');
        if ($this->name === 'anthropic-messages' && !in_array($type, ['message_start','message_delta','message_stop','content_block_start','content_block_delta','content_block_stop','ping','error'], true)) throw new AiProviderException('invalid_response', 'Messages 事件协议不匹配');
        if (isset($data['error']) || in_array($type, ['error','response.failed','response.incomplete'], true)) throw new AiProviderException('invalid_response', 'Provider 流式协议失败');
        if (str_contains($type, 'refusal')) throw new AiProviderException('safety_refusal', '模型安全拒绝');
        $out = [];
        // 推理事件只参与私有快照，不作为可展示增量输出。
        if ($this->name === 'anthropic-messages') {
            if ($type === 'ping') return [];
            if ($type === 'message_start') {
                if (isset($state['started'])) throw new AiProviderException('invalid_response', '重复 message_start');
                $state['started'] = true;
            } elseif (!isset($state['started'])) throw new AiProviderException('invalid_response', '缺少 message_start');
            if ($type === 'content_block_start') {
                $index = $data['index'];
                if (isset($state['blocks'][$index]) || ($state['stop'] ?? false)) throw new AiProviderException('invalid_response', '内容块重复或结束后开始');
                $state['blocks'][$index] = $data['content_block']['type'];
                $state['native'][$index] = $data['content_block'];
            }
            if (in_array($type, ['content_block_delta','content_block_stop'], true)) {
                $index = $data['index'];
                if (!isset($state['blocks'][$index]) || isset($state['closed'][$index])) throw new AiProviderException('invalid_response', '内容块顺序无效');
                if ($type === 'content_block_stop') $state['closed'][$index] = true;
                elseif (!in_array($data['delta']['type'] ?? '', match ($state['blocks'][$index]) {
                    'text' => ['text_delta','citations_delta'],
                    'thinking' => ['thinking_delta','signature_delta'],
                    'tool_use' => ['input_json_delta'],
                    default => [],
                }, true)) throw new AiProviderException('invalid_response', '内容块增量类型不匹配');
                if ($type === 'content_block_stop' && $state['blocks'][$index] === 'tool_use' && isset($state['json'][$index])) {
                    $state['native'][$index]['input'] = json_decode($state['json'][$index], true, 512, JSON_THROW_ON_ERROR);
                }
            }
            if (in_array($type, ['message_delta','message_stop'], true) && count($state['blocks'] ?? []) !== count($state['closed'] ?? [])) throw new AiProviderException('invalid_response', '存在未结束内容块');
            if ($type === 'message_stop' && !($state['stop'] ?? false)) throw new AiProviderException('invalid_response', '缺少停止原因');
        }
        if ($this->name === 'openai-responses') {
            $allowed = ['response.created','response.in_progress','response.output_item.added','response.output_item.done','response.content_part.added','response.content_part.done','response.output_text.delta','response.output_text.done','response.function_call_arguments.delta','response.function_call_arguments.done','response.completed','response.reasoning_summary_part.added','response.reasoning_summary_part.done','response.reasoning_summary_text.delta','response.reasoning_summary_text.done','response.reasoning_text.delta','response.reasoning_text.done'];
            if (!in_array($type, $allowed, true)) throw new AiProviderException('unsupported_capability', '不支持的 Responses 流事件，禁止静默丢弃');
            if ($type === 'response.created') {
                if (isset($state['started'])) throw new AiProviderException('invalid_response', '重复 response.created');
                $state['started'] = true;
            } elseif (!isset($state['started'])) throw new AiProviderException('invalid_response', '缺少 response.created');
            if ($type === 'response.output_item.added') {
                $index = $data['output_index'];
                if (isset($state['items'][$index])) throw new AiProviderException('invalid_response', '重复输出项');
                if (!in_array($data['item']['type'] ?? '', ['message','function_call','reasoning'], true)) throw new AiProviderException('unsupported_capability', '不支持的输出项');
                $state['items'][$index] = $data['item']['type'];
            }
            if (in_array($type, ['response.output_text.delta','response.function_call_arguments.delta','response.output_item.done'], true)) {
                $index = $data['output_index'];
                if (!isset($state['items'][$index]) || isset($state['closed'][$index])) throw new AiProviderException('invalid_response', '输出项顺序无效');
                if ($type === 'response.output_item.done') $state['closed'][$index] = true;
                elseif ($state['items'][$index] !== ($type === 'response.output_text.delta' ? 'message' : 'function_call')) throw new AiProviderException('invalid_response', '输出增量类型不匹配');
            }
            if (str_starts_with($type, 'response.reasoning_')) {
                $index = $data['output_index'] ?? -1;
                if (($state['items'][$index] ?? '') !== 'reasoning' || isset($state['closed'][$index])) throw new AiProviderException('invalid_response', '推理增量顺序无效');
            }
            if ($type === 'response.output_item.done' && isset($data['item'])) $state['native'][$data['output_index']] = $data['item'];
            if ($type === 'response.completed' && count($state['items'] ?? []) !== count($state['closed'] ?? [])) throw new AiProviderException('invalid_response', '存在未完成输出项');
        }
        if ($type === 'response.output_text.delta') {
            $state['text'] = ($state['text'] ?? '') . $data['delta'];
            $out[] = ['type'=>'content','content'=>$data['delta']];
        }
        if ($type === 'response.output_item.added' && ($data['item']['type'] ?? '') === 'function_call') {
            $item = $data['item'];
            if (isset($calls[$data['output_index']])) throw new AiProviderException('invalid_response', '工具起始事件重复');
            $calls[$data['output_index']] = ['id'=>$item['call_id'],'name'=>$item['name'],'arguments'=>$item['arguments'] ?? ''];
        }
        if ($type === 'response.function_call_arguments.delta') {
            if (!isset($calls[$data['output_index']])) throw new AiProviderException('invalid_response', '工具增量缺少起始事件');
            $calls[$data['output_index']]['arguments'] .= $data['delta'];
        }
        if ($type === 'response.completed') {
            if (isset($data['response']['output'])) {
                $final = $this->decode($data['response']);
                $state['protocol_context'] = $final['choices'][0]['message']['protocol_context'];
                if ($final['choices'][0]['message']['content'] !== ($state['text'] ?? '')) throw new AiProviderException('invalid_response', '最终文本与流不一致');
                $finalCalls = $final['choices'][0]['message']['tool_calls'];
                $streamCalls = array_values($calls);
                if (count($finalCalls) !== count($streamCalls)) throw new AiProviderException('invalid_response', '最终工具列表与流不一致');
                foreach ($finalCalls as $i => $call) {
                    if ($call['id'] !== $streamCalls[$i]['id'] || $call['function']['name'] !== $streamCalls[$i]['name'] || $call['function']['arguments'] !== $streamCalls[$i]['arguments']) throw new AiProviderException('invalid_response', '最终工具参数与流不一致');
                }
            }
            if (($data['response']['status'] ?? '') !== 'completed') throw new AiProviderException('invalid_response', 'Responses 流未完成');
            if (!isset($state['protocol_context'])) {
                if (count($state['native'] ?? []) !== count($state['items'] ?? [])) throw new AiProviderException('invalid_response', '缺少完整输出快照');
                $state['native'] ??= [];
                ksort($state['native']);
                $state['protocol_context'] = ['protocol'=>$this->name,'data'=>array_values($state['native'])];
            }
            $state['done'] = true;
            $state['usage'] = $data['response']['usage'] ?? [];
        }
        if ($type === 'message_start') $state['usage'] = $data['message']['usage'] ?? [];
        if ($type === 'content_block_start') {
            $block = $data['content_block'];
            if ($block['type'] === 'tool_use') $calls[$data['index']] = ['id'=>$block['id'],'name'=>$block['name'],'arguments'=>''];
            elseif (!in_array($block['type'], ['text','thinking','redacted_thinking'], true)) throw new AiProviderException('invalid_response', '未知 Messages 流内容块');
            elseif (($block['text'] ?? '') !== '') $out[] = ['type'=>'content','content'=>$block['text']];
        }
        if ($type === 'content_block_delta') {
            $delta = $data['delta'];
            $index = $data['index'];
            if ($delta['type'] === 'text_delta') {
                $state['native'][$index]['text'] .= $delta['text'];
                $out[] = ['type'=>'content','content'=>$delta['text']];
            }
            elseif ($delta['type'] === 'thinking_delta') $state['native'][$index]['thinking'] .= $delta['thinking'];
            elseif ($delta['type'] === 'signature_delta') $state['native'][$index]['signature'] = $delta['signature'];
            elseif ($delta['type'] === 'citations_delta') $state['native'][$index]['citations'][] = $delta['citation'];
            elseif ($delta['type'] === 'input_json_delta') {
                if (!isset($calls[$data['index']])) throw new AiProviderException('invalid_response', '工具增量缺少起始事件');
                $calls[$data['index']]['arguments'] .= $delta['partial_json'];
                $state['json'][$index] = ($state['json'][$index] ?? '') . $delta['partial_json'];
            } else throw new AiProviderException('invalid_response', '未知 Messages 流增量');
        }
        if ($type === 'message_delta') {
            $reason = $data['delta']['stop_reason'] ?? null;
            if (!in_array($reason, ['end_turn','tool_use','stop_sequence'], true)) throw new AiProviderException('invalid_response', 'Messages 流未完整完成');
            if (($reason === 'tool_use') !== ($calls !== [])) throw new AiProviderException('invalid_response', '停止原因与工具调用不一致');
            if (isset($state['stop_reason']) && $state['stop_reason'] !== $reason) throw new AiProviderException('invalid_response', '停止原因不一致');
            $state['stop_reason'] = $reason;
            $state['stop'] = true;
            $state['usage'] = array_replace($state['usage'] ?? [], $data['usage'] ?? []);
        }
        if ($type === 'message_stop') {
            $state['done'] = $state['stop'] ?? false;
            $native = $state['native'] ?? [];
            ksort($native);
            $state['protocol_context'] = ['protocol'=>$this->name,'data'=>array_values($native)];
        }
        if (in_array($type, ['response.completed','message_delta'], true)) {
            $u = $state['usage'] ?? [];
            $input = ($u['input_tokens'] ?? 0) + ($u['cache_creation_input_tokens'] ?? 0) + ($u['cache_read_input_tokens'] ?? 0);
            $output = $u['output_tokens'] ?? 0;
            $out[] = ['type'=>'usage','usage'=>['inputTokens'=>$input,'outputTokens'=>$output,'totalTokens'=>$input + $output]];
        }
        return $out;
    }

    /** 将协议结果转换为已有内部 Chat 结构，避免改变任务消息和工具审批契约。 */
    public function decode(array $body): array
    {
        if (isset($body['error'])) throw new AiProviderException('invalid_request', 'Provider 返回协议错误');
        if ($this->name === 'openai-chat') {
            $message = $body['choices'][0]['message'] ?? [];
            $private = array_intersect_key($message, array_flip(['reasoning_content','reasoning','thinking']));
            if ($private !== []) $body['choices'][0]['message']['protocol_context'] = ['protocol'=>$this->name,'data'=>$private];
            return $body;
        }
        $anthropic = $this->name === 'anthropic-messages';
        if (!$anthropic && ($body['status'] ?? '') !== 'completed') throw new AiProviderException('invalid_response', 'Responses 未完整完成');
        $blocks = $anthropic ? ($body['content'] ?? null) : ($body['output'] ?? null);
        if (!is_array($blocks) || !array_is_list($blocks)) throw new AiProviderException('invalid_response', '协议响应缺少内容列表');
        $text = '';
        $calls = [];
        foreach ($blocks as $block) {
            $type = $block['type'] ?? '';
            if (($anthropic && in_array($type, ['thinking','redacted_thinking'], true)) || (!$anthropic && $type === 'reasoning')) continue;
            if ($type === 'text' && $anthropic) $text .= $block['text'];
            elseif ($type === 'message' && !$anthropic) {
                foreach ($block['content'] ?? [] as $part) {
                    if (($part['type'] ?? '') === 'refusal') throw new AiProviderException('safety_refusal', '模型安全拒绝');
                    if (($part['type'] ?? '') !== 'output_text') throw new AiProviderException('invalid_response', '未知 Responses 内容块');
                    $text .= $part['text'];
                }
            } elseif (($type === 'tool_use' && $anthropic) || ($type === 'function_call' && !$anthropic)) {
                $calls[] = ['id'=>$block[$anthropic ? 'id' : 'call_id'] ?? '', 'function'=>['name'=>$block['name'] ?? '', 'arguments'=>$anthropic ? json_encode((object) $block['input'], JSON_THROW_ON_ERROR) : ($block['arguments'] ?? '')]];
            } else throw new AiProviderException('invalid_response', '未知协议输出块，禁止静默丢弃');
        }
        $reason = $anthropic ? ($body['stop_reason'] ?? '') : ($calls ? 'tool_calls' : 'stop');
        if (in_array($reason, ['refusal','safety'], true)) throw new AiProviderException('safety_refusal', '模型安全拒绝');
        if ($anthropic && !in_array($reason, ['end_turn','stop_sequence','tool_use'], true)) throw new AiProviderException('invalid_response', 'Messages 未完整完成');
        if ($anthropic && (($reason === 'tool_use') !== ($calls !== []))) throw new AiProviderException('invalid_response', '停止原因与工具调用不一致');
        $usage = $body['usage'] ?? [];
        $input = ($usage['input_tokens'] ?? 0) + ($anthropic ? ($usage['cache_creation_input_tokens'] ?? 0) + ($usage['cache_read_input_tokens'] ?? 0) : 0);
        $output = $usage['output_tokens'] ?? 0;
        return array_replace($body, ['choices'=>[['message'=>['content'=>$text,'tool_calls'=>$calls,'protocol_context'=>['protocol'=>$this->name,'data'=>$blocks]],'finish_reason'=>$calls ? 'tool_calls' : 'stop']], 'usage'=>['prompt_tokens'=>$input,'completion_tokens'=>$output,'total_tokens'=>$input + $output]]);
    }
}
