<?php

namespace app\jobs\system;

use app\services\system\CapitalFlowServices;
use app\services\user\UserServices;
use crmeb\basic\BaseJobs;
use crmeb\traits\QueueTrait;
use think\facade\Log;

/**
 * 平台资金流水记录
 * Class CapitalFlowJob
 * @package app\jobs\system
 */
class CapitalFlowJob extends BaseJobs
{
    use QueueTrait;

	/**
	 * @param $order
	 * @param $type
	 * @return bool
	 */
    public function doJob($order, $type)
    {
		if (!$order || !$type) {
			return true;
		}
		$data = [];
        try {
			switch ($type) {
				case 'order'://订单
					if (in_array($order['pay_type'], ['weixin', 'alipay', 'offline'])) {
						$data = [
							'order_id' => $order['order_id'],
							'store_id' => $order['store_id'] ?? 0,
							'uid' => $order['uid'] ?? 0,
							'nickname' => $order['nickname'] ?? '游客' . time(),
							'phone' => $order['phone'] ?? '',
							'price' => $order['pay_price'],
							'pay_type' => $order['pay_type'],
						];
					}
					break;
				case 'refund'://退款
					//退款写入资金流水
					if (in_array($order['pay_type'], ['weixin', 'alipay', 'offline'])) {
						$userServices = app()->make(UserServices::class);
						$userInfo = $userServices->get($order['uid']);
						//记录资金流水队列
						$data = [
							'order_id' => $order['order_id'],
							'store_id' => $order['store_id'],
							'uid' => $order['uid'],
							'nickname' => $userInfo['nickname'],
							'phone' => $userInfo['phone'],
							'price' => $order['refund_price'],
							'pay_type' => $order['pay_type'],
						];
					}
					break;
				case 'pay_member'://购买付费会员
					if ($order['pay_type'] != 'yue') {
						$userServices = app()->make(UserServices::class);
						$userInfo = $userServices->get($order['uid']);
						$data = [
							'order_id' => $order['order_id'],
							'store_id' => 0,
							'uid' => $order['uid'],
							'nickname' => $userInfo['nickname'],
							'phone' => $userInfo['phone'],
							'price' => $order['pay_price'],
							'pay_type' => $order['pay_type'],
						];
					}
					break;
				case 'recharge'://充值
					$data = [
						'order_id' => $order['order_id'],
						'store_id' => $order['store_id'] ?? 0,
						'uid' => $order['uid'],
						'nickname' => $order['nickname'],
						'phone' => $order['phone'],
						'price' => $order['price'],
						'pay_type' => $order['recharge_type'] ?? 'weixin',
						'add_time' => time(),
					];
					break;
				case 'refund_recharge'://充值退款
					$data = [
						'order_id' => $order['order_id'],
						'store_id' => $order['store_id'] ?? 0,
						'uid' => $order['uid'],
						'nickname' => $order['nickname'],
						'phone' => $order['phone'],
						'price' => $order['price'],
						'pay_type' => $order['recharge_type'] ?? 'weixin',
					];
					break;
				case 'extract'://提现
					$data = $order;
					break;
				case 'luck'://抽奖
					$data = $order;
					break;
			}
			if ($data) {
				/** @var CapitalFlowServices $capitalFlowServices */
				$capitalFlowServices = app()->make(CapitalFlowServices::class);
				$capitalFlowServices->setFlow($data, $type);
			}
        } catch (\Throwable $e) {
            Log::error('写入资金流水错误:[' . class_basename($this) . ']' . $e->getMessage());
        }
        return true;

    }
}
