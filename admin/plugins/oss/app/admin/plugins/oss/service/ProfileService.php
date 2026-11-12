<?php
declare(strict_types=1);
namespace app\admin\plugins\oss\service;
use app\common\model\Attach;
use app\common\storage\StorageDriverInterface;
use support\Db;
use InvalidArgumentException;
use RuntimeException;
use app\admin\plugins\oss\model\OssProfile;
use app\admin\plugins\oss\model\OssSetting;
/**
 * 云存储配置管理。
 * - 删除是软删除；当前配置不能删除（409）。回收站可以恢复和彻底删除；仍有附件（含回收站中的附件）引用 oss_<id> 时彻底删除返回 409。
 * - 附件读取与删除按 driver 名解析配置时包含已删除的配置（resolve），已删除配置的历史附件仍可访问和删除；上传、启用、测试只用未删除的配置。
 * - 连接测试使用表单里尚未保存的值；编辑已有配置时密钥留空沿用已保存的密钥，此时存储位置必须与已保存的一致（与保存规则相同）。
 */
final class ProfileService
{
    private const FIELDS=['id','name','provider','bucket','region','endpoint','domain','prefix','path_style','access_key','secret_key'];
    /** 存储位置字段：保存后不可修改（历史附件按配置定位对象）。 */
    private const LOCATION=['provider','bucket','region','endpoint','domain','prefix'];
    /** 连接测试中每个 SDK 请求的连接与总超时（秒）；最多约 4 个请求（七牛未指定区域时多一次区域查询），总计不超过 PROBE_BUDGET。 */
    public const PROBE_TIMEOUT=3;
    public const PROBE_BUDGET=15;
    private readonly ProfileSecret $secrets;
    public function __construct(?string $masterKey=null,private readonly mixed $driverFactory=null){$this->secrets=new ProfileSecret($masterKey??(string)config('ai.encryption_key',getenv('OAUTH_ENCRYPTION_KEY')?:''));}
    private function ready():void{if(!Schema::ready())throw new RuntimeException('请先完成 OSS 插件安装或数据库升级',503);}
    private function id(string $id):string{if(!preg_match('/^[a-f0-9]{16}$/D',$id))throw new InvalidArgumentException('云存储配置标识无效');return $id;}
    private function row(string $id,bool $lock=false,bool $withTrashed=false):array{$this->ready();$query=($withTrashed?OssProfile::withTrashed():OssProfile::query())->where('id',$this->id($id));if($lock)$query->lockForUpdate();return ($query->first()??throw new InvalidArgumentException('云存储配置不存在',404))->getAttributes();}
    private function publicRow(array $row):array{unset($row['credentials']);$row['has_credentials']=true;$row['path_style']=(bool)($row['path_style']??false);return $row;}
    public function listing():array{$this->ready();return array_map(fn(OssProfile $row)=>$this->publicRow($row->getAttributes()),OssProfile::query()->orderByDesc('created_at')->orderBy('id')->get()->all());}
    public function current():?string{$this->ready();$id=OssSetting::query()->where('id',1)->value('current_profile_id');return $id===null?null:$this->id((string)$id);}
    private function text(mixed $value,int $max=255):string{if(!is_scalar($value)&&$value!==null)throw new InvalidArgumentException('配置字段格式无效');$text=trim((string)$value);if(mb_strlen($text)>$max)throw new InvalidArgumentException('配置字段过长');return $text;}
    private function flag(mixed $value):bool{
        if(is_bool($value))return $value;
        if(in_array($value,[0,1,'0','1','true','false'],true))return in_array($value,[1,'1','true'],true);
        throw new InvalidArgumentException('path-style 必须为布尔值');
    }
    /**
     * 校验并规范化表单（保存与连接测试共用）。返回 [配置字段, 访问密钥, 密钥]，密钥原样返回（可能为空，表示沿用已保存的值）。
     * @return array{0: array<string, mixed>, 1: string, 2: string}
     */
    private function normalize(array $input,?array $old):array{
        if(array_diff(array_keys($input),self::FIELDS))throw new InvalidArgumentException('包含未知云存储配置字段');
        $data=[];foreach(['name'=>100,'provider'=>20,'bucket'=>100,'region'=>60,'endpoint'=>255,'domain'=>500,'prefix'=>200] as $field=>$max)$data[$field]=$this->text($input[$field]??$old[$field]??'',$max);
        if($data['name']==='')throw new InvalidArgumentException('请填写配置名称');
        if(!in_array($data['provider'],CloudDriver::PROVIDERS,true))throw new InvalidArgumentException('不支持此云存储厂商');
        $s3=$data['provider']==='s3';
        $data['path_style']=$s3&&$this->flag($input['path_style']??$old['path_style']??false);
        if($s3?!CloudDriver::validS3Bucket($data['bucket']):!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{1,99}$/D',$data['bucket']))throw new InvalidArgumentException('存储桶名称无效');
        if(!preg_match('/^[a-z0-9-]{0,60}$/D',$data['region']))throw new InvalidArgumentException('地域标识无效');
        if($data['provider']==='tencent'&&$data['region']==='')throw new InvalidArgumentException('请填写腾讯云 Region');
        if($s3&&$data['region']==='')throw new InvalidArgumentException('请填写 S3 Region（Cloudflare R2 填 auto，MinIO 一般为 us-east-1）');
        if($data['provider']==='aliyun'){
            $endpoint=parse_url($data['endpoint']);$host=$endpoint['host']??'';
            if(($endpoint['scheme']??'')!=='https'||!str_ends_with($host,'.aliyuncs.com')||(isset($endpoint['user'])||isset($endpoint['pass']))||isset($endpoint['query'])||isset($endpoint['fragment'])||isset($endpoint['port'])||!in_array($endpoint['path']??'',['','/'],true))throw new InvalidArgumentException('请填写 HTTPS 阿里云官方 Endpoint');
        }
        if($s3&&!CloudDriver::validS3Endpoint($data['endpoint']))throw new InvalidArgumentException('S3 Endpoint 必须是 HTTPS 地址（AWS 可留空），不能指向本机、链路本地或元数据地址');
        $domain=parse_url($data['domain']);
        if(!in_array($domain['scheme']??'',['http','https'],true)||empty($domain['host'])||isset($domain['user'])||isset($domain['pass'])||isset($domain['query'])||isset($domain['fragment'])||preg_match('/[\x00-\x20\\\\]/',$data['domain']))throw new InvalidArgumentException('请填写有效的公开访问域名');
        if($data['prefix']!==''&&(!preg_match('~^[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*$~D',$data['prefix'])))throw new InvalidArgumentException('目录前缀必须为安全相对目录');
        if($old)foreach(self::LOCATION as $field)if($data[$field]!==$old[$field])throw new InvalidArgumentException('存储位置不可修改，请新增配置后切换，保留历史附件归属');
        return [$data,$this->text($input['access_key']??'',500),$this->text($input['secret_key']??'',1000)];
    }
    /** 合并凭据：留空的一项沿用已保存的值；结果必须是真实值。 */
    private function credentials(string $id,?array $old,string $access,string $secret):array{
        $previous=$old&&($access===''||$secret==='')?$this->secrets->open($id,$old['credentials']):[];
        $credentials=['access_key'=>$access!==''?$access:($previous['access_key']??''),'secret_key'=>$secret!==''?$secret:($previous['secret_key']??'')];
        if(in_array('',$credentials,true)||in_array('********',$credentials,true)||in_array('[REDACTED]',$credentials,true))throw new InvalidArgumentException('请填写真实的 AccessKey / Secret，留空可保留已有凭据');
        return $credentials;
    }
    public function save(array $input):array{
        $this->ready();if(array_diff(array_keys($input),self::FIELDS))throw new InvalidArgumentException('包含未知云存储配置字段');
        return Db::transaction(function()use($input):array{
            $old=empty($input['id'])?null:$this->row((string)$input['id'],true);$id=$old['id']??bin2hex(random_bytes(8));
            [$data,$access,$secret]=$this->normalize($input,$old);
            $cipher=$old['credentials']??null;
            if(!$old||$access!==''||$secret!=='')$cipher=$this->secrets->seal($id,$this->credentials($id,$old,$access,$secret));
            $data['credentials']=$cipher;$data['updated_at']=gmdate('Y-m-d H:i:s');
            if($old)OssProfile::query()->where('id',$id)->update($data);else OssProfile::query()->insert($data+['id'=>$id,'created_at'=>$data['updated_at']]);
            return $this->publicRow($this->row($id));
        });
    }
    private function build(array $row,array $limits=[]):StorageDriverInterface{
        $profile=array_replace($row,$this->secrets->open($row['id'],$row['credentials']));unset($profile['credentials']);
        return $this->driverFactory===null?new CloudDriver($profile,null,$limits):($this->driverFactory)($profile,$limits);
    }
    /** 未删除的配置（上传、启用、测试）。 */
    public function driver(string $name):?StorageDriverInterface{
        if(!preg_match('/^oss_([a-f0-9]{16})$/D',$name,$matches))return null;
        return $this->build($this->row($matches[1]));
    }
    /** 历史附件的读取与删除：包含已软删除的配置，彻底删除前它们的附件仍然可用。 */
    public function resolve(string $name):?StorageDriverInterface{
        if(!preg_match('/^oss_([a-f0-9]{16})$/D',$name,$matches))return null;
        return $this->build($this->row($matches[1],false,true));
    }
    public function drivers():array{
        return array_map(function(array $row):array{
            try{$available=$this->driver('oss_'.$row['id'])?->available()??false;}catch(\Throwable){$available=false;}
            return ['name'=>'oss_'.$row['id'],'label'=>$row['name'],'source'=>'oss','available'=>$available];
        },$this->listing());
    }
    public function activate(string $id):array{
        $id=$this->id($id);$driver=$this->driver('oss_'.$id);if(!$driver?->available())throw new InvalidArgumentException('对应云 SDK 或配置不可用，请检查插件依赖');
        Db::transaction(function()use($id):void{
            OssSetting::query()->where('id',1)->lockForUpdate()->first();
            // 与删除互斥：持有设置行锁后再确认配置仍未删除。
            if(!OssProfile::query()->where('id',$id)->sharedLock()->exists())throw new InvalidArgumentException('云存储配置不存在',404);
            OssSetting::query()->updateOrInsert(['id'=>1],['current_profile_id'=>$id]);
        });
        return ['current'=>$id];
    }
    /** 软删除；当前配置不能删除。 */
    public function delete(string $id):array{
        $this->ready();$id=$this->id($id);
        return Db::transaction(function()use($id):array{
            $current=OssSetting::query()->where('id',1)->lockForUpdate()->value('current_profile_id');
            $profile=OssProfile::query()->where('id',$id)->lockForUpdate()->first()??throw new InvalidArgumentException('云存储配置不存在',404);
            if($current!==null&&(string)$current===$id)throw new InvalidArgumentException('不能删除当前存储配置，请先把其他配置设为当前存储',409);
            $profile->delete();
            return ['id'=>$id];
        });
    }
    public function recycle():array{
        $this->ready();
        return ['list'=>array_map(fn(OssProfile $row)=>$this->publicRow($row->getAttributes()),OssProfile::onlyTrashed()->orderByDesc('deleted_at')->orderBy('id')->get()->all())];
    }
    public function restore(string $id):array{
        $this->ready();$id=$this->id($id);
        return Db::transaction(function()use($id):array{
            $profile=OssProfile::onlyTrashed()->where('id',$id)->lockForUpdate()->first()??throw new InvalidArgumentException('云存储配置不在回收站',404);
            $profile->restore();
            return $this->publicRow($this->row($id));
        });
    }
    /** 回收站彻底删除：仍有附件（含已软删除的附件）引用 oss_<id> 时返回 409；只删配置行，不删除云端对象。 */
    public function destroy(string $id):array{
        $this->ready();$id=$this->id($id);
        return Db::transaction(function()use($id):array{
            $current=OssSetting::query()->where('id',1)->lockForUpdate()->value('current_profile_id');
            $profile=OssProfile::onlyTrashed()->where('id',$id)->lockForUpdate()->first()??throw new InvalidArgumentException('云存储配置不在回收站',404);
            if($current!==null&&(string)$current===$id)throw new InvalidArgumentException('不能删除当前存储配置',409);
            if(Attach::withTrashed()->where('driver','oss_'.$id)->exists())throw new InvalidArgumentException('仍有附件（含回收站中的附件）使用此配置，不能彻底删除',409);
            $profile->forceDelete();
            return ['id'=>$id];
        });
    }
    /**
     * 连接测试：用表单值（可不保存）依次上传随机小对象、读回校验、删除，返回每一步的结果。
     * 每个 SDK 请求的超时为 PROBE_TIMEOUT 秒；上传成功后总会尝试删除测试对象，读取步骤在超出预算时跳过。
     * @return array{success: bool, steps: list<array{step: string, status: string, reason: ?string, message: string, ms: int}>, elapsed_ms: int}
     */
    public function testConnection(array $input):array{
        $this->ready();
        $old=empty($input['id'])?null:$this->row((string)$input['id']);
        [$data,$access,$secret]=$this->normalize($input,$old);
        $id=$old['id']??bin2hex(random_bytes(8));
        $profile=$data+$this->credentials($id,$old,$access,$secret)+['id'=>$id];
        $limits=['timeout'=>self::PROBE_TIMEOUT,'connect_timeout'=>self::PROBE_TIMEOUT];
        $driver=$this->driverFactory===null?new CloudDriver($profile,null,$limits):($this->driverFactory)($profile,$limits);
        if(!$driver->available())throw new InvalidArgumentException('对应云 SDK 或配置不可用');
        if(!method_exists($driver,'verify'))throw new RuntimeException('当前驱动不支持连接测试');
        $content='FunAdmin OSS connection probe '.bin2hex(random_bytes(16));
        $path=tempnam(sys_get_temp_dir(),'oss-probe-');if($path===false)throw new RuntimeException('无法创建连接测试文件');
        $started=hrtime(true);$elapsed=static fn():int=>intdiv(hrtime(true)-$started,1_000_000);
        $steps=[];$key=null;
        $run=function(string $step,callable $operation)use(&$steps,$elapsed):bool{
            $begin=$elapsed();
            try{$ok=(bool)$operation();$reason=$ok?null:'mismatch';$message=$ok?'成功':'读回内容与上传内容不一致';}
            catch(\Throwable $error){$ok=false;$reason='error';$message=$error instanceof RuntimeException?$error->getMessage():'云存储操作失败';}
            $steps[]=['step'=>$step,'status'=>$ok?'ok':'failed','reason'=>$reason,'message'=>$message,'ms'=>$elapsed()-$begin];
            return $ok;
        };
        $skip=static function(string $step,string $reason,string $message)use(&$steps):void{$steps[]=['step'=>$step,'status'=>'skipped','reason'=>$reason,'message'=>$message,'ms'=>0];};
        try{
            file_put_contents($path,$content);
            $uploaded=$run('upload',function()use($driver,$path,&$key):bool{$key=$driver->store(new \Webman\Http\UploadFile($path,'connection.txt','text/plain',UPLOAD_ERR_OK),'connection-tests')->key;return true;});
            if(!$uploaded){$skip('read','upload_failed','上传失败，未读取');$skip('delete','upload_failed','上传失败，无需删除');}
            else{
                if($elapsed()>(self::PROBE_BUDGET-2*self::PROBE_TIMEOUT)*1000)$skip('read','timeout','已接近超时，跳过读取校验');
                else $run('read',fn():bool=>$driver->verify($key,$content));
                if(!$run('delete',fn():bool=>$driver->delete($key))){try{\support\Log::warning('oss.probe.cleanup_failed',['driver'=>$driver->name(),'key'=>$key]);}catch(\Throwable){}}
            }
        }finally{if(is_file($path))unlink($path);}
        $success=array_all($steps,static fn(array $step):bool=>$step['status']==='ok');
        return ['success'=>$success,'steps'=>$steps,'elapsed_ms'=>$elapsed()];
    }
}
