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
namespace app\controller\admin\v1\marketing\integral;


use app\controller\admin\AuthController;
use app\services\user\UserBillServices;
use app\services\activity\integral\UserPointServices;
use think\facade\App;

/**
 * 积分控制器
 * Class UserPoint
 * @package app\controller\admin\v1\markging\integral
 */
class UserPoint extends AuthController
{

    /**
     * Finance constructor.
     * @param App $app
     * @param UserBillServices $services
     */
    public function __construct(App $app, UserBillServices $services)
    {
        parent::__construct($app);
        $this->services = $services;
    }

    /**
     * @return mixed
     */
    public function index()
    {
        $where = $this->request->getMore([
            ['start_time', ''],
            ['end_time', ''],
            ['nickname', ''],
            ['page', 1],
            ['limit', 10],
        ]);
        return $this->success($this->services->getPointList($where));
    }

    /**
     * 获取积分日志头部信息
     * @return mixed
     */
    public function integral_statistics()
    {
        $where = $this->request->getMore([
            ['start_time', ''],
            ['end_time', ''],
            ['nickname', ''],
        ]);
        return $this->success(['res' => $this->services->getUserPointBadgelist($where)]);
    }

	/**
	 * 积分记录
	 * @return mixed
	 */
	public function pointRecord(UserPointServices $pointServices)
	{
		$where = $this->request->getMore([
			['time', ''],
			['trading_type', 0]
		]);
		$date = $pointServices->pointRecord($where);
		return app('json')->success($date);
	}

	/**
	 * 积分记录备注
	 * @return mixed
	 */
	public function pointRecordRemark(UserPointServices $pointServices, $id = 0)
	{
		[$mark] = $this->request->postMore([
			['mark', '']
		], true);
		$pointServices->recordRemark($id, $mark);
		return app('json')->success('备注成功');
	}

	/**
	 * 积分统计基础信息
	 * @return mixed
	 */
	public function getBasic(UserPointServices $pointServices)
	{
		$where = $this->request->getMore([
			['time', '']
		]);
		$where['time'] = $this->getDay($where['time']);
		return app('json')->success($pointServices->getBasic($where));
	}

	/**
	 * 积分统计趋势图
	 * @return mixed
	 */
	public function getTrend(UserPointServices $pointServices)
	{
		$where = $this->request->getMore([
			['time', '']
		]);
		$where['time'] = $this->getDay($where['time']);
		return app('json')->success($pointServices->getTrend($where));
	}

	/**
	 * 积分来源
	 * @return mixed
	 */
	public function getChannel(UserPointServices $pointServices)
	{
		$where = $this->request->getMore([
			['time', '']
		]);
		$where['time'] = $this->getDay($where['time']);
		return app('json')->success($pointServices->getChannel($where));
	}

	/**
	 * 积分消耗
	 * @return mixed
	 */
	public function getType(UserPointServices $pointServices)
	{
		$where = $this->request->getMore([
			['time', '']
		]);
		$where['time'] = $this->getDay($where['time']);
		return app('json')->success($pointServices->getType($where));
	}


	/**
	 * 格式化时间
	 * @param $time
	 * @return string
	 */
	public function getDay($time)
	{
		if (strstr($time, '-') !== false) {
			[$startTime, $endTime] = explode('-', $time);
			if (!$startTime && !$endTime) {
				return date("Y/m/d", strtotime("-30 days", time())) . '-' . date("Y/m/d", time());
			} else {
				return $startTime . '-' . $endTime;
			}
		} else {
			return date("Y/m/d", strtotime("-30 days", time())) . '-' . date("Y/m/d", time());
		}
	}


}
