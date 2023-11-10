<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2020 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------
namespace app\controller\api\v1\admin\video;

use app\Request;
use app\services\activity\video\VideoServices;
use app\services\product\product\StoreProductServices;
use app\services\product\category\StoreProductCategoryServices;
use app\services\store\SystemStoreStaffServices;
use think\facade\App;

/**
 * 短视频控制器
 * Class Video
 * @package app\controller\api\admin\video
 */
class Video
{
    public $services;
    /**
     * Video constructor.
     * @param App $app
     * @param VideoServices $service
     */
    public function __construct(App $app, VideoServices $service)
    {
        $this->services = $service;
    }

    /**
     * 视频列表
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index(Request $request)
    {
        $uid = $request->uid();
        $staff = app()->make(SystemStoreStaffServices::class)->getStaffInfoByUid($uid);
        if (!$staff) {
            return app('json')->fail('没有操作权限');
        }
        // $where = $request->getMore([
        //     // ['data', '', '', 'time'],
        //     ['keyword', ''],
        //     // ['is_verify', '']
        // ]);
        $where['type'] = 1;
        $where['relation_id'] = $staff->store_id;
        $where['is_del'] = 0;
        return  app('json')->successful('ok',  $this->services->sysPage($where));
    }

    /**
     * 获取视频信息
     * @param $id
     * @return mixed
     */
    public function detail(Request $request, $id)
    {
        if (!$id) return app('json')->fail('缺少参数');
        return  app('json')->successful('ok',  $this->services->getInfo((int)$id));
    }

    /**
     * 保存新增分类
     * @return mixed
     */ 
    public function save(Request $request, $id)
    {
        $uid = $request->uid();
        $staff = app()->make(SystemStoreStaffServices::class)->getStaffInfoByUid($uid);
        if (!$staff) {
            return app('json')->fail('没有操作权限');
        }
        $data = $request->postMore([
            ['image', ''],
            ['desc', ''],
            ['video_url', ''],
            ['product_id', []],
            ['is_show', 1],
            ['is_recommend', 0],
            ['sort', 0]
        ]);
        $data['type'] = 1;
        $data['relation_id'] = $staff->store_id;
        if ($id) {
            $info = $this->services->get($id);
            if (!$info) {
                app('json')->fail('视频不存在');
            }
            if ($info->relation_id != $staff->store_id) {
                return app('json')->fail('没有操作权限');
            }
            $data['is_verify'] = 0;
            $this->services->update($id, $data);
        } else {
            $data['add_time'] = time();
            $this->services->save($data);
        }
        return  app('json')->successful('保存视频成功!');
    }

    /**
     * 修改状态
     * @param string $status
     * @param string $id
     */
    public function set_show(Request $request, $id = '', $status = '')
    {
        if ($status == '' || $id == '') return app('json')->fail('缺少参数');
        $uid = $request->uid();
        $staff = app()->make(SystemStoreStaffServices::class)->getStaffInfoByUid($uid);
        if (!$staff) {
            return app('json')->fail('没有操作权限');
        }
        $info = $this->services->get($id);
        if (!$info) {
            app('json')->fail('视频不存在');
        }
        if ($info->relation_id != $staff->store_id) {
            return app('json')->fail('没有操作权限');
        }
        $this->services->update($id, ['is_show' => $status]);
        return  app('json')->successful($status == 1 ? '显示成功' : '隐藏成功');
    }


    /**
     * 删除视频
     * @param $id
     * @return mixed
     */
    public function delete(Request $request, $id)
    {
        if ($id == '') return app('json')->fail('缺少参数');
        $uid = $request->uid();
        $staff = app()->make(SystemStoreStaffServices::class)->getStaffInfoByUid($uid);
        if (!$staff) {
            return app('json')->fail('没有操作权限');
        }
        $info = $this->services->get($id);
        if (!$info) {
            app('json')->fail('视频不存在');
        }
        if ($info->relation_id != $staff->store_id) {
            return app('json')->fail('没有操作权限');
        }
        if ($info) {
            $this->services->update($id, ['is_del' => 1]);
        }
        return  app('json')->successful('删除成功!');
    }

        /**
     * 显示资源列表
     * @return mixed
     */
    public function product(Request $request)
    {
        $uid = $request->uid();
        $staff = app()->make(SystemStoreStaffServices::class)->getStaffInfoByUid($uid);
        if (!$staff) {
            return app('json')->fail('没有操作权限');
        }

        $where = $request->getMore([
            ['cate_id', ''],
            ['store_name', ''],
            ['type', 1, '', 'status'],
            ['is_live', 0],
            ['is_new', ''],
            ['is_vip_product', ''],
            ['is_presale_product', ''],
            ['store_label_id', '']
        ]);
        $where['is_show'] = 1;
        $where['is_del'] = 0;
		$where['type'] = 1;
		$where['relation_id'] = $staff->store_id;
        /** @var StoreProductCategoryServices $storeCategoryServices */
        $storeCategoryServices = app()->make(StoreProductCategoryServices::class);
        if ($where['cate_id'] !== '') {
            if ($storeCategoryServices->value(['id' => $where['cate_id']], 'pid')) {
                $where['sid'] = $where['cate_id'];
            } else {
                $where['cid'] = $where['cate_id'];
            }
        }
        unset($where['cate_id']);
        $list = app()->make(StoreProductServices::class)->searchList($where);

        return app('json')->successful('ok',$list);
    }
}
