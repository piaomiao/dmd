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


use app\services\product\label\StoreProductLabelAuxiliaryServices;
use app\services\product\product\StoreProductCategoryBrandServices;
use crmeb\basic\BaseJobs;
use crmeb\traits\QueueTrait;
use think\facade\Log;

/**
 * 商品、分类、品牌
 * Class ProductCategoryBrandJob
 * @package app\jobs\product
 */
class ProductCategoryBrandJob extends BaseJobs
{
    use QueueTrait;

	/**
	 * @param int $id
	 * @param array $cate_id
	 * @param array $brand_id
	 * @return bool
	 */
    public function doJob(int $id, array $cate_id, array $brand_id, int $status = 1)
    {
        if (!$id) {
            return true;
        }
        try {
            /** @var StoreProductCategoryBrandServices $services */
            $services = app()->make(StoreProductCategoryBrandServices::class);
            //标签关联
			$services->saveRelation($id, $cate_id, $brand_id, $status);
        } catch (\Throwable $e) {
            Log::error('写入商品、分类、品牌关联发生错误,错误原因:' . $e->getMessage());
        }
        return true;
    }

	/**
	 * 处理关联数据
	 * @param int $id
	 * @param string $field
	 * @param string $updateField
	 * @param int $status
	 * @return bool
	 */
	public function setShow(int $id, string $field = 'cate_id', string $updateField = 'status', int $status = 1)
	{
		if (!$id || !$field) {
			return true;
		}
		try {
			if (!in_array($field, ['product_id', 'cate_id', 'brand_id'])) {
				return true;
			}
			if (!in_array($updateField, ['status', 'is_del'])) {
				return true;
			}
			/** @var StoreProductCategoryBrandServices $services */
			$services = app()->make(StoreProductCategoryBrandServices::class);
			$where = [$field => $id];
			if ($updateField == 'is_del') {
				$services->delete($where);
			} else {
				$services->update($where, [$updateField => $status]);
			}
		} catch (\Throwable $e) {
			Log::error('修改商品、分类、品牌关联发生错误,错误原因:' . $e->getMessage());
		}
		return true;
	}

}
