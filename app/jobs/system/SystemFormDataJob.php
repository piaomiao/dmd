<?php

namespace app\jobs\system;

use app\services\system\form\SystemFormDataServices;
use crmeb\basic\BaseJobs;
use crmeb\traits\QueueTrait;
use think\facade\Log;

/**
 * 系统表单数据收集
 * Class SystemFormDataJob
 * @package app\jobs\system
 */
class SystemFormDataJob extends BaseJobs
{
    use QueueTrait;

	/**
	 * @param $order
	 * @param $type
	 * @return bool
	 */
    public function doJob($data, $type = 1)
    {
		if (!$data || !(int)$type) {
			return true;
		}
		$form = [];
        try {
			switch ($type) {
				case '1'://订单
						$form = [
							'type' => 1,
							'relation_id' => $data['id'] ?? 0,
							'uid' => $data['uid'] ?? 0,
							'system_form_id' => $data['system_form_id'] ?? 0,
							'value' => is_string($data['custom_form']) ? json_decode($data['custom_form'], true) : $data['custom_form']
						];
					break;
			}
			if ($form) {
				/** @var SystemFormDataServices $systemFormDataServices */
				$systemFormDataServices = app()->make(SystemFormDataServices::class);
				$systemFormDataServices->setFormData($form, (int)$type);
			}
        } catch (\Throwable $e) {
            Log::error('写入系统表单收集数据失败，错误:' . $e->getMessage());
        }
        return true;

    }
}
