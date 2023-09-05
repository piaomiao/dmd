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

namespace app\dao\activity\table;


use app\dao\BaseDao;
use app\model\activity\table\TableSeats;

/**
 * 桌码
 * Class TableSeatsDao
 * @package app\dao\activity\table
 */
class TableSeatsDao extends BaseDao
{
    /**
     * 设置模型
     * @return string
     */
    protected function setModel(): string
    {
        return TableSeats::class;
    }

    /**获取餐桌座位数
     * @param int $storeId
     * @param array $where
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function tableSeats(int $storeId, array $where)
    {
        return $this->search($where)->where(['store_id' => $storeId])->order('number Asc')->select()->toArray();
    }
}
