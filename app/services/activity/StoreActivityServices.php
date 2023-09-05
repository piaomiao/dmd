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
declare (strict_types=1);

namespace app\services\activity;


use app\dao\activity\StoreActivityDao;
use app\services\activity\seckill\StoreSeckillServices;
use app\services\activity\seckill\StoreSeckillTimeServices;
use app\services\BaseServices;
use app\services\product\product\StoreDescriptionServices;
use app\services\product\product\StoreProductServices;
use app\services\product\sku\StoreProductAttrResultServices;
use app\services\product\sku\StoreProductAttrServices;
use app\services\product\sku\StoreProductAttrValueServices;
use app\services\store\SystemStoreServices;
use crmeb\exceptions\AdminException;
use crmeb\services\CacheService;

/**
 * 活动
 * Class StoreActivityServices
 * @package app\services\activity
 * @mixin StoreActivityDao
 */
class StoreActivityServices extends BaseServices
{

	/**
	 * @var string[]
	 */
	public $typeName = [
		1 => '秒杀',
	];

    /**
     * StoreActivityServices constructor.
     * @param StoreActivityDao $dao
     */
    public function __construct(StoreActivityDao $dao)
    {
        $this->dao = $dao;
    }

	/**
	 * 活动列表
	 * @param array $where
	 * @return array
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\DbException
	 * @throws \think\db\exception\ModelNotFoundException
	 */
	public function systemPage(array $where)
	{
		[$page, $limit] = $this->getPageValue();
		$list = $this->dao->getList($where, '*', $page, $limit, ['seckill' => function ($query) {
			$query->field('id,activity_id');
		}]);
		$count = $this->dao->count($where);
		/** @var StoreSeckillTimeServices $seckillTimeServices */
		$seckillTimeServices = app()->make(StoreSeckillTimeServices::class);
		$timeList = $seckillTimeServices->time_list();
		if ($timeList) {
			$timeList = array_combine(array_column($timeList, 'id'), $timeList);
		}
		foreach ($list as &$item) {
			$item['product_count'] = count($item['seckill']);
			$item['time_id'] = is_string($item['time_id']) ? explode(',', $item['time_id']) : $item['time_id'];
			$item['time_list'] = [];
			foreach ($item['time_id'] as $time_id) {
				if (isset($timeList[$time_id])) $item['time_list'][] = $timeList[$time_id];
			}
			if ($item['status']) {
				if ($item['start_day'] > time())
					$item['start_name'] = '未开始';
				else if (bcadd((string)$item['end_day'], '86400') < time())
					$item['start_name'] = '已结束';
				else if (bcadd((string)$item['end_day'], '86400') > time() && $item['start_day'] < time()) {
					$item['start_name'] = '进行中';
				}
			} else $item['start_name'] = '已结束';
			switch ($item['type']) {
				case 1://秒杀
					$item['start_time'] = substr_replace($item['start_time'], ':', 2, 0);
					$item['end_time'] = substr_replace($item['end_time'], ':', 2, 0);
					break;
			}
			$item['start_day'] = date('Y-m-d', $item['start_day']);
			$item['end_day'] = date('Y-m-d', $item['end_day']);
//			$item['add_time'] = $item['add_time'] ? date('Y-m-d H:i:s', $item['add_time']) : '';
		}
		return compact('list', 'count');
	}

	/**
	 * 获取一条活动信息
	 * @param int $id
	 * @return array
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\DbException
	 * @throws \think\db\exception\ModelNotFoundException
	 */
	public function getInfo(int $id)
	{
		$info = $this->dao->get($id);
		if (!$info) {
			throw new AdminException('数据不存在');
		}
		$info = $info->toArray();
		return $info;
	}

