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
use app\services\BaseServices;
use app\dao\diy\DiyDao;


/**
 * 系统表单
 * Class SystemFormServices
 * @package app\services\diy
 * @mixin DiyDao
 */
class SystemFormServices extends BaseServices
{

	/**
	 * form类型
	 * @var string[]
	 */
	protected $formType = [
		'checkboxs' => '多选框',
		'citys' => '城市',
		'dates' => '日期',
		'dateranges' => '日期范围',
		'radios' => '单选框',
		'selects' => '下拉框',
		'texts' => '文本框',
		'times' => '时间',
		'timeranges' => '时间范围',
		'uploadPicture' => '图片'
	];

    /**
     * DiyServices constructor.
     * @param SystemFormDao $dao
     */
    public function __construct(SystemFormDao $dao)
    {
        $this->dao = $dao;
    }

	/**
	 * 获取系统表单
	 * @param array $where
	 * @param array $field
	 * @return array
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\DbException
	 * @throws \think\db\exception\ModelNotFoundException
	 */
	public function getFormList(array $where = [], array $field = ['*'])
	{
		[$page, $limit] = $this->getPageValue();
		$list = $this->dao->getFormList($where, $field, $page, $limit);
		$count = $this->dao->count($where + ['is_del' => 0]);
		return compact('list', 'count');
	}

	/**
	 * 处理表单数据
	 * @param array $form
	 * @return array
	 */
	public function handleForm(array $form)
	{
		$info = [];
		if ($form) {
			$infoOne = [];
			foreach ($form as $item) {
				$infoOne['id'] = $item['id'] ?? '';
				$infoOne['type'] = $item['name'] ?? '';
				$infoOne['name'] = $this->formType[$infoOne['type']] ?? '';
				$infoOne['title'] = $item['titleConfig']['value'] ?? '';
				$infoOne['tip'] = $item['tipConfig']['value'] ?? '';
				$infoOne['list'] = [];
				$infoOne['require'] = $item['titleShow']['val'] ?? false;
				switch ($item['name']) {
					case 'checkboxs':
					case 'radios':
					case 'selects':
						$infoOne['list'] = $item['wordsConfig']['list'] ?? [];
						break;
				}
				$infoOne['value'] = $item['value'] ?? '';
				$info[] = $infoOne;
			}
		}
		return $info;
	}

}
