<?php
/**
 * funadmin
 * ============================================================================
 * 版权所有 2018-2027 funadmin，并保留所有权利。
 * 网站地址: https://www.funadmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 */

namespace app\cms\model;

use app\common\model\BaseModel;
use fun\helper\FileHelper;
use think\facade\Db;
use think\model\concern\SoftDelete;

class Category extends BaseModel
{
    use SoftDelete;
    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;
    protected $name = 'addons_cms_category';

    public static $category;

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

    public function module(){

        return $this->belongsTo(Module::class,'moduleid','id');
    }

    public static function fixCate()
    {
        self::$category = $category = array();
        //取出需要处理的栏目数据
        $category = self::order('sort ASC, id ASC')->select()->toArray();
        self::set_category($category);
        if (is_array(self::$category)) {
            foreach (self::$category as $catid => $cat) {
                //获取父栏目ID列表
                $arrpid = self::get_arrpid($catid);
                //获取子栏目ID列表
                $arrchildid = self::get_arrchildid($catid);
                //检查所有父id 子栏目id 等相关数据是否正确，不正确更新
                if (self::$category[$catid]['arrpid'] != $arrpid || self::$category[$catid]['arrchildid'] != $arrchildid) {
                    self::update(['arrpid' => $arrpid, 'arrchildid' => $arrchildid, 'id' => $catid]);
                }

            }
        }
        return true;
    }

    public static function set_category($category)
    {
        if ($category) {
            foreach ($category as $v) {
                $category[$v['id']] = $v;
            }
            self::$category = $category;
            cache('cms_category', $category);
        }
    }

    //获取父id
    public static function get_arrpid($id, $arrpid = '')
    {
        if (empty(self::$category)) {
            self::$category = cache('Category');
        }
        if (!is_array(self::$category) || !isset(self::$category[$id])) return false;
        $pid = self::$category[$id]['pid'];
        $arrpid = $arrpid ? $pid . ',' . $arrpid : $pid;
        if ($pid) {
            $arrpid = self::get_arrpid($pid, $arrpid);
        } else {
            self::$category[$id]['arrpid'] = $arrpid;
        }
        return $arrpid;
    }

    //获取子id
    public static function get_arrchildid($catid)
    {
        if (empty(self::$category)) {
            self::$category = cache('Category');
        }
        $arrchildid = $catid;
        if (is_array(self::$category)) {
            foreach (self::$category as $id => $cat) {
                if ($cat['pid'] && $catid != $id) {
                    $arrpids = explode(',', $cat['arrpid']);
                    if (in_array($catid, $arrpids)) {
                        $arrchildid .= ',' . $id;
                    }
                }
            }
        }
        return $arrchildid;
    }


    //刷新栏目索引缓存
    public static function flashCache()
    {
        FileHelper::delDir(root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'temp');
        FileHelper::delDir(root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'cache');
        $data = self::order("sort ASC")->select();
        $ids = array();
        foreach ($data as $r) {
            $ids[$r['id']] = array(
                'id' => $r['id'],
                'parentid' => $r['parentid'],
            );
        }
        cache("cms_category", $ids);
        return $ids;
    }


    //获取栏目
    public static function getCategory($cateid, $returnfield = '', $cache = true)
    {
        if (empty($cateid)) {
            return false;
        }
        //读取数据
        $data = self::cache($cache)->find($cateid);
        if ($returnfield) {
            return $data[$returnfield];
        } else {
            return $data;
        }


    }

}