	/**
	 * 获取一条秒杀活动
	 * @param int $id
	 * @return array
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\DbException
	 * @throws \think\db\exception\ModelNotFoundException
	 */
	public function getSeckillInfo(int $id)
	{
		$info = $this->getInfo($id);
		/** @var StoreSeckillServices $seckillServices */
		$seckillServices = app()->make(StoreSeckillServices::class);
		$seckill = $seckillServices->getList(['activity_id' => $id, 'is_del' => 0], 0, 0, ['attrValue']);
		$info['section_data'] = [date('Y-m-d', $info['start_day']), date('Y-m-d', $info['end_day'])];
		$productList = [];
		if ($seckill) {
			/** @var StoreProductServices $productServices */
			$productServices = app()->make(StoreProductServices::class);
			$productList = $productServices->searchList(['id' => array_column($seckill, 'product_id'), 'is_del' => 0, 'is_verify' => 1]);
			$productList = $productList['list'] ?? [];
			$seckill = array_combine(array_column($seckill, 'product_id'), $seckill);
			//放入秒杀商品价格
			foreach ($productList as &$product) {
				$seckillInfo = $seckill[$product['id']] ?? [];
				$attrValue = $product['attrValue'] ?? [];
				if ($seckillInfo && $attrValue) {
					$product['status'] = $seckillInfo['status'];
					$seckillAttrValue = $seckillInfo['attrValue'] ?? [];
					if ($seckillAttrValue) {
						$seckillAttrValue = array_combine(array_column($seckillAttrValue, 'suk'), $seckillAttrValue);
						foreach ($attrValue as &$value) {
							$value['quota'] = $seckillAttrValue[$value['suk']]['quota'] ?? 0;
							$value['quota_show'] = $seckillAttrValue[$value['suk']]['quota_show'] ?? 0;
							$value['price'] = $seckillAttrValue[$value['suk']]['price'] ?? 0;
							$value['cost'] = $seckillAttrValue[$value['suk']]['cost'] ?? 0;
							$value['ot_price'] = $seckillAttrValue[$value['suk']]['ot_price'] ?? 0;
						}
						$product['attrValue'] = $attrValue;
					}
				}
			}
		}
		$info['productList'] = $productList;
		//适用门店
		$info['stores'] = [];
		if (isset($info['applicable_type']) && ($info['applicable_type'] == 1 || ($info['applicable_type'] == 2 && isset($info['applicable_store_id']) && $info['applicable_store_id']))) {//查询门店信息
			$where = ['is_del' => 0];
			if ($info['applicable_type'] == 2) {
				$store_ids = is_array($info['applicable_store_id']) ? $info['applicable_store_id'] : explode(',', $info['applicable_store_id']);
				$where['id'] = $store_ids;
			}
			$field = ['id', 'cate_id', 'name', 'phone', 'address', 'detailed_address', 'image', 'is_show', 'day_time', 'day_start', 'day_end'];
			/** @var SystemStoreServices $storeServices */
			$storeServices = app()->make(SystemStoreServices::class);
			$storeData = $storeServices->getStoreList($where, $field, '', '', 0, ['categoryName']);
			$info['stores'] = $storeData['list'] ?? [];
		}
		return $info;
	}


	/**
	 * 保存活动信息
	 * @param int $id
	 * @param array $data
	 * @param array $expend
	 * @param int $type
	 * @return bool
	 */
	public function saveData(int $id, array $data, array $expend, int $type = 1)
	{
		if (!$data || !$expend) {
			throw new AdminException('缺少活动数据');
		}
		$section_data = $data['section_data'];
		$data['start_day'] = strtotime($section_data[0]);
		$data['end_day'] = strtotime($section_data[1]);
		unset($data['section_data']);
		$this->transaction( function () use ($id, $type, $data, $section_data, $expend) {
			if ($id) {
				$this->getInfo($id);
				$this->dao->update($id, $data);
				$this->clearAcivityAttr($id, $type);
			} else {
				$data['add_time'] = time();
				$res = $this->dao->save($data);
				$id = (int)$res->id;
			}
			//处理活动商品
			switch ($type) {
				case 1:
					$data['section_data'] = $section_data;
					$this->saveActivitySeckill($id, $expend, $data);
					break;
			}
		});

		return true;
	}

