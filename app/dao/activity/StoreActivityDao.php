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
declare (strict_types=1);

namespace app\dao\activity;

use app\dao\BaseDao;
use app\model\activity\StoreActivity;

/**
 * 活动
 * Class StoreActivityDao
 * @package app\dao\activity
 */
class StoreActivityDao extends BaseDao
{

    /**
     * 设置模型
     * @return string
     */
    protected function setModel(): string
    {
        return StoreActivity::class;
    }

	/**
	 * 搜索
	 * @param array $where
	 * @return \crmeb\basic\BaseModel|mixed|\think\Model
	 */
	protected function search(array $where = [])
	{
		return parent::search($where)->when(isset($where['activityTime']), function ($query) use ($where) {
			[$startTime, $stopTime] = is_array($where['activityTime']) ? $where['activityTime'] : [time(), time() - 86400];
			$query->where('start_day', '<=', $startTime)->where('end_day', '>=', $stopTime);
		})->when(isset($where['start_status']) && $where['start_status'] !== '', function ($query) use ($where) {
			$time = time();
			switch ($where['start_status']) {
				case -1:
					$query->where(function ($q) use ($time) {
						$q->where('end_day', '<', $time - 86400)->whereOr('status', '0');
					});
					break;
				case 0:
					$query->where('start_day', '>', $time)->where('status', 1);
					break;
				case 1:
					$query->where('start_day', '<=', $time)->where('end_day', '>=', $time - 86400)->where('status', 1);
					break;
			}
		});
	}


	/**
	 * 获取活动列表
	 * @param array $where
	 * @param string $field
	 * @param int $page
	 * @param int $limit
	 * @param array $with
	 * @param string $order
	 * @return array
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\DbException
	 * @throws \think\db\exception\ModelNotFoundException
	 */
    public function getList(array $where, string $field = '*', int $page = 0, int $limit = 0, array $with = [], string $order = 'start_time asc, id desc')
    {
        return $this->search($where)->field($field)
			->when($with, function ($query) use ($with) {
				$query->with($with);
			})->when($page != 0 && $limit != 0, function ($query) use ($page, $limit) {
                $query->page($page, $limit);
            })->order($order)->select()->toArray();
    }

	/**
	 * 验证是否有活动
	 * @param array $where
	 * @return bool
	 * @throws \think\db\exception\DbException
	 */
	public function checkActivity(array $where)
	{
		return $this->search($where)->where('end_day', '>', time())->count() > 0;
	}



}
