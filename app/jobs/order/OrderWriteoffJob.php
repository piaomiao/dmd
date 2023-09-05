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

namespace app\jobs\order;


use app\services\order\StoreOrderDeliveryServices;
use app\services\order\StoreOrderWriteOffServices;
use crmeb\basic\BaseJobs;
use crmeb\traits\QueueTrait;
use think\facade\Log;

/**
 * 订单核销
 * Class OrderWriteoffJob
 * @package app\jobs
 */
class OrderWriteoffJob extends BaseJobs
{
    use QueueTrait;

	/**
	 * 写入核销记录
	 * @param int $oid
	 * @param array $cartIds
	 * @param $data
	 * @param array $orderInfo
	 * @param array $cartInfo
	 * @return bool
	 */
    public function doJob(int $oid, array $cartIds, $data, array $orderInfo = [], array $cartInfo = [])
    {
        try {
			/** @var StoreOrderWriteOffServices $storeOrderWriteoffServices */
			$storeOrderWriteoffServices = app()->make(StoreOrderWriteOffServices::class);
			$storeOrderWriteoffServices->saveWriteOff($oid, $cartIds, $data, $orderInfo, $cartInfo);
        } catch (\Throwable $e) {
            Log::error('写入订单核销记录失败失败，原因：' . $e->getMessage());
        }
        return true;
    }


}
