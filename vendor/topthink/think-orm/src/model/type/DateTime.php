<?php

declare (strict_types = 1);

namespace think\model\type;

use Stringable;
use think\model\contract\Modelable;
use think\model\contract\Typeable;

class DateTime implements Typeable
{
    protected $data;
    protected $value;

    public static function from(mixed $value, Modelable $model)
    {
        $static = new static();
        $static->data($value, $model->getDateFormat());
        return $static;
    }

    public function data($time, $format)
    {
        if ($format) {
            if (class_exists($format)) {
                $this->data = $time instanceof $format ? $time : new $format($time);
            } else {
                if (!is_object($time)) {
                    $this->value = is_numeric($time) ? (int) $time : strtotime($time);
                    $time  = (new \DateTime())->setTimestamp($this->value);
                }
                $this->data = $time->format($format);
            }
        } else {
            // 不做格式化输出转换
            $this->data  = $time;
        }
    }

    public function format(string $format)
    {
        if (is_object($this->data)) {
            return $this->data->format($format);
        }
        $date = new \DateTime;
        return $date->setTimestamp($this->value)->format($format);
    }

    public function value(bool $auto = true)
    {
        if ($auto && is_object($this->data) && $this->data instanceof Stringable) {
            // 对象数据写入
            return $this->data->__toString();
        }
        return $this->data;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->value();
    }
}
