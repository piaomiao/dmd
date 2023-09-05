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

namespace app\dao\product\product;

use app\dao\BaseDao;
use app\model\product\product\StoreProductCategoryBrand;
use app\model\product\product\StoreProductRelation;

/**
 * 商品关联关系dao
 * Class StoreProductCategoryBrandDao
 * @package app\dao\product\product
 */
class StoreProductCategoryBrandDao extends BaseDao
{
    /**
     * 设置模型
     * @return string
     */
    protected function setModel(): string
    {
        return StoreProductCategoryBrand::class;
    }

	 /**
     * 获取所有的分销员等级
     * @param array $where
     * @param string $field
     * @param array $with
     * @param int $page
     * @param int $limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getList(array $where = [], string $field = '*', array $with = [], int $page = 0, int $limit = 0)
    {
        return $this->search($where)->field($field)
		->when($with, function ($query) use ($with) {
			$query->with($with);
		})->when($page && $limit, function ($query) use ($page, $limit) {
			$query->page($page, $limit);
		})->select()->toArray();
    }

    /**
     * 保存数据
     * @param array $data
     * @return mixed|void
     */
    public function saveAll(array $data)
    {
        $this->getModel()->insertAll($data);
    }

	/**
	 * 设置
	 * @param array $ids
	 * @param int $is_show
	 * @param string $key
	 * @return \crmeb\basic\BaseModel
	 */
	public function setShow(array $ids, int $is_show = 1, string $key = 'product_id')
	{
		return $this->getModel()->whereIn($key, $ids)->update(['status' => $is_show]);
	}

}
