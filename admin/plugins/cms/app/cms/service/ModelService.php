<?php
declare(strict_types=1);
namespace app\cms\service;
use app\cms\model\{ContentModel,ModelVersion};
use app\common\model\Attach;
use support\Db;
use InvalidArgumentException;
final class ModelService
{
    public static function seed(): void
    {
        $definitions=['article'=>['文章',[['name'=>'source','label'=>'来源','type'=>'text']]],'page'=>['单页',[]],'product'=>['产品',[['name'=>'sku','label'=>'型号','type'=>'text'],['name'=>'parameters','label'=>'参数','type'=>'textarea'],['name'=>'gallery','label'=>'图库','type'=>'gallery']]],'case'=>['案例',[['name'=>'client','label'=>'客户','type'=>'text'],['name'=>'industry','label'=>'行业','type'=>'text'],['name'=>'project_date','label'=>'项目日期','type'=>'text']]],'download'=>['下载',[['name'=>'file','label'=>'附件','type'=>'file'],['name'=>'version','label'=>'版本号','type'=>'text']]]];
        foreach($definitions as $code=>[$name,$fields]) if(!ContentModel::query()->where('code',$code)->exists()) (new self())->save(compact('code','name','fields'));
    }
    public function listing(): array
    {
        $rows=Input::rows(ContentModel::query()->orderBy('id')->get());
        foreach($rows as &$row) $row['schema']=Input::row(ModelVersion::query()->where('model_id',$row['id'])->where('version',$row['current_version'])->first());
        return $rows;
    }
    public function save(array $input): array
    {
        $fields=$input['fields']??[];if(!is_array($fields)||count($fields)>64) throw new InvalidArgumentException('模型字段配置无效');$names=[];
        foreach($fields as &$field) {
            if(!is_array($field)) throw new InvalidArgumentException('字段定义无效');
            $field['name']=Input::code($field['name']??'');
            if(in_array($field['name'],$names,true)||in_array($field['name'],['id','site_id','locale'],true)) throw new InvalidArgumentException('字段名重复或保留');$names[]=$field['name'];
            if(!in_array($field['type']??'', ['text','textarea','number','boolean','select','image','file','gallery'],true)) throw new InvalidArgumentException('字段类型无效');
            $field['label']=Input::text($field['label']??$field['name'],100);
            $field=$this->definition($field);
        }unset($field);
        return Db::transaction(function()use($input,$fields): array {
            $old=isset($input['id'])?Input::row(ContentModel::query()->where('id',Input::id($input['id']))->lockForUpdate()->first()):null;
            if(isset($input['id'])&&!$old) throw new InvalidArgumentException('模型不存在');
            $code=Input::code($input['code']??$old['code']??'');if($old&&$code!==$old['code']) throw new InvalidArgumentException('模型标识不可修改');
            $version=$old?(int)$old['current_version']+1:1;$now=Input::now();
            $data=['code'=>$code,'name'=>Input::text($input['name']??$code,100),'current_version'=>$version,'status'=>(int)($input['status']??1),'updated_at'=>$now];
            if($old) { $id=$old['id'];ContentModel::query()->where('id',$id)->update($data); }
            else { $row=new ContentModel();$row->fill($data+['created_at'=>$now])->save();$id=(string)$row->getKey(); }
            ModelVersion::query()->insert(['model_id'=>$id,'version'=>$version,'field_schema'=>Input::json($fields),'created_at'=>$now]);
            return Input::row(ContentModel::query()->where('id',$id)->first());
        });
    }
    public function validate(string $versionId,array $data): array
    {
        $version=Input::row(ModelVersion::query()->where('id',Input::id($versionId))->first());if(!$version) throw new InvalidArgumentException('模型版本不存在');
        $allowed=array_column($version['field_schema'],'name');if(array_diff(array_keys($data),$allowed)) throw new InvalidArgumentException('包含未声明的模型字段');
        $result=[];
        foreach($version['field_schema'] as $field) {
            $name=$field['name'];
            $result[$name]=$this->value($field,array_key_exists($name,$data)?$data[$name]:($field['default']??null));
        } return $result;
    }
    private function definition(array $field): array
    {
        if(isset($field['required'])&&!is_bool($field['required']))throw new InvalidArgumentException('必填设置必须为布尔值');
        foreach(['description'=>500,'placeholder'=>255] as $key=>$length)if(isset($field[$key]))$field[$key]=Input::text($field[$key],$length);
        if($field['type']==='select') {
            if(!isset($field['options'])||!is_array($field['options'])||!array_is_list($field['options'])||!count($field['options'])||count($field['options'])>100)throw new InvalidArgumentException('选择字段需要 1 至 100 个选项');
            foreach($field['options'] as $option)if(!is_string($option)||trim($option)===''||mb_strlen($option)>255)throw new InvalidArgumentException('选项必须是非空文本');
            if(count(array_unique($field['options']))!==count($field['options']))throw new InvalidArgumentException('选项不能重复');
        }
        foreach(['min','max'] as $key) {
            if(!isset($field[$key])){unset($field[$key]);continue;}
            if($field['type']!=='number'||!is_numeric($field[$key])||!is_finite((float)$field[$key]))throw new InvalidArgumentException('数字范围配置无效');
            $field[$key]=(float)$field[$key];
        }
        if(isset($field['min'],$field['max'])&&$field['min']>$field['max'])throw new InvalidArgumentException('最小值不能大于最大值');
        if(isset($field['max_length'])) {
            $limit=$field['type']==='textarea'?20000:2000;
            if(!in_array($field['type'],['text','textarea'],true)||!is_int($field['max_length'])||$field['max_length']<1||$field['max_length']>$limit)throw new InvalidArgumentException('文本长度配置无效');
        }else unset($field['max_length']);
        if(array_key_exists('default',$field)&&$field['default']===''&&in_array($field['type'],['boolean','number'],true))throw new InvalidArgumentException('默认值类型无效');
        if(array_key_exists('default',$field)&&$field['default']!==null&&$field['default']!=='') {
            $check=$field;$check['required']=false;
            $field['default']=$this->value($check,$field['default']);
        }
        return $field;
    }
    private function value(array $field,mixed $value): mixed
    {
        if(($field['required']??false)&&($value===null||is_string($value)&&trim($value)===''||$field['type']==='gallery'&&$value===[]))throw new InvalidArgumentException('必填字段：'.$field['label']);
        if($value===null||$value==='')return $value;
        switch($field['type']) {
            case 'number':
                if(!is_numeric($value)||!is_finite((float)$value))throw new InvalidArgumentException('数字字段无效：'.$field['label']);
                $value=(float)$value;
                if(isset($field['min'])&&$value<$field['min']||isset($field['max'])&&$value>$field['max'])throw new InvalidArgumentException('数字超出范围：'.$field['label']);
                break;
            case 'boolean':if(!is_bool($value))throw new InvalidArgumentException('布尔字段无效');break;
            case 'image':case 'file':$value=$this->attachment($value);break;
            case 'gallery':if(!is_array($value)||!array_is_list($value)||count($value)>100)throw new InvalidArgumentException('图库无效');$value=array_map($this->attachment(...),$value);break;
            case 'select':if(!in_array($value,$field['options'],true))throw new InvalidArgumentException('字段选项无效');break;
            default:
                $value=Input::text($value,$field['type']==='textarea'?20000:2000);
                if(isset($field['max_length'])&&mb_strlen($value)>$field['max_length'])throw new InvalidArgumentException('文本超出长度：'.$field['label']);
        }
        return $value;
    }
    public function attachment(mixed $id): string
    {
        $id=Input::id($id);if(!Db::schema()->hasTable('attach')||!Attach::query()->withoutGlobalScopes()->where('id',$id)->where('status',1)->whereNull('deleted_at')->exists()) throw new InvalidArgumentException('附件不存在或不可访问');return $id;
    }
}
