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

namespace app\jobs\activity;


use app\services\activity\collage\UserCollageServices;
use app\services\activity\table\TableQrcodeServices;
use crmeb\basic\BaseJobs;
use crmeb\traits\QueueTrait;
use think\facade\Log;

/**
 * 营销：桌码
 * Class TableQrcodeJob
 * @package app\jobs\activity
 */
class TableQrcodeJob extends BaseJobs
{

    use QueueTrait;

	/**
	 * @param $oid
	 * @param $orderInfo
	 * @return bool|void
	 */
	public function updateTableQrcode($oid, $orderInfo)
	{
		if (!$oid) {
			return true;
		}
		try {
			/** @var UserCollageServices $collageServices */
			$collageServices = app()->make(UserCollageServices::class);
			$TableCode = $collageServices->get($orderInfo['activity_id']);
			/** @var TableQrcodeServices $qrcodeService */
			$qrcodeService = app()->make(TableQrcodeServices::class);
			$qrcodeService->update($TableCode['qrcode_id'], ['is_use' => 0, 'eat_number' => 0, 'order_time' => 0]);
		} catch (\Throwable $e) {
			Log::error('处理桌码状态失败,失败原因:' . $e->getMessage());
		}
		return true;
	}

}
