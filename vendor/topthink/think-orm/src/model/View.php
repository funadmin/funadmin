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

namespace think\model;

use ReflectionClass;
use think\Entity;
use think\Model;

/**
 * 视图模型
 */
abstract class View extends Entity
{
    /**
     * 架构函数.
     *
     * @param Model $model 模型连接对象
     */
    public function __construct(?Model $model = null)
    {
        parent::__construct($model);

        // 设置为视图模型
        $this->model()->asView(true);

        // 初始化模型
        if (!$this->model()->isEmpty()) {
            $this->initData();
        }
    }

    /**
     * 设置Model对象并初始化数据
     *
     * @param Model $model
     *
     * @return $this
     */
    public function setModel(Model $model)
    {
        parent::setModel($model);
        $this->initData();
        return $this;
    }

    /**
     * 初始化实体数据属性.
     *
     * @return void
     */
    private function initData()
    {
        // 获取实体属性
        $properties = $this->getEntityProperties();
        foreach ($properties as $key => $field) {
            if (is_int($key)) {
                $this->$field = $this->model()->$field;
            } elseif (strpos($field, '->')) {
                $items    = explode('->', $field);
                $relation = array_shift($items);
                if (isset($data->$relation)) {
                    // 存在关联数据
                    $value    = $this->model()->$relation;
                    foreach ($items as $item) {
                        $value = $value->$item;
                    }
                    $this->$key = $value;
                } else {
                    $this->$key = $this->model()->$key;
                }
            } else {
                $this->$key = $this->model()->$field;
            }
        }
    }

    /**
     * 获取实体属性列表.
     *
     * @return array
     */
    private function getEntityProperties(): array
    {
        $reflection = new ReflectionClass($this);
        $options    = $this->getOptions();
        $mapping    = $options['property_mapping'] ?? [];
        $properties = [];

        foreach ($reflection->getProperties() as $property) {
            $field = $property->getName();
            if (isset($mapping[$field])) {
                $properties[$field] = $mapping[$field];
            } else {
                $properties[] = $field;
            }
        }

        return $properties;
    }

    /**
     * 转换为数组. 视图模型不支持 hidden visible append
     *
     * @return array
     */
    public function toArray(): array
    {
        return get_object_vars($this);
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

    // JsonSerializable
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * 设置关联数据.
     *
     * @param string $relation 关联属性
     * @param Model  $model  关联数据
     *
     * @return void
     */
    public function setRelation($relation, $model)
    {
        $properties = $this->getEntityProperties();
        foreach ($properties as $key => $field) {
            if (strpos($field, '->') && str_starts_with($field, $relation)) {
                // 关联映射属性
                $items  = explode('->', $field);
                array_shift($items);

                $value  = $model;
                foreach ($items as $item) {
                    $value = $value->$item;
                }
                $this->$key = $value;
            }
        }
    }

    /**
     * 设置关联绑定数据
     *
     * @param Model  $model 关联对象
     * @param array  $bind  绑定属性
     * @param string $relation  关联名称
     * @return void
     */
    public function bindRelationAttr($model, $bind, $relation) 
    {
        if ($relation) {
            $this->setRelation($relation, $model);
        }
    }

    /**
     * 获取属性 支持获取器
     *
     * @param string $name 名称
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        if (property_exists($this, $name)) {
            return $this->$name ?? null;
        }
        return $this->model()->$name;
    }

    /**
     * 设置数据 支持类型自动转换
     *
     * @param string $name  名称
     * @param mixed  $value 值
     *
     * @return void
     */
    public function __set(string $name, $value): void
    {
        if (property_exists($this, $name)) {
            $this->$name = $value;
        }
    }

    /**
     * 检测数据对象的值
     *
     * @param string $name 名称
     *
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return isset($this->$name);
    }

    /**
     * 销毁数据对象的值
     *
     * @param string $name 名称
     *
     * @return void
     */
    public function __unset(string $name): void
    {
        __unset($this->$name);
    }

    public function __debugInfo()
    {
        return [];
    }
}
