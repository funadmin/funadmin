<?php
declare(strict_types=1);
namespace app\admin\plugins\oss\service;
use app\common\storage\StorageDriverInterface;
use support\Db;
use InvalidArgumentException;
use RuntimeException;
final class ProfileService
{
    private readonly ProfileSecret $secrets;
    public function __construct(?string $masterKey=null,private readonly mixed $driverFactory=null){$this->secrets=new ProfileSecret($masterKey??(string)config('ai.encryption_key',getenv('OAUTH_ENCRYPTION_KEY')?:''));}
    private function ready():void{if(!Schema::ready())throw new RuntimeException('请先完成 OSS 插件安装或数据库升级',503);}
    private function id(string $id):string{if(!preg_match('/^[a-f0-9]{16}$/D',$id))throw new InvalidArgumentException('云存储配置标识无效');return $id;}
    private function row(string $id,bool $lock=false):array{$this->ready();$query=Db::table('oss_profile')->where('id',$this->id($id));if($lock)$query->lockForUpdate();return (array)($query->first()??throw new InvalidArgumentException('云存储配置不存在'));}
    private function publicRow(array $row):array{unset($row['credentials']);$row['has_credentials']=true;return $row;}
    public function listing():array{$this->ready();return array_map(fn($row)=>$this->publicRow((array)$row),Db::table('oss_profile')->orderByDesc('created_at')->orderBy('id')->get()->all());}
    public function current():?string{$this->ready();$id=Db::table('oss_setting')->where('id',1)->value('current_profile_id');return $id===null?null:$this->id((string)$id);}
    private function text(mixed $value,int $max=255):string{if(!is_scalar($value)&&$value!==null)throw new InvalidArgumentException('配置字段格式无效');$text=trim((string)$value);if(mb_strlen($text)>$max)throw new InvalidArgumentException('配置字段过长');return $text;}
    public function save(array $input):array{
        $this->ready();$allowed=['id','name','provider','bucket','region','endpoint','domain','prefix','access_key','secret_key'];if(array_diff(array_keys($input),$allowed))throw new InvalidArgumentException('包含未知云存储配置字段');
        return Db::transaction(function()use($input):array{
            $old=empty($input['id'])?null:$this->row((string)$input['id'],true);$id=$old['id']??bin2hex(random_bytes(8));
            $data=[];foreach(['name'=>100,'provider'=>20,'bucket'=>100,'region'=>60,'endpoint'=>255,'domain'=>500,'prefix'=>200] as $field=>$max)$data[$field]=$this->text($input[$field]??$old[$field]??'',$max);
            if($data['name']==='')throw new InvalidArgumentException('请填写配置名称');
            if(!in_array($data['provider'],['aliyun','tencent','qiniu'],true))throw new InvalidArgumentException('不支持此云存储厂商');
            if(!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{1,99}$/D',$data['bucket']))throw new InvalidArgumentException('存储桶名称无效');
            if(!preg_match('/^[a-z0-9-]{0,60}$/D',$data['region']))throw new InvalidArgumentException('地域标识无效');
            if($data['provider']==='tencent'&&$data['region']==='')throw new InvalidArgumentException('请填写腾讯云 Region');
            if($data['provider']==='aliyun'){
                $endpoint=parse_url($data['endpoint']);$host=$endpoint['host']??'';
                if(($endpoint['scheme']??'')!=='https'||!str_ends_with($host,'.aliyuncs.com')||(isset($endpoint['user'])||isset($endpoint['pass']))||isset($endpoint['query'])||isset($endpoint['fragment'])||isset($endpoint['port'])||!in_array($endpoint['path']??'',['','/'],true))throw new InvalidArgumentException('请填写 HTTPS 阿里云官方 Endpoint');
            }
            $domain=parse_url($data['domain']);
            if(!in_array($domain['scheme']??'',['http','https'],true)||empty($domain['host'])||isset($domain['user'])||isset($domain['pass'])||isset($domain['query'])||isset($domain['fragment'])||preg_match('/[\x00-\x20\\\\]/',$data['domain']))throw new InvalidArgumentException('请填写有效的公开访问域名');
            if($data['prefix']!==''&&(!preg_match('~^[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*$~D',$data['prefix'])))throw new InvalidArgumentException('目录前缀必须为安全相对目录');
            if($old)foreach(['provider','bucket','region','endpoint','domain','prefix'] as $field)if($data[$field]!==$old[$field])throw new InvalidArgumentException('存储位置不可修改，请新增配置后切换，保留历史附件归属');
            $access=$this->text($input['access_key']??'',500);$secret=$this->text($input['secret_key']??'',1000);
            $cipher=$old['credentials']??null;
            if(!$old||$access!==''||$secret!==''){
                $previous=$old&&($access===''||$secret==='')?$this->secrets->open($id,$old['credentials']):[];
                $credentials=['access_key'=>$access!==''?$access:($previous['access_key']??''),'secret_key'=>$secret!==''?$secret:($previous['secret_key']??'')];
                if(in_array('',$credentials,true)||in_array('********',$credentials,true)||in_array('[REDACTED]',$credentials,true))throw new InvalidArgumentException('请填写真实的 AccessKey / Secret，留空可保留已有凭据');
                $cipher=$this->secrets->seal($id,$credentials);
            }
            $data['credentials']=$cipher;$data['updated_at']=gmdate('Y-m-d H:i:s');
            if($old)Db::table('oss_profile')->where('id',$id)->update($data);else Db::table('oss_profile')->insert($data+['id'=>$id,'created_at'=>$data['updated_at']]);
            return $this->publicRow($this->row($id));
        });
    }
    public function driver(string $name):?StorageDriverInterface{
        if(!preg_match('/^oss_([a-f0-9]{16})$/D',$name,$matches))return null;
        $row=$this->row($matches[1]);$profile=array_replace($row,$this->secrets->open($row['id'],$row['credentials']));unset($profile['credentials']);
        return $this->driverFactory===null?new CloudDriver($profile):($this->driverFactory)($profile);
    }
    public function drivers():array{
        return array_map(function(array $row):array{
            try{$available=$this->driver('oss_'.$row['id'])?->available()??false;}catch(\Throwable){$available=false;}
            return ['name'=>'oss_'.$row['id'],'label'=>$row['name'],'source'=>'oss','available'=>$available];
        },$this->listing());
    }
    public function activate(string $id):array{
        $id=$this->id($id);$driver=$this->driver('oss_'.$id);if(!$driver?->available())throw new InvalidArgumentException('对应云 SDK 或配置不可用，请检查插件依赖');
        Db::transaction(function()use($id):void{Db::table('oss_setting')->where('id',1)->lockForUpdate()->first();Db::table('oss_setting')->updateOrInsert(['id'=>1],['current_profile_id'=>$id]);});return ['current'=>$id];
    }
    public function testConnection(string $id):array{
        $driver=$this->driver('oss_'.$this->id($id));if(!$driver?->available())throw new InvalidArgumentException('对应云 SDK 或配置不可用');
        $path=tempnam(sys_get_temp_dir(),'oss-probe-');if($path===false)throw new RuntimeException('无法创建连接测试文件');
        try{file_put_contents($path,'FunAdmin OSS connection probe');$stored=$driver->store(new \Webman\Http\UploadFile($path,'connection.txt','text/plain',UPLOAD_ERR_OK),'connection-tests');if(!$driver->delete($stored->key)){error_log(json_encode(['event'=>'oss.probe.cleanup_failed','driver'=>$driver->name(),'key'=>$stored->key]));throw new RuntimeException('测试对象写入成功，但清理失败，请按日志清理测试对象');}return ['success'=>true,'message'=>'测试对象上传和删除成功'];}finally{if(is_file($path))unlink($path);}
    }
}
