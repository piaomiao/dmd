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


use app\jobs\activity\LuckLotteryJob;
use app\jobs\activity\TableQrcodeJob;
use app\jobs\agent\AgentJob;
use app\jobs\order\OrderCreateAfterJob;
use app\jobs\order\OrderDeliveryJob;
use app\jobs\order\OrderJob;
use app\jobs\order\OrderPayHandelJob;
use app\jobs\order\OrderStatusJob;
use app\jobs\order\OrderSyncJob;
use app\jobs\order\OrderTakeJob;
use app\jobs\order\ShareOrderJob;
use app\jobs\activity\pink\PinkJob;
use app\jobs\product\ProductCouponJob;
use app\jobs\product\ProductLogJob;
use app\jobs\activity\StorePromotionsJob;
use app\jobs\system\CapitalFlowJob;
use app\services\order\StoreOrderInvoiceServices;
use app\services\order\StoreOrderServices;
use crmeb\interfaces\ListenerInterface;

/**
 * 订单支付事件
 * Class Pay
 * @package app\listener\order
 */
class Pay implements ListenerInterface
{
    public function handle($event): void
    {
        [$orderInfo, $userInfo] = $event;

        if ($orderInfo['activity_id'] && !$orderInfo['refund_status']) {
            switch ($orderInfo['type']) {
                case 3://拼团
                    //创建拼团
                    PinkJob::dispatchDo('createPink', [$orderInfo]);
                    break;
                case 8://抽奖
                    //抽奖订单中奖记录处理
                    LuckLotteryJob::dispatchDo('updateLotteryRecord', [$orderInfo['id'], $orderInfo]);
                    break;
                case 10://桌码
                    //桌码处理
                    $order = app()->make(StoreOrderServices::class);
                    $order->update($orderInfo['id'], ['status' => 2]);
                    TableQrcodeJob::dispatchDo('updateTableQrcode', [$orderInfo['id'], $orderInfo]);
                    break;
            }
        }
        //卡密、虚拟、次卡商品订单处理
        OrderPayHandelJob::dispatch([$orderInfo]);
        //自动分配订单
        ShareOrderJob::dispatch([$orderInfo]);
        //门店虚拟用户
        if ($orderInfo['uid']) {
            //赠送商品关联优惠卷
            ProductCouponJob::dispatch([$orderInfo]);
            //修改开票数据支付状态
            $orderInvoiceServices = app()->make(StoreOrderInvoiceServices::class);
            $orderInvoiceServices->update(['order_id' => $orderInfo['id']], ['is_pay' => 1]);
            //支付成功后计算商品节省金额
            OrderJob::dispatchDo('setEconomizeMoney', [$orderInfo]);
            //支付成功处理自己、上级分销等级升级
            AgentJob::dispatch([(int)$orderInfo['uid']]);
            //支付成功后更新用户支付订单数量
            OrderJob::dispatchDo('setUserPayCountAndPromoter', [$orderInfo]);
            //优惠活动赠送优惠卷
            StorePromotionsJob::dispatchDo('give', [$orderInfo]);
            //优惠活动关联用户标签设置
            StorePromotionsJob::dispatchDo('setUserLabel', [$orderInfo]);
        }
        if ($orderInfo['shipping_type'] == 4) {
            //订单发货
            OrderDeliveryJob::dispatch([$orderInfo, [], 4]);
            //订单收货
            OrderTakeJob::dispatchSece(5, [$orderInfo]);
            //清理购物车
            $cartIds = [];
            if (isset($orderInfo['cart_id']) && $orderInfo['cart_id']) {
                $cartIds = is_string($orderInfo['cart_id']) ? json_decode($orderInfo['cart_id'], true) : $orderInfo['cart_id'];
            }
            OrderCreateAfterJob::dispatchDo('delCartAndUpdateAddres', [$orderInfo, ['cartIds' => $cartIds, 'delCart' => true]]);
        }
        //支付成功后其他事件处理
        OrderJob::dispatchDo('otherTake', [$orderInfo]);
        //支付成功后向管理员发送模板消息
        OrderJob::dispatchDo('sendServicesAndTemplate', [$orderInfo]);
        //订单记录
        OrderStatusJob::dispatchDo('savePayStatus', [$orderInfo['id']]);
        //支付记录
        ProductLogJob::dispatch(['pay', ['uid' => $orderInfo['uid'], 'order_id' => $orderInfo['id']]]);
        //记录资金流水队列
		CapitalFlowJob::dispatch([$orderInfo, 'order']);
        // 同步订单
        if (sys_config('erp_open')) {
            OrderSyncJob::dispatchDo('syncOrder', [(int)$orderInfo['id']]);
        }
    }
}
