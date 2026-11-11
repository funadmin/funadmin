<?php
declare(strict_types=1);
namespace app\cms\service;
use InvalidArgumentException;
final class Input
{
    public static function id(mixed $id): string { $id=(string)$id; if(!preg_match('/^[1-9][0-9]{0,19}$/D',$id)||(strlen($id)===20&&strcmp($id,'18446744073709551615')>0)) throw new InvalidArgumentException('ID 无效'); return $id; }
    public static function text(mixed $value,int $max=255): string { if(!is_scalar($value) && $value!==null) throw new InvalidArgumentException('文本格式无效'); $v=trim((string)$value); if(mb_strlen($v)>$max) throw new InvalidArgumentException('文本过长'); return $v; }
    public static function code(mixed $value): string { $v=self::text($value,64); if(!preg_match('/^[a-z][a-z0-9_-]*$/D',$v)) throw new InvalidArgumentException('标识无效'); return $v; }
    public static function locale(mixed $value): string { $v=strtolower(self::text($value,20)); if(!preg_match('/^[a-z]{2}(?:-[a-z0-9]{2,8})*$/D',$v)) throw new InvalidArgumentException('语言代码无效'); return $v; }
    public static function json(mixed $value): string { if(!is_array($value)) throw new InvalidArgumentException('配置必须是对象或数组'); return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
    public static function now(): string { return gmdate('Y-m-d H:i:s'); }
    /**
     * 取出一行的原始列值：Eloquent 模型用 getAttributes()（直接 (array) 会得到受保护属性），其他输入按 (array) 转换。
     * 软删除的内部列 deleted_token 不对外输出（模型与查询构造器的结果一致）。
     */
    public static function attributes(object|array|null $row): ?array
    {
        if($row===null) return null;
        $row=$row instanceof \Illuminate\Database\Eloquent\Model ? $row->getAttributes() : (array)$row;
        unset($row['deleted_token']);
        return $row;
    }
    public static function row(object|array|null $row): ?array
    {
        $row=self::attributes($row); if($row===null) return null;
        foreach($row as $key=>&$value) {
            if(($key==='id'||str_ends_with($key,'_id'))&&$value!==null) $value=(string)$value;
            if(in_array($key,['settings','field_schema','data','parameters'],true) && is_string($value)) $value=json_decode($value,true,512,JSON_THROW_ON_ERROR);
        } unset($value); return $row;
    }
    public static function rows(iterable $rows): array { $result=[]; foreach($rows as $row) $result[]=self::row($row); return $result; }
    public static function template(mixed $value,string $default='detail'): string { $v=self::text($value??$default,128); if(!preg_match('~^[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*$~D',$v)) throw new InvalidArgumentException('模板标识无效'); return $v; }
}
