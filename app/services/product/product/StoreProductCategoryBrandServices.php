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

namespace app\services\product\product;


use app\dao\product\product\StoreProductCategoryBrandDao;
use app\dao\product\product\StoreProductRelationDao;
use app\services\BaseServices;
use app\services\product\brand\StoreBrandServices;

/**
 * 商品关联关系
 * Class StoreProductCategoryBrandServices
 * @package app\services\product\product
 * @mixin StoreProductRelationDao
 */
class StoreProductCategoryBrandServices extends BaseServices
{


	/**
	* @param StoreProductCategoryBrandDao $dao
	*/
    public function __construct(StoreProductCategoryBrandDao $dao)
    {
        $this->dao = $dao;
    }

	/**
	 * 保存商品关联关系
	 * @param int $id
	 * @param array $cate_id
	 * @param array $brand_id
	 * @param int $status
	 * @return bool
	 */
    public function saveRelation(int $id, array $cate_id, array $brand_id, int $status = 1)
    {
        $cateData = [];
		if ($cate_id && $brand_id) {
			$time = time();
			/** @var StoreBrandServices $storeBrandServices */
			$storeBrandServices = app()->make(StoreBrandServices::class);
			$brands = $storeBrandServices->getColumn([['id', 'in', $brand_id]], 'id,brand_name', 'id');
			foreach ($cate_id as $cid) {
				foreach ($brand_id as $bid) {
					if ($brands[$bid] ?? [])
						$cateData[] = ['product_id' => $id, 'cate_id' => $cid, 'brand_id' => $bid, 'brand_name' => $brands[$bid]['brand_name'] ?? '', 'status' => $status, 'add_time' => $time];
				}
			}
		}
        $this->change($id, $cateData);
        return true;
    }

    /**
 	* 商品添加商品关联
	* @param int $id
	* @param array $cateData
	* @param int $type
	* @return bool
	*/
    public function change(int $id, array $cateData)
    {
        $this->dao->delete(['product_id' => $id]);
        if ($cateData) $this->dao->saveAll($cateData);
		return true;
    }

	/**
 	* 批量设置关联状态
	* @param array $ids
	* @param int $is_show
	* @param int $type
	* @return bool
	*/
	public function setShow(array $ids, int $is_show = 1, int $type = 1)
	{
		$this->dao->setShow($ids, $is_show, $type);
		return true;
	}



}