	/**
	 * 保存秒杀商品
	 * @param int $id
	 * @param array $data
	 * @param array $activity_data
	 * @return bool
	 */
	public function saveActivitySeckill(int $id, array $data, array $activity_data)
	{
		$productIds = array_column($data, 'id');
		/** @var StoreProductServices $productServices */
		$productServices = app()->make(StoreProductServices::class);
		$productList = $productServices->searchList(['id' => $productIds, 'is_del' => 0, 'is_verify' => 1]);
		$productList = $productList['list'] ?? [];
		//放入秒杀商品价格
		/** @var StoreSeckillServices $seckillServices */
		$seckillServices = app()->make(StoreSeckillServices::class);
		$data = array_combine($productIds, $data);
		foreach ($productList as &$product) {
			$attrInfo = $productServices->getProductRules((int)$product['id'], 0);
			$seckillData = [];
			$seckillData['activity_id'] = $id;
			$seckillData['product_id'] = $product['id'] ?? 0;
			$seckillData['title'] = $product['store_name'] ?? '';
			$seckillData['info'] = $product['store_info'] ?? '';
			$seckillData['unit_name'] = $product['unit_name'] ?? '';
			$seckillData['section_time'] = $activity_data['section_data'];
			$seckillData['images'] = $product['slider_image'] ?? '';
			$seckillData['description'] = $product['description'] ?? '';
			$seckillData['status'] = $data[$product['id']]['status'] ?? 1;
			$seckillData['time_id'] = $activity_data['time_id'] ?? [];
			$seckillData['num'] = $activity_data['num'] ?? 0;
			$seckillData['once_num'] = $activity_data['once_num'] ?? 0;
			$seckillData['delivery_type'] = $product['delivery_type'] ?? '';
			$seckillData['temp_id'] = $product['temp_id'];//运费设置
			$seckillData['freight'] = $product['freight'];//运费设置
            $seckillData['postage'] = $product['freight'];//邮费
            $seckillData['custom_form'] = $product['custom_form'];//自定义表单
			$seckillData['system_form_id'] = $product['system_form_id'];//系统表单ID
            $seckillData['product_type'] = $product['product_type'];//商品类型
			$seckillData['applicable_type'] = $activity_data['applicable_type'];//适用门店类型
			$seckillData['applicable_store_id'] = $activity_data['applicable_store_id'];//适用门店IDS

			$seckillData['items'] = $attrInfo['items'];
			$attrs = $attrInfo['attrs'] ?? [];
			if ($attrs) {
				$seckillAttrValue = $data[$product['id']]['attrValue'] ?? [];
				if (!$seckillAttrValue) {
					throw new AdminException('请选择商品规格');
				}
				foreach ($seckillAttrValue as $sattr) {
					if (!isset($sattr['price']) || !$sattr['price']) {
						throw new AdminException('请填写商品（'.$product['store_name'] .'）活动价');
					}
					if (!isset($sattr['quota']) || !$sattr['quota']) {
						throw new AdminException('请填写商品（'.$product['store_name'] .'）限量');
					}
				}
				$seckillAttrValue = array_combine(array_column($seckillAttrValue, 'suk'), $seckillAttrValue);
				foreach ($attrs as $attr) {
					$sku = implode(',', $attr['detail']);
					if(!isset($seckillAttrValue[$sku])) {
						throw new AdminException('请重新选择商品规格');
					}
					if (($seckillAttrValue[$sku][''] ?? 0) > $attr['stock']) {
						throw new AdminException('限量超过了商品库存');
					}
					$attr['quota'] = $attr['quota_show'] = $seckillAttrValue[$sku]['quota'] ?? 0;
					$attr['price'] = $seckillAttrValue[$sku]['price'] ?? 0;
					$attr['cost'] = $seckillAttrValue[$sku]['cost'] ?? 0;
					$attr['ot_price'] = $seckillAttrValue[$sku]['ot_price'] ?? 0;
					$seckillData['attrs'][] = $attr;
				}
			}
			$seckillServices->saveData(0, $seckillData);
		}
		return true;
	}

	/**
	 * 清空活动商品数据
	 * @param int $id
	 * @return bool
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\DbException
	 * @throws \think\db\exception\ModelNotFoundException
	 */
	public function clearAcivityAttr(int $id, int $type = 1)
	{
		/** @var StoreSeckillServices $seckillServices */
		$seckillServices = app()->make(StoreSeckillServices::class);
		$seckill = $seckillServices->getList(['activity_id' => $id, 'is_del' => 0]);
		if ($seckill) {
			$seckillIds = array_column($seckill, 'id');
			/** @var StoreProductAttrResultServices $storeProductAttrResultServices */
        	$storeProductAttrResultServices = app()->make(StoreProductAttrResultServices::class);
			/** @var StoreDescriptionServices $storeDescriptionServices */
			$storeDescriptionServices = app()->make(StoreDescriptionServices::class);
			/** @var StoreProductAttrServices $storeProductAttrServices */
			$storeProductAttrServices = app()->make(StoreProductAttrServices::class);
			/** @var StoreProductAttrValueServices $storeProductAttrValueServices */
			$storeProductAttrValueServices = app()->make(StoreProductAttrValueServices::class);
			$where = ['product_id' => $seckillIds, 'type' => $type];
			$storeProductAttrResultServices->delete($where);
			$storeDescriptionServices->delete($where);
			$storeProductAttrServices->delete($where);
			$storeProductAttrValueServices->delete($where);
			$seckillServices->delete(['activity_id' => $id, 'is_del' => 0]);
			$seckillServices->cacheTag()->clear();
		}
		CacheService::redisHandler('product_attr')->clear();
		return true;
	}


	/**
	 * 判断时间段是否存在秒杀活动
	 * @param array $where
	 * @param int $type
	 * @return bool
	 * @throws \think\db\exception\DbException
	 */
	public function existenceActivity(array $where, int $type = 1): bool
	{
		$where['is_del'] = 0;
		if (!isset($where['type'])) $where['type'] = $type;
		return $this->dao->checkActivity($where);
	}



}
