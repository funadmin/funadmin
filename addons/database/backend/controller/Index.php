<?php

/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */
namespace addons\database\backend\controller;
use app\common\controller\AddonsBackend;
use app\common\traits\Curd;
use fun\helper\StringHelper;
use think\App;
use think\facade\Request;
use addons\database\common\service\Backup;
use think\facade\Db;

class Index extends AddonsBackend
{
    use Curd;
    protected $db = '';
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->config=array(
            'path'     => './Data/',//数据库备份路径
            'part'     => 20971520,//数据库备份卷大小
            'compress' => 0,//数据库备份文件是否启用压缩 0不压缩 1 压缩
            'level'    => 9 //数据库备份文件压缩级别 1普通 4 一般  9最高
        );
        $this->db = new Backup($this->config);
    }


    public function index(){
        if(Request::isAjax()){
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
        if(Request::isAjax()){
            $list =  $this->db->fileList();
            return $result = ['code'=>0,'msg'=>lang('get info success'),'data'=>$list,'rel'=>1];
        }
        return view();
    }
    //执行还原数据库操作
    public function recover() {
        $time = $this->request->param('time');
        $list  = $this->db->getFile('timeverif',$time);
        $this->success('restore success') ;
    }

    //下载
    public function download() {
        $time = $this->request->param('time');
        $this->db->downloadFile($time);
    }
    //删除sql文件
    public function delete() {
        $time = $this->request->param('time');
        if($this->db->delFile($time)){
            $this->success(lang('delete success')) ;
        }else{
            $this->error(lang('delete fail')) ;

        }
    }

    /**
     * @return \think\response\View
     * sql 语句
     */
    public function querysql(){
        if($this->request->isPost()){
            $sql = $this->request->param('sql');
            if ($sql == '') {
                exit(lang('SQL can not be empty'));
            }
            $sql = str_replace('__PREFIX__', config('database.connections.mysql.prefix'), $sql);
            $sql = str_replace("\r", "", $sql);//换行
            $sqls = preg_split("/;[ \t]{0,}\n/i", $sql);//分号
            $maxnum = 100;
            $res = '';
            foreach ($sqls as $key => $val) {
                if (trim($val) == '') {
                    continue;
                }
                $val = rtrim($val, ';');
                $res .= "SQL：<span style='color:green;'>{$val}</span> ";
                if (preg_match("/^(select|explain)(.*)/i ", $val)) {
                    $limit = stripos(strtolower($val), "limit") !== false ? true : false;
                    $count =count( Db::query($val));
                    if ($count > 0) {
                        $list = Db::query($val . (!$limit && $count > $maxnum ? ' LIMIT ' . $maxnum : ''));
                    } else {
                        $list = [];
                    }
                    if ($count <= 0) {
                        $res .= lang('Query returned an empty result');
                    } else {
                        $res .= (lang('Total:%s', [$count]) . (!$limit && $count > $maxnum ? ',' . lang('Max output:%s', [$maxnum]) : ""));
                    }
                    foreach ($list as $kk => $vv) {
                        if (!$limit && $kk > $maxnum) {
                            break;
                        }
                        $res .= "<hr/>";
                        $res .= "<font color='red'>" . lang('Row:%s', [$kk+1]) . "</font><br />";
                        foreach ($vv as $k => $v) {
                            $res .= "<font color='blue'>{$k}：</font>{$v}<br/>\r\n";
                        }
                    }
                } else {
                    $count = Db::execute($val);
                }
                Db::listen(function($val, $runtime, $master) {
                    // 进行监听处理
                    echo  lang('Query affected rows took %s seconds', [$runtime]) . "<br />";
                });
            }
            echo $res;


        }else{
            return view();

        }

    }
}