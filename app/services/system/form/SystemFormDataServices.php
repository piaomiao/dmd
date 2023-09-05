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

namespace app\services\system\form;

use app\dao\system\form\SystemFormDao;
use app\dao\system\form\SystemFormDataDao;
use app\services\BaseServices;
use app\dao\diy\DiyDao;
use think\exception\ValidateException;


/**
 * 系统表单
 * Class SystemFormDataServices
 * @package app\services\system\form
 * @mixin DiyDao
 */
class SystemFormDataServices extends BaseServices
{

    /**
     * DiyServices constructor.
     * @param SystemFormDataDao $dao
     */
    public function __construct(SystemFormDataDao $dao)
    {
        $this->dao = $dao;
    }

	/**
	 * 获取表单收集数据列表
	 * @param int $id
	 * @param array $where
	 * @return array
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\DbException
	 * @throws \think\db\exception\ModelNotFoundException
	 */
	public function getFormDataList(int $id = 0, array $where = [])
	{
		$where['is_del'] = 0;
		if ($id) $where['system_form_id'] = $id;
		[$page, $limit] = $this->getPageValue();
		$list = $this->dao->getList($where, ['*'], $page, $limit, ['user', 'systemForm']);
		$count = $this->dao->count($where);
		return compact('list', 'count');
	}

	/**
	 * 保存系统表单收集数据
	 * @param array $form
	 * @param int $type
	 * @return bool
	 */
	public function setFormData(array $form, int $type = 1)
	{
		if (!$form) {
			throw new ValidateException('缺少表单收集数据');
		}
		/** @var SystemFormServices $systemFormServices */
		$systemFormServices = app()->make(SystemFormServices::class);
		$form['value'] = $systemFormServices->handleForm($form['value'] ?? []);

		$form['value'] = json_encode($form['value']);
		$data = ['type' => $type, 'add_time' => time()];
		switch ($type) {
			case 1://订单
				$data = array_merge($data, $form);
				break;
		}
		if ($data) {
			$this->dao->save($data);
		}
		return true;
	}

}
