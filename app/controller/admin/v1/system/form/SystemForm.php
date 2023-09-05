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

namespace app\controller\admin\v1\system\form;


use app\controller\admin\AuthController;
use app\services\system\form\SystemFormDataServices;
use app\services\system\form\SystemFormServices;
use think\facade\App;

/**
 *
 * Class SystemForm
 * @package app\controller\admin\v1\system\form
 */
class SystemForm extends AuthController
{

    /**
     * Diy constructor.
     * @param App $app
     * @param SystemFormServices $services
     */
    public function __construct(App $app, SystemFormServices $services)
    {
        parent::__construct($app);
        $this->services = $services;
    }

    /**
     * 系统表单列表
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index()
    {
        $where = $this->request->getMore([
            ['status', ''],
            ['name', ''],
        ]);
        return $this->success($this->services->getFormList($where));
    }

	/*
	 * 所有系统表单
	 */
	public function allSystemForm()
	{
		$data = $this->services->getFormList([], ['id', 'name']);
		return $this->success($data['list'] ?? []);
	}

	/**
	 * 获取一条数据
	 * @param int $id
	 * @return mixed
	 */
	public function getInfo(int $id)
	{
		if (!$id) return $this->fail('数据不存在');
		[$type] = $this->request->postMore([
			['type', 0],
		], true);
		$info = $this->services->get($id);
		if ($info) {
			$info = $info->toArray();
		} else {
			return $this->fail('数据不存在');
		}
		$info['value'] = json_decode($info['value'], true);
		if ($type == 1) {//处理表单数据
			$value = $info['value'] ?? [];
			$info = $this->services->handleForm($value);
		}
		return $this->success(compact('info'));
	}

    /**
     * 保存资源
     * @param int $id
     * @return mixed
     */
    public function save(int $id = 0)
    {
        $data = $this->request->postMore([
            ['name', ''],
            ['value', '']
        ]);
		if (!$data['name']) {
			return $this->fail('请输入表单模版名称');
		}
		if (!$data['value']) {
			return $this->fail('请添加表单组件');
		}
		foreach ($data['value'] as $item) {
			$defaultValue = $item['defaultValConfig']['value'] ?? '';
			if ($item['name'] == 'texts' && $defaultValue) {//文本类型，验证手机号 身份证 邮箱 数字
				$tableList = $item['valConfig']['tabList'] ?? [];
				$tableValue = $item['valConfig']['tabVal'] ?? 0;
				if ($tableList) {
					foreach ($tableList as $type => $verify) {
							switch ($tableValue) {
								case 0:
									break;
								case 1://手机号
									if (!check_phone($defaultValue)) {
										return $this->fail('请输入正确的手机号');
									}
									break;
								case 2://身份证
									try {
										if (!check_card($defaultValue)) return app('json')->fail('请输入正确的身份证');
									} catch (\Throwable $e) {
										//				return app('json')->fail('请输入正确的身份证');
									}
									break;
								case 3://邮箱
									if (!check_mail($defaultValue)) {
										return $this->fail('请填写正确的邮箱');
									}
									break;
								case 4://数字
									if (!preg_match('/^[0-9]+$/', $defaultValue)) {
										return $this->fail('请输入数字');
									}
									break;
							}
					}
				}
			}
		}
        $value = is_array($data['value']) ? json_encode($data['value']) : $data['value'];
		$data['value'] = $value;
		$one =  $this->services->getOne(['name' => $data['name']]);
		if ($one && (($id && $one['id'] != $id) || !$id)) {
			return $this->fail('模版名称已经存在');
		}
		if ($id) {
			$info = $this->services->get((int)$id, ['id', 'is_del']);
			if (!$info) {
				return $this->fail('数据不存在');
			}
			$data['update_time'] = time();
			$res = $this->services->update($id, $data);
			if (!$res) $this->fail('修改失败');
		} else {
			$data['add_time'] = time();
			$data['update_time'] = time();
			$res = $this->services->save($data);
			if (!$res) $this->fail('保存失败');
			$id = $res->id;
		}
        return $this->success($id ? '修改成功' : '保存成功', ['id' => $id]);
    }

    /**
     * 删除模板
     * @param $id
     * @return mixed
     */
    public function del($id)
    {
		$info = $this->services->get((int)$id, ['id', 'is_del']);
		if ($info && $info['is_del'] == 0) {
			$this->services->update($id, ['is_del' => 1]);
		}
        return $this->success('删除成功');
    }

    /**
     * 上下架
     * @param $id
     * @return mixed
     */
    public function set_show($id, $is_show)
    {
		$info = $this->services->get((int)$id, ['id']);
		if (!$info) {
			return $this->fail('数据不存在');
		}
		$this->services->update($id, ['status' => $is_show, 'update_time' => time()]);
        return $this->success('设置成功');
    }

	/**
	 * 表单收集数据列表
	 * @param SystemFormDataServices $systemFormDataServices
	 * @param $id
	 * @return mixed
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\DbException
	 * @throws \think\db\exception\ModelNotFoundException
	 */
	public function formData(SystemFormDataServices $systemFormDataServices, $id)
	{
		$where = $this->request->postMore([
			['data', '', '', 'time']
		]);
		return $this->success($systemFormDataServices->getFormDataList((int)$id, $where));
	}

}
