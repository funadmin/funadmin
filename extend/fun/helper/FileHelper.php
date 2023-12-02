<?php

namespace fun\helper;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
class FileHelper
{
    /**
     * 检测目录并循环创建目录
     *
     * @param $catalogue
     */
    public static function mkdirs($dir)
    {
        if (!file_exists($dir)) {
            self::mkdirs(dirname($dir));
            mkdir($dir, 0777);
        }
        return true;
    }
    /**
     * @param $dir
     * @return bool
     * 删除文件以及目录
     */
    public static function delDir($dir) {
        //先删除目录下的文件：
        if(!is_dir($dir)){
            return true;
        }
        $dh=opendir($dir);
        while ($file=readdir($dh)) {
            if($file!="." && $file!="..") {
                $fullpath=$dir."/".$file;
                if(!is_dir($fullpath)) {
                    @unlink($fullpath);
                } else {
                    self::delDir($fullpath);
                }
            }
        }
        closedir($dh);
        //删除当前文件夹：
        if(@rmdir($dir)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $source
     * @param $dest
     * 复制文件到指定文件
     */
    public static function copyDir($source, $dest,$delete=false)
    {
        if (!is_dir($dest)) {
            self::mkdirs($dest, 0755, true);
        }
        foreach (
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            ) as $item
        ) {
            if ($item->isDir()) {
                $sontDir = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
                if (!is_dir($sontDir)) {
                    self::mkdirs($sontDir, 0755, true);
                }
            } else {
                @copy($item, $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
                if($delete) unlink($item);
            }
        }
        return true;
    }

    /*写入
    * @param  string  $type 1 为生成控制器 2 模型
    */

    public static function filePutContents($content,$filepath,$type){
        if($type==1){
            $str = file_get_contents($filepath);
            $parten = '/\s\/\*+start\*+\/(.*)\/\*+end\*+\//iUs';
            preg_match_all($parten,$str,$all);
            $ext_content = '';
            if($all[0]){
                foreach($all[0] as $key=>$val){
                    $ext_content .= $val."\n\n";
                }
            }
            $content .= $ext_content."\n\n";
            $content .="}\n\n";
        }
        ob_start();
        echo $content;
        $_cache=ob_get_contents();
        ob_end_clean();
        if($_cache){
            $File = new \think\template\driver\File();
            $File->write($filepath, $_cache);
        }
    }
    /**
     * 获取文件夹大小
     *
     * @param string $dir 根文件夹路径
     * @return int
     */
    public static function getDirSize($dir)
    {
        if(!is_dir($dir)){
            return false;
        }
        $handle = opendir($dir);
        $sizeResult = 0;
        while (false !== ($FolderOrFile = readdir($handle))) {
            if ($FolderOrFile != "." && $FolderOrFile != "..") {
                if (is_dir("$dir/$FolderOrFile")) {
                    $sizeResult += self::getDirSize("$dir/$FolderOrFile");
                } else {
                    $sizeResult += filesize("$dir/$FolderOrFile");
                }
            }
        }

        closedir($handle);
        return $sizeResult;
    }

    /**
     * 创建文件
     *
     * @param $files
     */
    public static function createFile($file,$content)
    {
        $myfile = fopen($file, "w") or die("Unable to open file!");
        fwrite($myfile, $content);
        fclose($myfile);
        return true;
    }
    /**
     * 基于数组创建目录
     *
     * @param $files
     */
    public static function createDirOrFiles($files)
    {
        foreach ($files as $key => $value) {
            if (substr($value, -1) == '/') {
                mkdir($value,0755);
            } else {
                file_put_contents($value, '');
            }
        }
    }

    // 判断文件或目录是否有写的权限
    public static function isWritable($file)
    {
        if (DIRECTORY_SEPARATOR == '/' AND @ ini_get("safe_mode") == FALSE) {
            return is_writable($file);
        }
        if (!is_file($file) OR ($fp = @fopen($file, "r+")) === FALSE) {
            return FALSE;
        }
        fclose($fp);
        return TRUE;
    }

    /**
     * 写入日志
     *
     * @param $path
     * @param $content
     * @return bool|int
     */
    public static function writeLog($path, $content)
    {
        self::mkdirs(dirname($path));
        return file_put_contents($path, "\r\n" . $content, FILE_APPEND);
    }

    /**
     * 获取本地文件列表
     * @param $path
     * @param string $type
     * @return array
     */
    public static function getFileList($path,$type='')
    {
        $list = [];
        $temp_list = scandir($path);
        foreach ($temp_list as $file) {
            //排除根目录
            if ($file != ".." && $file != ".") {
                if (is_dir($path . "/" . $file)) {
                    //子文件夹，进行递归
                    $list[] = self::getFileList($path . "/" . $file,$type);
                } else {
                    if($type==''){
                        //根目录下的文件
                        $list[] = $file;
                    }else{
                        $fileType = mime_content_type($path.'/'.$file);
                        if(strpos($fileType,$type)!==false){
                            $list[] = $path.'/'.$file;
                        }
                    }
                }
            }
        }
        return $list;
    }
}
