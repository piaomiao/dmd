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

namespace app\controller\admin\v1\marketing\seckill;

use app\controller\admin\AuthController;
use app\services\activity\seckill\StoreSeckillTimeServices;
use app\services\activity\StoreActivityServices;
use think\facade\App;

/**
 * 限时秒杀时间  控制器
 * Class StoreSeckillTime
 * @package app\controller\admin\v1\marketing\seckill
 */
class StoreSeckillTime extends AuthController
{
    public function __construct(App $app, StoreSeckillTimeServices $services)
    {
        parent::__construct($app);
        $this->services = $services;
    }

    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function index()
    {
        $where = $this->request->getMore([
			['title', ''],
			[['status', 's'], '']
        ]);
        $where['is_del'] = 0;
        return $this->success($this->services->systemPage($where));
    }

	/**
	 * 可用秒杀时间段
	 * @return mixed
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\DbException
	 * @throws \think\db\exception\ModelNotFoundException
	 */
	public function time_list()
	{
		return $this->success($this->services->time_list());
	}

	/**
	 * @param $id
	 * @return mixed
	 */
	public function create($id = '')
	{
		return $this->success($this->services->createForm((int)$id));
	}

    /**
     * 详情
     * @param $id
     * @return mixed
     */
    public function read($id)
    {
        $info = $this->services->getInfo((int)$id);
        return $this->success(compact('info'));
    }

    /**
     * 保存秒杀商品
     * @param int $id
     */
    public function save($id)
    {
        $data = $this->request->postMore([
            [['title', 's'], ''],
            [['describe', 's'], ''],
            ['time', []],
			['pic', ''],
            [['status', 'd'], 0],
        ]);

        $this->validate($data, \app\validate\admin\marketing\StoreSeckillTimeValidate::class, 'save');
		$data['start_time'] = str_replace(':', '', substr($data['time'][0], 0, -3));
		$data['end_time'] = str_replace(':', '', substr($data['time'][1], 0, -3));
		unset($data['time']);
		$id = (int)$id;
		if (!$this->services->checkTime($data, $id))
			return app('json')->fail('时间段不可重叠');
        if ($id) {
            $seckill = $this->services->get($id);
            if (!$seckill) {
                return $this->fail('数据不存在');
            }
        }
		if ($id) {
			$this->services->update($id, $data);
		} else {
			$this->services->save($data);
		}
        return $this->success('保存成功');
    }

	/**
	 * 修改状态
	 * @param $id
	 * @param $status
	 * @return mixed
	 */
	public function set_status($id, $status)
	{
		$this->services->update($id, ['status' => $status]);
		return $this->success($status == 0 ? '关闭成功' : '开启成功');
	}

    /**
     * 删除指定资源
     *
     * @param int $id
     * @return \think\Response
     */
    public function delete(StoreActivityServices $activityServices, $id)
    {
        if (!$id) return $this->fail('缺少参数');
		$info = $this->services->get((int)$id);
		if (!$info) {
			return $this->fail('数据不存在');
		}
		if ($activityServices->existenceActivity(['start_time' => $info->start_time, 'end_time' => $info->end_time]))
			return app('json')->fail('已有开启活动，不可删除');
        $this->services->delete($id);
        return $this->success('删除成功!');
    }




}
