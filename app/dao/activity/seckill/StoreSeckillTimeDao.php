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

namespace app\dao\activity\seckill;

use app\dao\BaseDao;
use app\model\activity\seckill\StoreSeckillTime;

/**
 * 秒杀时间
 * Class StoreSeckillTimeDao
 * @package app\dao\activity\seckill
 */
class StoreSeckillTimeDao extends BaseDao
{

    /**
     * 设置模型
     * @return string
     */
    protected function setModel(): string
    {
        return StoreSeckillTime::class;
    }

	/**
	 * @param array $where
	 * @return \crmeb\basic\BaseModel|mixed|\think\Model
	 */
	public function search(array $where = [])
	{
		return parent::search($where)
			->when(isset($where['start_time']) && $where['start_time'] !== '', function ($query) use ($where) {
				$query->whereTime('start_time', '<=', intval($where['start_time']));
			})
			->when(isset($where['end_time']) && $where['end_time'] !== '', function ($query) use ($where) {
				$query->whereTime('end_time', '>=', intval($where['end_time']));
			});
	}


	/**
	 * 获取秒杀时间列表
	 * @param array $where
	 * @param string $field
	 * @param int $page
	 * @param int $limit
	 * @param string $order
	 * @return array
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\DbException
	 * @throws \think\db\exception\ModelNotFoundException
	 */
    public function getList(array $where, string $field = '*', int $page = 0, int $limit = 0, string $order = 'start_time asc,id desc')
    {
        return $this->search($where)->field($field)
            ->when($page != 0 && $limit != 0, function ($query) use ($page, $limit) {
                $query->page($page, $limit);
            })->order($order)->select()->toArray();
    }


	/**
	 * 开始时间 在别的时间段中
	 * @param $time
	 * @param $id
	 * @return int
	 * @throws \think\db\exception\DbException
	 */
	public function valStartTime($time, $id)
	{
		return $this->getModel()
			->when($id, function ($query) use ($id) {
				$query->where('id', '<>', $id);
			})->where('start_time', '<=', $time)->where('end_time', '>', $time)->count();
	}

	/**
	 * 结束时间在别的时间段中
	 * @param $time
	 * @param $id
	 * @return int
	 * @throws \think\db\exception\DbException
	 */
	public function valEndTime($time, $id)
	{
		return $this->getModel()
			->when($id, function ($query) use ($id) {
				$query->where('id', '<>', $id);
			})->where('start_time', '<', $time)->where('end_time', '>=', $time)->count();
	}

	/**
	 * 时间段包含了别的时间段
	 * @param array $data
	 * @param $id
	 * @return int
	 * @throws \think\db\exception\DbException
	 */
	public function valAllTime(array $data, $id)
	{
		return $this->getModel()
			->when($id, function ($query) use ($id) {
				$query->where('id', '<>', $id);
			})->where('start_time', '>', $data['start_time'])->where('end_time', '<=', $data['end_time'])->count();
	}



}
