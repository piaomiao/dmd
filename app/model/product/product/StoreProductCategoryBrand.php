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

namespace app\model\product\product;

use app\model\product\brand\StoreBrand;
use app\model\product\category\StoreProductCategory;
use app\model\product\ensure\StoreProductEnsure;
use app\model\product\label\StoreProductLabel;
use app\model\product\specs\StoreProductSpecs;
use app\model\user\label\UserLabel;
use crmeb\traits\ModelTrait;
use think\Model;

/**
 *  商品、分类、品牌
 * Class StoreProductCategoryBrand
 * @package app\model\product\product
 */
class StoreProductCategoryBrand extends Model
{
    use ModelTrait;

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'store_product_category_brand';


	/**
     * 商品ID搜索器
     * @param Model $query
     * @param $value
     */
    public function searchProductIdAttr($query, $value)
    {
		if ($value) {
            if (is_array($value)) {
                $query->whereIn('product_id', $value);
            } else {
                $query->where('product_id', $value);
            }
        }
    }

    /**
     * 分类ID搜索器
     * @param Model $query
     * @param $value
     */
    public function searchCateIdAttr($query, $value)
    {
        if ($value) {
            if (is_array($value)) {
                $query->whereIn('cate_id', $value);
            } else {
                $query->where('cate_id', $value);
            }
        }
    }

	/**
	 * 品牌ID搜索器
	 * @param Model $query
	 * @param $value
	 */
	public function searchBrandIdAttr($query, $value)
	{
		if ($value) {
			if (is_array($value)) {
				$query->whereIn('brand_id', $value);
			} else {
				$query->where('brand_id', $value);
			}
		}
	}

	/**
	 * 状态搜索器
	 * @param Model $query
	 * @param $value
	 * @param $data
	 */
	public function searchStatusAttr($query, $value, $data)
	{
		if ($value != '') $query->where('status', $value);
	}


}
