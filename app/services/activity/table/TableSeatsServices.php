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

namespace app\services\activity\table;

use app\services\BaseServices;
use app\dao\activity\table\TableSeatsDao;

/**
 *
 * Class TableSeatsServices
 * @package app\services\activity\table
 * @mixin TableSeatsDao
 */
class TableSeatsServices extends BaseServices
{

    /**
     * UserCollageServices constructor.
     * @param TableSeatsDao $dao
     */
    public function __construct(TableSeatsDao $dao)
    {
        $this->dao = $dao;
    }

    /**获取餐桌座位数
     * @param int $storeId
     * @return array
     */
    public function tableSeatsList(int $storeId)
    {
        return $this->dao->tableSeats($storeId, []);
    }
}
