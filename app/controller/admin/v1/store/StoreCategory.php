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
namespace app\controller\admin\v1\store;

use app\controller\admin\AuthController;
use app\services\store\StoreCategoryServices;
use crmeb\exceptions\AdminException;
use think\facade\App;

/**
 * 门店分类控制器
 * Class StoreCategory
 * @package app\controller\admin\v1\store
 */
class StoreCategory extends AuthController
{
    /**
     * @var StoreCategoryServices
     */
    protected $services;

    /**
     * StoreCategory constructor.
     * @param App $app
     * @param StoreCategoryServices $services
     */
    public function __construct(App $app, StoreCategoryServices $services)
    {
        parent::__construct($app);
        $this->services = $services;
    }

    /**
     * 分类列表
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index()
    {
        $where = $this->request->getMore([
            ['is_show', ''],
            ['pid', 0],
            ['name', ''],
            ['id', 0],
        ]);
		$where['pid'] = (int)$where['pid'];
        $data = $this->services->getList($where);
        return $this->success($data);
    }

	/**
	 * 商品分类搜索
	 * @return mixed
	 */
	public function tree_list($type)
	{
		$list = $this->services->getTierList(1, $type);
		return $this->success($list);
	}

	/**
	 * 获取分类cascader格式数据
	 * @param $type
	 * @return mixed
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\DbException
	 * @throws \think\db\exception\ModelNotFoundException
	 */
	public function cascader_list($type = 1)
	{
		return $this->success($this->services->cascaderList(!$type));
	}

    /**
     * 修改状态
     * @param string $is_show
     * @param string $id
     */
    public function set_show($is_show = '', $id = '')
    {
        if ($is_show == '' || $id == '') return $this->fail('缺少参数');
        $this->services->setShow($id, $is_show);
        return $this->success($is_show == 1 ? '显示成功' : '隐藏成功');
    }

    /**
     * 生成添加、编辑表单
     * @return mixed
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function create($id = 0)
    {
        return $this->success($this->services->createForm($id));
    }

    /**
     * 保存分类
     * @return mixed
     */
    public function save($id = 0)
    {
        $data = $this->request->postMore([
            ['pid', 0],
            ['name', ''],
            ['sort', 0],
            ['is_show', 0]
        ]);
        if (!$data['name']) {
            return $this->fail('请输入分类名称');
        }
		$data['type'] = 1;
		$data['group'] = 5;
		if ($id) {
			$info = $this->services->get((int)$id);
			if (!$info) {
				return $this->fail('数据不存在');
			}
			$this->services->update($id, $data);
		} else {
			$data['add_time'] = time();
			$this->services->save($data);
		}
        return $this->success('保存分类成功!');
    }

    /**
     * 删除分类
     * @param $id
     * @return mixed
     */
    public function delete($id)
    {
		if ($this->services->count(['pid' => $id])) {
			throw new AdminException('请先删除子分类!');
		}
        $this->services->delete((int)$id);
        return $this->success('删除成功!');
    }
}
