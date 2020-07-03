<?php

/**
 * lemocms
 * ============================================================================
 * 版权所有 2018-2027 lemocms，并保留所有权利。
 * 网站地址: https://www.lemocms.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/8/2
 */
namespace app\backend\controller\sys;
use app\common\controller\Backend;
use speed\helper\StringHelper;
use think\facade\Request;
use app\common\lib\Backup;
// 需要修改\tp5er\backup connect()
//以及 $info['name'] = $file->getFilename();
class Database extends Backend
{
    protected $db = '';
    function initialize(){
        parent::initialize();
        $this->config=array(
            'path'     => './Data/',//数据库备份路径
            'part'     => 20971520,//数据库备份卷大小
            'compress' => 0,//数据库备份文件是否启用压缩 0不压缩 1 压缩
            'level'    => 9 //数据库备份文件压缩级别 1普通 4 一般  9最高
        );
        $this->db = new Backup($this->config);
    }
    public function index(){
        if(Request::isPost()){
            $list = $this->db->dataList();
            $total = 0;
            foreach ($list as $k => $v) {
                $list[$k]['size'] = StringHelper::formatBytes($v['data_length']);
                $total += $v['data_length'];
            }

            return $result = ['code'=>0,'msg'=>'获取成功!','data'=>$list,'total'=>StringHelper::formatBytes($total),'tableNum'=>count($list),'rel'=>1];
        }
        return view();
    }
    //优化
    public function optimize() {
        $tables = Request::param('tables');
        if (empty($tables)) {
            $this->success(lang('please choose table')) ;
        }
        if($this->db->optimize($tables)){
            $this->success(lang('optimize success')) ;
        }else{
            $this->error(lang('optimize fail'));
        }
    }
    //修复
    public function repair() {
        $tables = Request::param('tables');
        if (empty($tables)) {
            $this->error(lang('please choose table'));
        }
        if($this->db->repair($tables)){
            $this->success(lang('repair success')) ;
        }else{
            $this->error(lang('repair fail'));
        }
    }
    //备份
    public function backup(){
        $tables = Request::param('tables');
        if (!empty($tables)) {
            foreach ($tables as $table) {
                $this->db->setFile()->backup($table, 0);
            }
            $this->success(lang('backup success')) ;

        } else {
            $this->error(lang('please choose table')) ;

        }
    }
    //备份列表
    public function restore(){
        if(Request::isPost()){
            $list =  $this->db->fileList();
            return $result = ['code'=>0,'msg'=>lang('get info success'),'data'=>$list,'rel'=>1];
        }
        return view();
    }
    //执行还原数据库操作
    public function import($time) {
        $list  = $this->db->getFile('timeverif',$time);
        $this->db->setFile($list)->import(1);
        $this->success('restore success') ;
    }

    //下载
    public function downFile($time) {
        $this->db->downloadFile($time);
    }
    //删除sql文件
    public function delSqlFiles() {
        $time = input('post.time');
        if($this->db->delFile($time)){
            $this->success(lang('delete success')) ;
        }else{
            $this->error(lang('delete fail')) ;

        }
    }
}