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

namespace app\services\activity\seckill;


use app\dao\activity\seckill\StoreSeckillTimeDao;
use app\services\BaseServices;
use crmeb\exceptions\AdminException;
use crmeb\services\FormBuilder as Form;
use think\facade\Route as Url;

/**
 * 秒杀时间
 * Class StoreSeckillTimeServices
 * @package app\services\activity\seckill
 * @mixin StoreSeckillTimeDao
 */
class StoreSeckillTimeServices extends BaseServices
{

    /**
     * StoreSeckillTimeServices constructor.
     * @param StoreSeckillTimeDao $dao
     */
    public function __construct(StoreSeckillTimeDao $dao)
    {
        $this->dao = $dao;
    }

	/**
	 * 获取当前的秒杀时间time
	 * @return int
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\DbException
	 * @throws \think\db\exception\ModelNotFoundException
	 */
	public function getSeckillTime()
	{
		$seckillTime = $this->time_list();
		$currentHour = date('Hi');
		$time = 0;
		foreach ($seckillTime as $value) {
			$start = str_replace(':', '', $value['start_time']);
			$end = str_replace(':', '', $value['end_time']);
			if ($currentHour >= $start && $currentHour < $end) {
				$time = $value['id'];
			}
		}
		return (int)$time;
	}

	/**
	 * 获取列表
	 * @param array $where
	 * @return array
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\DbException
	 * @throws \think\db\exception\ModelNotFoundException
	 */
	public function systemPage(array $where)
	{
		[$page, $limit] = $this->getPageValue();
		$list = $this->dao->getList($where, '*', $page, $limit);
		$count = $this->dao->count($where);
		foreach ($list as &$item) {
			$item['start_time'] = substr_replace($item['start_time'], ':', 2, 0);
			$item['end_time'] = substr_replace($item['end_time'], ':', 2, 0);
		}
		return compact('list', 'count');
	}

	/**
	 * 可用秒杀时间段
	 * @return array
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\DbException
	 * @throws \think\db\exception\ModelNotFoundException
	 */
	public function time_list()
	{
		$list = $this->dao->getList(['status' => 1], 'id,title,start_time,end_time,pic');
		foreach ($list as &$item) {
			$item['start_time'] = substr_replace($item['start_time'], ':', 2, 0);
			$item['end_time'] = substr_replace($item['end_time'], ':', 2, 0);
			$item['slide'] = $item['pic'];
		};
		return $list;
	}

	/**
	 * 验证时间是否重复
	 * @param array $where
	 * @param int $id
	 * @return bool
	 * @throws \think\db\exception\DbException
	 */
	public function checkTime(array $where, int $id)
	{
		if (!$this->dao->valStartTime($where['start_time'], $id) && !$this->dao->valEndTime($where['end_time'], $id) && !$this->dao->valAllTime($where, $id)) return true;
		return false;
	}

	/**
	 * 获取秒杀时间
	 * @param int $id
	 * @return array
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\DbException
	 * @throws \think\db\exception\ModelNotFoundException
	 */
	public function getInfo(int $id)
	{
		$info = $this->dao->get($id);
		if (!$info) {
			throw new AdminException('数据不存在');
		}
		$info = $info->toArray();
		$info['time'] = [
			substr_replace($info['start_time'], ':', 2, 0) . ':00',
			substr_replace($info['end_time'], ':', 2, 0) . ':00'
		];
		return $info;
	}


	/**
	 * 添加、编辑表单
	 * @param int $id
	 * @return mixed
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\DbException
	 * @throws \think\db\exception\ModelNotFoundException
	 */
	public function createForm(int $id)
	{
		$info = [];
		if ($id) {
			$info = $this->getInfo($id);
		}
		$f[] = Form::input('title', '标题', $info['title'] ?? '')->required();
		$f[] = Form::timeRange('time', '时间选择', $info['time'][0] ?? '', $info['time'][1] ?? '')->required()->prop('picker-options', ['format' => 'HH:mm']);
		$f[] = Form::frameImage('pic', '图片', Url::buildUrl('admin/widget.images/index', array('fodder' => 'pic')), $info['pic'] ?? '')->icon('ios-add')->width('960px')->height('505px')->modal(['footer-hide' => true]);
		$f[] = Form::radio('status', '状态', $info['status'] ?? 1)->options([['label' => '显示', 'value' => 1], ['label' => '隐藏', 'value' => 0]]);
		$f[] = Form::input('describe', '描述',$Info['describe'] ?? '')->required();
		return create_form($id ? '编辑' : '添加秒杀时间', $f, $this->url('/marketing/seckill/time/' . $id), 'POST');
	}

	/**
	 * 组合4位秒杀时间
	 * @param $time
	 * @return array|string|string[]
	 */
	public function changeTime($time)
	{
		$str_time = '';
		switch (strlen($time)) {
			case 1:
				$str_time = '0' . $time . '00';
				break;
			case 2:
				$str_time = $time . '00';
				break;
			case 3:
				$str_time = '0' . $time;
				break;
			case 4:
				$str_time = $time;
				break;
		}
		return substr_replace($str_time, ':', 2, 0);
	}

}
