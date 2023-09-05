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
namespace app\controller\api\v1\activity;


use app\Request;
use app\services\activity\seckill\StoreSeckillServices;
use app\services\activity\seckill\StoreSeckillTimeServices;
use app\services\other\QrcodeServices;
use crmeb\services\GroupDataService;


/**
 * 秒杀商品类
 * Class StoreSeckillController
 * @package app\api\controller\activity
 */
class StoreSeckillController
{

    protected $services;

    public function __construct(StoreSeckillServices $services)
    {
        $this->services = $services;
    }

	/**
	 * 秒杀商品时间区间
	 * @param StoreSeckillTimeServices $seckillTimeServices
	 * @return \think\Response
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\DbException
	 * @throws \think\db\exception\ModelNotFoundException
	 */
	public function index(StoreSeckillTimeServices $seckillTimeServices)
	{
		$seckillTime = $seckillTimeServices->time_list();
		$seckillTimeIndex = -1;
		$timeCount = count($seckillTime);//总数
		$unTimeCunt = 0;//即将开始
		if ($timeCount) {
			$today = date('Y-m-d');
			$currentHour = date('Hi');
			foreach ($seckillTime as $key => &$value) {
				$start = str_replace(':', '', $value['start_time']);
				$end = str_replace(':', '', $value['end_time']);
				if ($currentHour >= $start && $currentHour <= $end) {
					$value['state'] = '抢购中';
					$value['status'] = 1;
					if ($seckillTimeIndex == -1) $seckillTimeIndex = $key;
				} else if ($currentHour < $start) {
					$value['state'] = '即将开始';
					$value['status'] = 2;
					$unTimeCunt += 1;
				} else if ($currentHour >= $end) {
					$value['state'] = '已结束';
					$value['status'] = 0;
				}
				$value['time'] = $value['start_time'];
				$value['stop'] = strtotime($today. ' '. $value['end_time']);
			}
			//有时间段但是都不在抢购中
			if ($seckillTimeIndex == -1 && $currentHour <= (int)$seckillTime[$timeCount - 1]['time'] ?? 0) {
				if ($currentHour < (int)$seckillTime[0]['time'] ?? 0) {//当前时间
					$seckillTimeIndex = 0;
				} elseif ($unTimeCunt) {//存在未开始的
					foreach ($seckillTime as $key => $item) {
						if ($item['status'] == 2) {
							$seckillTimeIndex = $key;
							break;
						}
					}
				} else {
					$seckillTimeIndex = $timeCount - 1;
				}
			}
		}
		$data['lovely'] = sys_config('seckill_header_banner');
		if (strstr($data['lovely'], 'http') === false && strlen(trim($data['lovely']))) $data['lovely'] = sys_config('site_url') . $data['lovely'];
		$data['lovely'] = str_replace('\\', '/', $data['lovely']);
		$data['seckillTime'] = $seckillTime;
		$data['seckillTimeIndex'] = $seckillTimeIndex;
		return app('json')->successful($data);
	}

    /**
     * 秒杀商品列表
     * @param Request $request
     * @param $time
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function lst($time)
    {
        if (!$time) return app('json')->fail('参数错误');
        $seckillInfo = $this->services->getListByTime($time);
        return app('json')->successful(get_thumb_water($seckillInfo, 'mid'));
    }

    /**
     * 秒杀商品详情
     * @param Request $request
     * @param $id
     * @return mixed
     */
    public function detail(Request $request, $id)
    {
        $data = $this->services->seckillDetail($request, $id);
        return app('json')->successful($data);
    }

    /**
     * 获取秒杀小程序二维码
     * @param Request $request
     * @param $id
     * @return mixed
     */
    public function code(Request $request, $id, $stop_time = '')
    {
        /** @var QrcodeServices $qrcodeService */
        $qrcodeService = app()->make(QrcodeServices::class);
        $url = $qrcodeService->getRoutineQrcodePath($id, $request->uid(), 2, compact('stop_time'));
        if ($url) {
            return app('json')->success(['code' => $url]);
        } else {
            return app('json')->success(['code' => '']);
        }
    }

	/**
	 * @param Request $request
	 * @param $id
	 * @return mixed
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\DbException
	 * @throws \think\db\exception\ModelNotFoundException
	 * @author 等风来
	 * @email 136327134@qq.com
	 * @date 2022/11/2
	 */
	public function detailCode(Request $request, $id)
	{
		$uid = $request->uid();
		/** @var QrcodeServices $qrcodeService */
		$qrcodeService = app()->make(QrcodeServices::class);
		$time = $request->param('time', '');
		$status = $request->param('status', '');
		if (($configData['share_qrcode'] ?? 0) && request()->isWechat()) {
			$storeInfo['code_base'] = $qrcodeService->getTemporaryQrcode('seckill-' . $id . '-' . $time . '-' . $status, $uid)->url;
		} else {
			$storeInfo['code_base'] = $qrcodeService->getWechatQrcodePath($id . '_product_seckill_detail_wap.jpg', '/pages/activity/goods_seckill_details/index?id=' . $id . '&time=' . $time . '&status=' . $status);
		}

		return app('json')->success($storeInfo);
	}

}
