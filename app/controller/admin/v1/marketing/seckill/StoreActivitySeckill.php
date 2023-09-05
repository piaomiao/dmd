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

namespace app\controller\admin\v1\marketing\seckill;

use app\controller\admin\AuthController;
use app\services\activity\seckill\StoreSeckillServices;
use app\services\activity\StoreActivityServices;
use app\services\product\sku\StoreProductAttrValueServices;
use crmeb\services\CacheService;
use think\facade\App;

/**
 * 秒杀活动
 * Class StoreActivitySeckill
 * @package app\controller\admin\v1\marketing\seckill
 */
class StoreActivitySeckill extends AuthController
{
    public function __construct(App $app, StoreActivityServices $services)
    {
        parent::__construct($app);
        $this->services = $services;
    }

    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function index()
    {
        $where = $this->request->getMore([
			['time', ''],
            [['status', 's'], ''],
            [['store_name', 's'], '']
        ]);
        $where['is_del'] = 0;
		$where['type'] = 1;
        return $this->success($this->services->systemPage($where));
    }

    /**
     * 详情
     * @param $id
     * @return mixed
     */
    public function read($id)
    {
        $info = $this->services->getSeckillInfo((int)$id);
        return $this->success(compact('info'));
    }

    /**
     * 保存秒杀商品
     * @param int $id
     */
    public function save($id = '')
    {
        $data = $this->request->postMore([
			['name', 's', ''],
            ['seckill_ids', []],
            ['image', ''],
            ['section_data', []],
            [['status', 'd'], 0],
            [['num', 'd'], 0],
            [['once_num', 'd'], 0],
            ['time_id', []],
			['applicable_type', 1],//适用门店类型
			['applicable_store_id', []],//适用门店IDS
        ]);
        $this->validate($data, \app\validate\admin\marketing\StoreActivitySeckillValidate::class, 'save');
        if ($data['section_data']) {
            [$start_time, $end_time] = $data['section_data'];
            if (strtotime($end_time) + 86400 < time()) {
                return $this->fail('活动结束时间不能小于当前时间');
            }
        }
        if ($data['num'] < $data['once_num']) {
            return $this->fail('限制单次购买数量不能大于总购买数量');
        }
		if ($data['applicable_type'] == 1) {
			$data['applicable_store_id'] = [];
		} elseif ($data['applicable_type'] == 2) {
			if (!$data['applicable_store_id']) {
				return $this->fail('请选择要适用门店');
			}
		}
		$expend = $data['seckill_ids'];
        $this->services->saveData((int)$id, $data, $expend);
        return $this->success('保存成功');
    }

    /**
     * 删除指定资源
     *
     * @param int $id
     * @return \think\Response
     */
    public function delete($id)
    {
        if (!$id) return $this->fail('缺少参数');
        $this->services->update($id, ['is_del' => 1]);
		/** @var StoreSeckillServices $storeSeckillServices */
		$storeSeckillServices = app()->make(StoreSeckillServices::class);
		//秒杀商品
		$seckillIds = $storeSeckillServices->getActivitySeckillIds(['activity_id' => $id]);
		if ($seckillIds) {
			/** @var StoreProductAttrValueServices $storeProductAttrValueServices */
			$storeProductAttrValueServices = app()->make(StoreProductAttrValueServices::class);
			foreach ($seckillIds as $seckillId) {
				$storeSeckillServices->update($seckillId, ['is_del' => 1]);
				$unique = $storeProductAttrValueServices->value(['product_id' => $seckillId, 'type' => 1], 'unique');
				if ($unique) {
					$name = 'seckill_' . $unique . '_1';
					/** @var CacheService $cache */
					$cache = app()->make(CacheService::class);
					$cache->del($name);
				}
				$this->services->cacheDelById($seckillId);
			}
			CacheService::redisHandler('product_attr')->clear();
		}
        return $this->success('删除成功!');
    }

    /**
     * 修改状态
     * @param $id
     * @param $status
     * @return mixed
     */
    public function set_status($id, $status)
    {
        $this->services->update($id, ['status' => $status]);

		/** @var StoreSeckillServices $storeSeckillServices */
		$storeSeckillServices = app()->make(StoreSeckillServices::class);
		//秒杀商品
		$seckillIds = $storeSeckillServices->getActivitySeckillIds(['activity_id' => $id]);
		foreach ($seckillIds as $seckillId) {
			$storeSeckillServices->update($seckillId, ['status' => $status]);
			//修改状态同步缓存
			$this->services->cacheSaveValue($seckillId, 'status', $status);
		}
        return $this->success($status == 0 ? '关闭成功' : '开启成功');
    }

}
