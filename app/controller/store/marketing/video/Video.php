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
namespace app\controller\store\marketing\video;

use app\controller\store\AuthController;
use app\services\activity\video\VideoServices;
use think\facade\App;

/**
 * 短视频控制器
 * Class Video
 * @package app\admin\controlle\store\marketing\video
 */
class Video extends AuthController
{

    /**
     * Video constructor.
     * @param App $app
     * @param VideoServices $service
     */
    public function __construct(App $app, VideoServices $service)
    {
        parent::__construct($app);
        $this->services = $service;
    }

    /**
     * 视频列表
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index()
    {
        $where = $this->request->getMore([
            ['data', '', '', 'time'],
            ['keyword', ''],
            ['is_verify', '']
        ]);
		$where['type'] = 1;
		$where['relation_id'] = $this->storeId;
		$where['is_del'] = 0;
		return $this->success($this->services->sysPage($where));
    }

	/**
 	* 获取视频信息
	* @param $id
	* @return mixed
	 */
	public function info($id)
	{
		if (!$id) return $this->fail('缺少参数');
		return $this->success($this->services->getInfo((int)$id));
	}


    /**
     * 保存新增分类
     * @return mixed
     */
    public function save($id)
    {
        $data = $this->request->postMore([
            ['image', ''],
            ['desc', ''],
            ['video_url', ''],
            ['product_id', []],
            ['is_show', 1],
            ['is_recommend', 0],
            ['sort', 0]
        ]);
		$data['type'] = 1;
		$data['relation_id'] = $this->storeId;
		if ($id) {
			$info = $this->services->get($id);
			if (!$info) {
				$this->fail('视频不存在');
			}
			$data['is_verify'] = 0;
			$this->services->update($id, $data);
		} else {
			$data['add_time'] = time();
			$this->services->save($data);
		}
        return $this->success('添加视频成功!');
    }

	/**
     * 修改状态
     * @param string $status
     * @param string $id
     */
    public function set_show($id = '', $status = '')
    {
        if ($status == '' || $id == '') return $this->fail('缺少参数');
        $this->services->update($id, ['is_show' => $status]);
        return $this->success($status == 1 ? '显示成功' : '隐藏成功');
    }


    /**
     * 删除视频
     * @param $id
     * @return mixed
     */
    public function delete($id)
    {
        if ($id == '') return $this->fail('缺少参数');
		$info = $this->services->get($id);
		if ($info) {
			$this->services->update($id, ['is_del' => 1]);
		}
        return $this->success('删除成功!');
    }
}
