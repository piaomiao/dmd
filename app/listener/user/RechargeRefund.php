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

namespace app\listener\user;

use app\jobs\store\StoreFinanceJob;
use app\jobs\system\CapitalFlowJob;
use crmeb\interfaces\ListenerInterface;

/**
 * 用户充值退款事件
 * Class RechargeRefund
 * @package app\listener\user
 */
class RechargeRefund implements ListenerInterface
{
    /**
     * 用户充值事件
     * @param $event
     */
    public function handle($event): void
    {
        [$order, $data] = $event;

        if ($order['store_id']) {
            //门店充值退款流水
            StoreFinanceJob::dispatch([$order, 5, $data['refund_price'] ?? 0.00]);
        }
		//记录资金流水
        CapitalFlowJob::dispatch([$order, 'refund_recharge']);

        //提醒推送
        event('notice.notice', [['user_type' => strtolower($order['recharge_type']), 'data' => $data, 'UserRecharge' => $order], 'recharge_order_refund_status']);

    }
}
