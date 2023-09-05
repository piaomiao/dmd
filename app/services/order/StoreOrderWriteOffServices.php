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

namespace app\services\order;


use app\dao\order\StoreOrderDao;
use app\dao\order\StoreOrderWriteoffDao;
use app\services\activity\integral\StoreIntegralOrderServices;
use app\services\activity\integral\StoreIntegralOrderStatusServices;
use app\services\activity\combination\StorePinkServices;
use app\services\BaseServices;
use app\services\store\SystemStoreStaffServices;
use app\services\store\DeliveryServiceServices;
use app\services\user\UserServices;
use think\exception\ValidateException;

/**
 * 核销订单
 * Class StoreOrderWriteOffServices
 * @package app\sservices\order
 * @mixin StoreOrderDao
 */
class StoreOrderWriteOffServices extends BaseServices
{
	protected $orderDao;

	/**
	 * 构造方法
	 * @param StoreOrderWriteoffDao $dao
	 * @param StoreOrderDao $orderDao
	 */
    public function __construct(StoreOrderWriteoffDao $dao, StoreOrderDao $orderDao)
    {
		$this->dao = $dao;
        $this->orderDao = $orderDao;
    }

	/**
	 * 保存核销记录
	 * @param int $oid
	 * @param array $cartIds
	 * @param $data
	 * @param array $orderInfo
	 * @param array $cartInfo
	 * @return bool
	 */
	public function saveWriteOff(int $oid, array $cartIds, $data, array $orderInfo = [], array $cartInfo = [])
	{
		if (!$oid || !$data) {
			throw new ValidateException('缺少核销订单以及商品信息');
		}
		if (!$orderInfo) {
			/** @var StoreOrderServices $storeOrderServices */
			$storeOrderServices = app()->make(StoreOrderServices::class);
			$orderInfo = $storeOrderServices->get($oid);
		}
		if (!$orderInfo) {
			throw new ValidateException('核销订单不存在');
		}
		$orderInfo = is_object($orderInfo) ? $orderInfo->toArray() : $orderInfo;
		if (!$cartInfo) {
			/** @var StoreOrderCartInfoServices $cartInfoServices */
			$cartInfoServices = app()->make(StoreOrderCartInfoServices::class);
			if ($cartIds) {//商城存在部分核销
				$ids = array_unique(array_column($cartIds, 'cart_id'));
				$cartIds = array_combine($ids, $cartIds);
				//订单下原商品信息
				$cartInfo = $cartInfoServices->getCartColunm(['oid' => $orderInfo['id'], 'cart_id' => $ids, 'is_writeoff' => 0], '*', 'cart_id');
			} else {//整单核销
				$cartInfo = $cartInfoServices->getCartColunm(['oid' => $orderInfo['id'], 'is_writeoff' => 0], '*', 'cart_id');
			}
		}

		$writeOffDataAll = [];
		$writeOffData = ['uid' => $orderInfo['uid'], 'oid' => $oid, 'writeoff_code' => $orderInfo['verify_code'], 'add_time' => time()];
		foreach ($cartInfo as $cart) {
			$write = $cartIds[$cart['cart_id']] ?? [];
			$info = is_string($cart['cart_info']) ? json_decode($cart['cart_info'], true) : $cart['cart_info'];
			if (!$cartIds || $write) {
				$writeOffData['order_cart_id'] = $cart['id'];
				$writeOffData['writeoff_num'] = $write['cart_num'] ?? $cart['cart_num'];
				$writeOffData['type'] = $cart['type'];
				$writeOffData['relation_id'] = $cart['relation_id'];
				$writeOffData['product_id'] = $cart['product_id'];
				$writeOffData['product_type'] = $cart['product_type'];
				$writeOffData['writeoff_price'] = (float)bcmul((string)$info['truePrice'], (string)$writeOffData['writeoff_num'], 2);
				$writeOffData['staff_id'] = $data['staff_id'] ?? 0;
				$writeOffDataAll[] = $writeOffData;
			}
		}
		if ($writeOffDataAll) {
			$this->dao->saveAll($writeOffDataAll);
		}
		return true;
	}

}
