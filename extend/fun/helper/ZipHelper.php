<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.funadmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/9/22
 */

namespace fun\helper;

class ZipHelper
{
    /**
     *  压缩文件
     * @param $zipFile 相对文件路劲
     * @param $folderPath 相对文件夹路劲
     */
    public static function zip($zipFile, $folderPath)
    {
        // Get real path for our folder
        $rootPath = realpath($folderPath);
        // Initialize archive object
        $zip = new \ZipArchive();
        $zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        // Create recursive directory iterator
        /** @var SplFileInfo[] $files */
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($rootPath),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($files as $name => $file) {
            // Skip directories (they would be added automatically)
            if (!$file->isDir()) {
                // Get real and relative path for current file
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($rootPath) + 1);
                // Add current file to archive
                $zip->addFile($filePath, $relativePath);
            }
        }
        // Zip archive will be created only after closing object
        $zip->close();
    }

    /**
     *  解压文件
     * @param $zipFile 相对文件路劲
     * @param $folderPath 相对文件夹路劲
     */
    public static function unzip( $zipFile,$folderPath,$addon=0)
    {
        // Initialize archive object
        if (!class_exists('ZipArchive')) {
            throw new \Exception('ZinArchive not find');
        }
        $zip = new \ZipArchive();
        try {
            $zip->open($zipFile);
        } catch (\Exception $e) {
            $zip->close();
            throw new \Exception('Unable to open the zip file');
        }
        if (!is_dir($folderPath)) {
            @mkdir($folderPath, 0755);
        }
        $fileDir = trim($zip->getNameIndex(0), '/');
        //解压压缩包
        try {
            $zip->extractTo($folderPath);
        } catch (\Exception $e) {
            throw new \Exception('Unable to extract the file');
        } finally {
            $zip->close();
        }
        return $fileDir;
    }

}