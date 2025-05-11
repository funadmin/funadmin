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

namespace think;

use ArrayAccess;
use InvalidArgumentException;
use JsonSerializable;
use ReflectionClass;
use think\contract\Arrayable;
use think\contract\Jsonable;
use think\model\contract\Modelable;
use WeakMap;

/**
 * Class Entity.
 * @mixin Model
 */
abstract class Entity implements JsonSerializable, ArrayAccess, Arrayable, Jsonable, Modelable
{
    private static ?WeakMap $weakMap = null;

    /**
     * 架构函数.
     *
     * @param Model $model 模型连接对象
     */
    public function __construct(?Model $model = null)
    {
        if (!self::$weakMap) {
            self::$weakMap = new WeakMap;
        }

        // 获取实体模型参数
        $options = $this->getOptions();

        if (is_null($model)) {
            $class = !empty($options['modelClass']) ? $options['modelClass'] : str_replace('\\entity\\', '\\model\\', static::class);
            $model = new $class();
            $model->entity($this);
        }

        self::$weakMap[$this] = [
            'model' =>  $model,
        ];

        // 初始化模型
        $this->setOptions($options);
        $this->init($options);
    }

    /**
     * 在实体模型中定义 返回相关配置参数.
     *
     * @return array
     */
    protected function getOptions(): array
    {
        return [];
    }

    /**
     * 批量设置模型参数
     * @param array  $options  值
     * @return void
     */
    public function setOptions(array $options): void
    {
        foreach ($options as $name => $value) {
            $this->setOption($name, $value);
        }
    }

    /**
     * 设置模型参数
     *
     * @param string $name  参数名
     * @param mixed  $value  值
     *
     * @return $this
     */
    public function setOption(string $name, $value)
    {
        self::$weakMap[$this][$name] = $value;
        return $this;
    }

    /**
     * 获取模型参数
     *
     * @param string $name  参数名
     * @param mixed  $default  默认值
     *
     * @return mixed
     */
    public function getOption(string $name, $default = null)
    {
        return self::$weakMap[$this][$name] ?? $default;
    }

    /**
     * 创建新的实例.
     *
     * @param Model $model 模型连接对象
     * @param bool  $with  是否存在with关联查询
     */
    public function newInstance(?Model $model, bool $with = false)
    {
        return new static($model, $with);
    }

    /**
     *  初始化模型.
     *
     * @param array $options 模型参数
     * @return void
     */
    protected function init(array $options = []): void {}

    /**
     * 获取模型对象实例.
     * @return Model
     */
    public function model()
    {
        return self::$weakMap[$this]['model'];
    }

    /**
     *  设置模型.
     *
     * @param Model $model 模型对象
     * @return void
     */
    public function setModel(Model $model)
    {
        self::$weakMap[$this]['model'] = $model;
    }

    /**
     * 获取克隆的模型实例.
     *
     * @return static
     */
    public function clone()
    {
        $model = new static();
        self::$weakMap[$model] = self::$weakMap[$this];
        return $model;
    }

    /**
     * 克隆模型实例
     * 
     * @return void
     */
    public function __clone()
    {
        throw new InvalidArgumentException('use $modelObj->clone() replace clone $modelObj');
    }

    /**
     * 序列化模型对象
     * 
     * @return array
     */
    public function __serialize(): array
    {
        return array_diff_key(self::$weakMap[$this]);
    }

    /**
     * 反序列化模型对象
     * 
     * @param array $data 
     * @return void
     */
    public function __unserialize(array $data) 
    {
        self::$weakMap[$this] = $data;
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
        return $this->model()->get($name);
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
        $this->model()->set($name, $value);
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
        return $this->model()->__isset($name);
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
        $this->model()->__unset($name);
    }

    public function __toString()
    {
        return $this->model()->toJson();
    }

    public function __debugInfo()
    {
        return $this->model()->getData();
    }

    // JsonSerializable
    public function jsonSerialize(): array
    {
        return $this->model()->toArray();
    }

    /**
     * 模型数据转数组.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->model()->toArray();
    }   
     
    /**
     * 模型数据转Json.
     *
     * @param int $options json参数
     * @return string
     */
    public function toJson(int $options = JSON_UNESCAPED_UNICODE): string
    {
        return $this->model()->toJson($options);
    }

    // ArrayAccess
    public function offsetSet(mixed $name, mixed $value): void
    {
        $this->__set($name, $value);
    }

    public function offsetGet(mixed $name): mixed
    {
        return $this->__get($name);
    }

    public function offsetExists(mixed $name): bool
    {
        return $this->__isset($name);
    }

    public function offsetUnset(mixed $name): void
    {
        $this->__unset($name);
    }

    public static function __callStatic($method, $args)
    {
        $entity = new static();
        if (in_array($method, ['destroy', 'create', 'update', 'saveAll'])) {
            // 调用model的静态方法
            $db = $entity->model();
        } else {
            // 调用Query类查询方法
            $db = $entity->model()->db();
        }

        if ('with' != $method && !empty(self::$weakMap[$entity]['autoMapping'])) {
            // 自动关联查询
            $db->with(self::$weakMap[$entity]['autoMapping']);
        }

        return call_user_func_array([$db, $method], $args);
    }

    public function __call($method, $args)
    {
        // 调用Model类方法
        $result = call_user_func_array([$this->model(), $method], $args);
        return $result instanceof Model ? $this : $result;
    }
}
