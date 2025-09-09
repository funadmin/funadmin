<?php
namespace fun\helper;
use Doctrine\Common\Annotations\AnnotationReader;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;
use ReflectionClass;

class CtrHelper
{

    public static $controllerList= [];
    /**
     * Undocumented function
     * @param [type] $name
     * @return array
     */
    public static function getControllersByApp($app):array
    {
        $dir = app_path($app.'/controller');
        if (!is_dir($dir)) {
            return [];
        }
        self::scanDirectory($app,$dir);
        return self::$controllerList;
    }
    /**
     * 递归扫描目录
     */
    public static function scanDirectory($app,$dir)
    {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') continue;
            
            $filePath = rtrim($dir,DS) . DS . $file;
            if (is_dir($filePath)) {
                self::scanDirectory($app,$filePath);
            } elseif (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $result = self::analyzeController($app,$filePath);
                if($result){
                    self::$controllerList[] = $result;
                }
            }
        }
    }
    /**
     * 分析单个控制器文件
     */
    public static function analyzeController($appName,$filePath)
    {
        if (file_exists($filePath)) require_once $filePath;//插件类需要加载进来
        $className = str_replace([root_path(),DS,'.php'],['','\\',''],$filePath);
        if (!class_exists($className)) {
            return null;
        }
        $reflect = new ReflectionClass($className);
        // $parentClass = $reflect->getParentClass();
        // 检查是否继承自 Backend
        if (!$reflect->isSubclassOf(\app\common\controller\Backend::class)) {
            return null;
        }
        $controllerComment = self::getControllerTitleByAnnotation($reflect);
        $reflectionMethods = $reflect->getMethods(\ReflectionMethod::IS_PUBLIC);
        $methods = [];
        foreach($reflectionMethods as $reflectionMethod){
            if ($reflectionMethod->isConstructor()){
                continue;
            }
            if(str_starts_with($reflectionMethod->getName(), '__')){
                continue;
            }
            $methodName = $reflectionMethod->getName();
            if(in_array($methodName,['enlang','verify','initialize','db','trashed','scopeOnlyTrashed','scopeWithTrashed','getDeleteTimeField','getUpdateTimeField','getCreateTimeField','getUpdateTimeField','getCreateTimeField'])){
                continue;
            }
            $methods[] = [
                'name' => $methodName,
                'comment' => self::getMethodComment($reflectionMethod),
                'parameters' => self::getMethodParameters($reflectionMethod)
            ];
        }
        $relative_path = str_replace(app_path($appName.'/controller'), '', $filePath);
        $controller_name = substr($relative_path, strrpos($relative_path, DS) + 1);
        $sub_path = substr($relative_path, 0, strrpos($relative_path, DS));
        $controller_name = str_replace('.php', '', $controller_name);
        $method_title = implode('.', array_filter([$sub_path, $controller_name]));
        $controllerComment = $controllerComment?:$method_title;
        $route_info = $method_title;
        $results = [
            'module' => $appName,
            'app_name' => $appName,
            'file_path' => $filePath,
            'class_name' => $className,
            'controller_name' => $controller_name,
            'sub_path' => $sub_path,
            // 'parent_class' => $parentClass,
            'comment' => $controllerComment,
            'route_info' => $route_info,
            'methods' => $methods,
        ];
        return $results;
    }

    /**
     * 获取方法注释
     * @param \ReflectionMethod $method
     * @return string
     */
    public static function getMethodComment(\ReflectionMethod $method): string
    {
        // 首先尝试通过注解获取方法标题
        $annotationTitle = self::getMethodTitleByAnnotation($method);
        if ($annotationTitle) {
            return $annotationTitle;
        }
        // 如果没有注解，则从文档注释中提取
        $docComment = $method->getDocComment();
        if (!$docComment) {
            return $method->getName();
        }
        
        $comment = self::getTitle($docComment);
        if($comment){
            return $comment;
        }
        // 提取注释中的描述部分
        preg_match('/\/\*\*\s*\n\s*\*\s*(.+?)\n/', $docComment, $matches);
        
        $comment = $matches[1] ?? '';
        if($comment){
            // 去除HTML标签和其他标记
            $comment = strip_tags($comment);
            // 去除多余的空白字符
            $comment = preg_replace('/\s+/', ' ', $comment);
            // 去除@标记和特殊字符
            $comment = preg_replace('/@\w+/', '', $comment);
            // 去除特殊符号，保留中英文、数字、基本符号
            $comment = preg_replace('/[^\p{L}\p{N}\s\-_()（）]/u', '', $comment);
            return trim($comment);
        }
        return $method->getName();
    }

    /**
     * 通过注解获取方法标题
     * @param \ReflectionMethod $method
     * @return string
     */
    public static function getMethodTitleByAnnotation(\ReflectionMethod $method): string
    {
        try {
            $reader = new AnnotationReader();
            $nodeAnnotation = $reader->getMethodAnnotation($method, NodeAnnotation::class);
            return !empty($nodeAnnotation) && !empty($nodeAnnotation->title) ? $nodeAnnotation->title : '';
        } catch (\Exception $e) {
            return $method->getName();
        }
    }

    public static function getTitle($doc)
    {
        $tmp = array();
        preg_match_all('/@NodeAnnotation.*?title="(.*?)"\)[\r\n|\n]/', $doc, $tmp);
        return trim($tmp[1][0] ?? "");
    }


    /**
     * 通过注解获取控制器标题
     * @param object $reflect 类名
     * @return string
     */
    public static function getControllerTitleByAnnotation(object $reflectionClass): string
    {
        // 1. 首先尝试通过注解获取
        try {
            $reader = new AnnotationReader();
            
            // 尝试获取NodeAnnotation
            $nodeAnnotation = $reader->getClassAnnotation($reflectionClass, NodeAnnotation::class);
            if (!empty($nodeAnnotation) && !empty($nodeAnnotation->title)) {
                return $nodeAnnotation->title;
            }
            
            // 尝试获取ControllerAnnotation
            $controllerAnnotation = $reader->getClassAnnotation($reflectionClass, ControllerAnnotation::class);
            if (!empty($controllerAnnotation) && !empty($controllerAnnotation->title)) {
                return $controllerAnnotation->title;
            }
        } catch (\Exception $e) {
            // 注解读取失败，继续使用其他方法
        }
        
        // 2. 从文档注释中提取
        $docComment = $reflectionClass->getDocComment();
        if (!$docComment) {
            return $reflectionClass->getShortName();
        }
        
        // 3. 尝试从NodeAnnotation中提取title
        if (preg_match('/@NodeAnnotation\s*\(\s*title\s*=\s*["\']([^"\']+)["\']/', $docComment, $matches)) {
            return trim($matches[1]);
        }
        
        // 4. 尝试从ControllerAnnotation中提取title
        if (preg_match('/@ControllerAnnotation\s*\(\s*title\s*=\s*["\']([^"\']+)["\']/', $docComment, $matches)) {
            return trim($matches[1]);
        }
        
        // 5. 提取注释中的第一行描述
        $comment = preg_replace('/\/\*\*|\*\/|\*/', '', $docComment);
        $lines = explode("\n", $comment);
        
        foreach ($lines as $line) {
            $line = trim($line);
            // 跳过空行和@标记行
            if (!empty($line) && !str_starts_with($line, '@')) {
                // 清理特殊字符
                $line = strip_tags($line);
                $line = preg_replace('/\s+/', ' ', $line);
                return trim($line);
            }
        }
        
        // 6. 如果都没有，返回类名
        return $reflectionClass->getShortName();
    }
    /**
     * 获取方法参数
     * @param \ReflectionMethod $method
     * @return array
     */
    public static function getMethodParameters(\ReflectionMethod $method): array
    {
        $parameters = [];
        foreach ($method->getParameters() as $param) {
            // 安全获取参数类型
            $type = 'mixed';
            if ($param->getType()) {
                $reflectionType = $param->getType();
                if ($reflectionType instanceof \ReflectionNamedType) {
                    $type = $reflectionType->getName();
                } elseif (method_exists($reflectionType, '__toString')) {
                    $type = (string)$reflectionType;
                }
            }
            
            $parameters[] = [
                'name' => $param->getName(),
                'type' => $type,
                'default' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
                'required' => !$param->isOptional()
            ];
        }
        return $parameters;
    }
}