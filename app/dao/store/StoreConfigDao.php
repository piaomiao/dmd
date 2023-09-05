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

namespace app\dao\store;


use app\dao\BaseDao;
use app\model\store\StoreConfig;

//use crmeb\traits\SearchDaoTrait;

/**
 * Class StoreConfigDao
 * @package app\dao\store
 */
class StoreConfigDao extends BaseDao
{

//    use SearchDaoTrait;

    /**
     * @return string
     */
    protected function setModel(): string
    {
        return StoreConfig::class;
    }

    /**获取门店配置
     * @param $keys
     * @param $storeId
     * @return \crmeb\basic\BaseModel|mixed|\think\Model
     */
    public function searchs(array $keys, int $storeId)
    {
        return parent::search()->whereIn('key_name', $keys)->where('store_id', $storeId);
    }
}
