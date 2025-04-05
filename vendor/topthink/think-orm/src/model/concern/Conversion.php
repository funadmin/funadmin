<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2025 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\model\concern;

use Closure;
use think\helper\Str;
use think\model\Collection;
use think\model\contract\Modelable;

/**
 * 模型数据转换处理.
 */
trait Conversion
{
    /**
     * 设置需要附加的输出属性.
     *
     * @param array $append 属性列表
     * @param bool  $merge  是否合并
     *
     * @return $this
     */
    public function append(array $append, bool $merge = false)
    {
        $this->setOption('append', $merge ? array_merge($this->getOption('append'), $append) : $append);

        return $this;
    }

    /**
     * 设置需要隐藏的输出属性.
     *
     * @param array $hidden 属性列表
     * @param bool  $merge  是否合并
     *
     * @return $this
     */
    public function hidden(array $hidden, bool $merge = false)
    {
        $this->setOption('hidden', $merge ? array_merge($this->getOption('hidden'), $hidden) : $hidden);

        return $this;
    }

    /**
     * 设置需要输出的属性.
     *
     * @param array $visible
     * @param bool  $merge   是否合并
     *
     * @return $this
     */
    public function visible(array $visible, bool $merge = false)
    {
        $this->setOption('visible', $merge ? array_merge($this->getOption('visible'), $visible) : $visible);

        return $this;
    }

    /**
     * 设置属性的映射输出.
     *
     * @param array $map
     *
     * @return $this
     */
    public function mapping(array $map)
    {
        $this->setOption('mapping', $map);

        return $this;
    }

    /**
     * 设置输出场景.
     *
     * @param string $scene
     *
     * @return $this
     */
    public function scene(string $scene)
    {
        $method = 'scene' . Str::studly($scene);
        if (method_exists($this, $method)) {
            call_user_func([$this, $method]);
        }
        return $this;
    }

    /**
     * 模型数据转数组.
     *
     * @return array
     */
    public function toArray(): array
    {
        foreach (['visible', 'hidden', 'append'] as $convert) {
            ${$convert} = $this->getOption($convert);
            foreach (${$convert} as $key => $val) {
                if (is_string($key)) {
                    $relation[$key][$convert] = $val;
                    unset(${$convert}[$key]);
                } elseif (str_contains($val, '.')) {
                    [$relName, $name]               = explode('.', $val);
                    $relation[$relName][$convert][] = $name;
                    unset(${$convert}[$key]);
                }
            }
        }
        $data  = $this->getData();
        $allow = array_diff($visible ?: array_keys($data), $hidden);

        $item = [];
        foreach ($data as $name => $val) {
            if ($val instanceof Modelable || $val instanceof Collection) {
                if (in_array($name, $hidden)) {
                    // 隐藏关联属性
                    unset($item[$name]);
                    continue;
                } 

                if (!empty($relation[$name])) {
                    // 处理关联数据输出
                    foreach ($relation[$name] as $key => $attr) {
                        $val->$key($attr);
                    }
                }
                $item[$name] = $val->toArray();
            } elseif (empty($allow) || in_array($name, $allow)) {
                // 通过获取器输出
                $item[$name] = $this->getWithAttr($name, $val, $data);
            }

            if (isset($item[$name]) && $key = $this->getWeakData('mapping', $name)) {
                // 检查字段映射
                $item[$key] = $item[$name];
                unset($item[$name]);
            }
        }

        // 输出额外属性 必须定义获取器
        foreach ($this->getOption('append') as $key => $field) {
            if (is_numeric($key)) {
                $item[$field] = $this->get($field);
            } else {
                // 追加关联属性
                $relation = $this->getRelationData($key, false);
                foreach((array) $field as $name) {
                    $item[$name] = $relation[$name];
                }
            } 
        }

        if ($this->getOption('convertNameToCamel')) {
            foreach ($item as $key => $val) {
                $name = Str::camel($key);
                if ($name !== $key) {
                    $item[$name] = $val;
                    unset($item[$key]);
                }
            }
        }

        return $item;
    }

    /**
     * 模型数据转Json.
     *
     * @param int $options json参数
     * @return string
     */
    public function tojson(int $options = JSON_UNESCAPED_UNICODE): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * 转换为数据集对象
     *
     * @param array|Collection $collection    数据集
     * @param string           $resultSetType 数据集类
     *
     * @return Collection
     */
    public function toCollection(iterable $collection = [], ?string $resultSetType = null): Collection
    {
        $resultSetType = $resultSetType ?: $this->getOption('resultSetType');

        if ($resultSetType && str_contains($resultSetType, '\\')) {
            $collection = new $resultSetType($collection);
        } else {
            $collection = new Collection($collection);
        }

        return $collection;
    }    
}
