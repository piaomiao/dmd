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

namespace app\jobs\product;



use app\services\product\branch\StoreBranchProductServices;
use app\services\product\product\StoreProductServices;
use crmeb\basic\BaseJobs;
use crmeb\traits\QueueTrait;
use think\facade\Log;

/**
 * 商品同步到门店
 * Class ProductSyncStoreJob
 * @package app\jobs\product
 */
class ProductSyncStoreJob extends BaseJobs
{
    use QueueTrait;

	/**
	* 同步某个商品到某个门店
	* @param $product_id
	* @param $store_id
	* @return bool
	 */
    public function syncProduct($product_id, $store_id)
    {
		$product_id = (int)$product_id;
		$store_id = (int)$store_id;
		if (!$product_id || !$store_id) {
			return true;
		}
		try {
			/** @var StoreBranchProductServices $storeBranchProductServices */
			$storeBranchProductServices = app()->make(StoreBranchProductServices::class);
			$storeBranchProductServices->syncProduct($product_id, $store_id);
		} catch (\Throwable $e) {
			Log::error('同步商品到门店发生错误,错误原因:' . $e->getMessage());
		}
		return true;
    }

	/**
	 * 新增门店:同步平台商品(选择多个，或者所有)
	 * @param $store_id
	 * @param $product_id
	 * @return bool
	 */
	public function syncProducts($store_id, $product_id = [])
	{
		if (!$store_id) {
			return true;
		}
		try {
			$where = ['is_show' => 1, 'is_del' => 0, 'type' => 0, 'product_type' => [0, 4], 'is_verify' => 1, 'pid' => 0];
			if ($product_id) {//同步某些商品
				$where['id'] = is_array($product_id) ? $product_id : explode(',', $product_id);
			}
			/** @var StoreProductServices $productServices */
			$productServices = app()->make(StoreProductServices::class);
			$products = $productServices->getSearchList($where, 0, 0, ['id'], '', []);
			if ($products) {
				$productIds = array_column($products, 'id');
				foreach ($productIds as $id) {
					ProductSyncStoreJob::dispatchDo('syncProduct', [$id, $store_id]);
				}
			}
		} catch (\Throwable $e) {
			Log::error('同步商品到门店发生错误,错误原因:' . $e->getMessage());
		}
		return true;
	}

	/**
 	* 同步一个商品到多个门店
	* @param $product_id
	* @param $store_ids
	* @return bool
	 */
	public function syncProductToStores($product_id, $applicable_type = 0, $store_ids = [])
	{
		$product_id = (int)$product_id;
		if (!$product_id) {
			return true;
		}
		try {
			if ($store_ids) {//同步门店
				$store_ids = is_array($store_ids) ? $store_ids : explode(',', $product_id);
			}
			/** @var StoreBranchProductServices $storeBranchProductServices */
			$storeBranchProductServices = app()->make(StoreBranchProductServices::class);
			$storeBranchProductServices->syncProductToStores($product_id, $applicable_type, $store_ids);
		} catch (\Throwable $e) {
			Log::error('同步商品到门店发生错误,错误原因:' . $e->getMessage());
		}
		return true;
	}

}
