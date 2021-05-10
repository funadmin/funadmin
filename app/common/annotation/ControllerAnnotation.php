<?php
namespace app\common\annotation;

use Doctrine\Common\Annotations\Annotation\Attributes;
use Doctrine\Common\Annotations\Annotation\Required;
use Doctrine\Common\Annotations\Annotation\Target;
/**
 * Class ControllerAnnotation
 *
 * @Annotation
 * @Target("CLASS")
 * @Attributes({
 *     @Attribute("title", type="string"),
 * })
 */
final class ControllerAnnotation
{
    /**
     * Route group prefix for the controller
     *
     * @Required()
     *
     * @var string
     */
    public $title = '';
    /**
     * 是否开启权限控制
     * @Enum({true,false})
     * @var bool
     */
    public $auth = true;
}