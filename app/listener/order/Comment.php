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
namespace app\listener\order;

use app\services\order\StoreOrderServices;
use app\services\order\StoreOrderStatusServices;
use app\services\system\admin\SystemAdminServices;
use crmeb\interfaces\ListenerInterface;

/**
 * 订单评价事件
 * Class PriceRevision
 * @package app\listener\order
 */
class Comment implements ListenerInterface
{
    /**
     * @param $event
     */
    public function handle($event): void
    {
        [$orderInfo] = $event;
		/** @var SystemAdminServices $systemAdmin */
        $systemAdmin = app()->make(SystemAdminServices::class);
        $systemAdmin->adminNewPush();

		//检测主订单 是否全部评价
		if ($orderInfo['pid']) {
			$id = (int)$orderInfo['pid'];
			/** @var StoreOrderServices $orderServices */
			$orderServices = app()->make(StoreOrderServices::class);
			//默认部分收货
			$take_data = [];
			$status_data = ['oid' => $id, 'change_time' => time()];
			if ($orderServices->count(['pid' => $id]) == $orderServices->count(['pid' => $id, 'status' => 4])) {
				$take_data = ['status' => 3];
				$status_data['change_type'] = 'check_order_over';
				$status_data['change_message'] = '用户评价';
				//改变主订单状态
				$orderServices->update($id, $take_data);
				/** @var StoreOrderStatusServices $statusService */
				$statusService = app()->make(StoreOrderStatusServices::class);
				//记录主订单状态
				$statusService->save($status_data);
			}
		}
    }
}
