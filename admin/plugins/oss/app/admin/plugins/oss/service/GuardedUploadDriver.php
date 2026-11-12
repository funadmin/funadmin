<?php
declare(strict_types=1);
namespace app\admin\plugins\oss\service;
use app\admin\plugins\oss\model\OssProfile;
use app\common\storage\{StorageDriverInterface,StoredFile};
use RuntimeException;
use Webman\Http\UploadFile;
/**
 * 上传用的配置驱动：云端写入成功、宿主写附件行之前，再确认配置仍存在且未删除。
 *
 * 上传开始时解析的当前配置，可能在上传期间被切换、删除（进入回收站）甚至彻底删除；这里在 store() 返回前重新查询一次，
 * 配置已删除时删除刚上传的对象并让本次上传失败，避免留下指向不存在配置的附件。宿主在 store() 返回后到写入附件行之间
 * 不再回调插件，这段很短的窗口无法在插件内关闭（需要宿主在同一事务里加锁），见 README。
 */
final class GuardedUploadDriver implements StorageDriverInterface
{
    public function __construct(private readonly StorageDriverInterface $driver,private readonly string $profileId){}
    public function name():string{return $this->driver->name();}
    public function label():string{return $this->driver->label();}
    public function source():string{return $this->driver->source();}
    public function available():bool{return $this->driver->available();}
    public function delete(string $storageKey):bool{return $this->driver->delete($storageKey);}
    public function store(UploadFile $file,string $directory,string $rule=''):StoredFile{
        $stored=$this->driver->store($file,$directory,$rule);
        try{$exists=OssProfile::query()->where('id',$this->profileId)->exists();}
        catch(\Throwable $error){
            // 复查失败（如数据库异常）：对象已在云端但不确定能否登记，与 CloudDriver 相同只记录清理所需的标识（不含异常信息与凭据），再抛出。
            $this->log('oss.upload.uncertain',$stored->key);
            throw $error;
        }
        if($exists)return $stored;
        try{if(!$this->driver->delete($stored->key))throw new RuntimeException('cleanup failed');}
        catch(\Throwable){$this->log('oss.upload.cleanup_failed',$stored->key);}
        throw new RuntimeException('云存储配置已在上传期间删除，请重新上传');
    }
    /** 只记录驱动名与对象 key（清理所需的标识），日志失败不覆盖原有错误。 */
    private function log(string $event,string $key):void{try{\support\Log::warning($event,['driver'=>$this->driver->name(),'key'=>$key]);}catch(\Throwable){}}
}
