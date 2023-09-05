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

namespace app\jobs\system;


use crmeb\basic\BaseJobs;
use crmeb\traits\QueueTrait;
use app\webscoket\SocketPush;

/**
 * socket推送
 * Class SocketPushJob
 * @package app\jobs\system
 */
class SocketPushJob extends BaseJobs
{
    use QueueTrait;

    public function sendApplyRefund($order)
	{
		if (!$order) {
			return true;
		}
		if ($order['store_id']) {
			//向门店后台发送退款订单消息
			try {
				SocketPush::store()->to($order['store_id'])->data(['order_id' => $order['order_id']])->type('NEW_REFUND_ORDER')->push();
			} catch (\Exception $e) {
			}
		} elseif ($order['supplier_id']) {
			//向门店后台发送退款订单消息
			try {
				SocketPush::instance()->setUserType('supplier')->to($order['supplier_id'])->data(['order_id' => $order['order_id']])->type('NEW_REFUND_ORDER')->push();
			} catch (\Exception $e) {
			}
		} else {
			//向后台发送退款订单消息
			try {
				SocketPush::admin()->data(['order_id' => $order['order_id']])->type('NEW_REFUND_ORDER')->push();
			} catch (\Exception $e) {
			}
		}
		return true;
	}

}
