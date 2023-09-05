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

namespace app\services\store;


use app\dao\other\CategoryDao;
use app\services\BaseServices;
use crmeb\exceptions\AdminException;
use crmeb\services\FormBuilder as Form;
use crmeb\traits\ServicesTrait;

/**
 * 门店分类
 * Class StoreCategoryServices
 * @package app\services\store
 * @mixin CategoryDao
 */
class StoreCategoryServices extends BaseServices
{
	/**
	 * 在分类库中
	 */
	const GROUP = 5;

    use ServicesTrait;

    /**
     * UserLabelCateServices constructor.
     * @param CategoryDao $dao
     */
    public function __construct(CategoryDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取标签分类
     * @param array $where
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getList(array $where)
    {
		$where['type'] = 1;
		$where['group'] = self::GROUP;
        $list = $this->dao->getCateList($where, 0, 0, ['*'], ['children']);
		if ($list) {
			foreach ($list as $key => &$item) {
				if (isset($item['children']) && $item['children']) {
					$item['children'] = [];
					$item['loading'] = false;
					$item['_loading'] = false;
				} else {
					unset($item['children']);
				}
			}
		}
        $count = $this->dao->count($where);
        return compact('list', 'count');
    }

	/**
	 * 设置分类状态
	 * @param $id
	 * @param $is_show
	 */
	public function setShow(int $id, int $is_show)
	{
		$res = $this->dao->update($id, ['is_show' => $is_show]);
		$res = $res && $this->dao->update($id, ['is_show' => $is_show], 'pid');
		if (!$res) {
			throw new AdminException('设置失败');
		}
		return true;
	}

	/**
	 * 商品分类搜索下拉
	 * @param string $show
	 * @param string $type
	 * @return array
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\DbException
	 * @throws \think\db\exception\ModelNotFoundException
	 */
	public function getTierList($show = '', $type = 0)
	{
		$where = ['type' => 1, 'group' => self::GROUP];
		if ($show !== '') $where['is_show'] = $show;
		if (!$type) $where['pid'] = 0;
		return sort_list_tier($this->dao->getCateList($where));
	}

	/**
	 * 获取分类cascader
	 * @param int $type
	 * @param int $relation_id
	 * @param bool $isPid
	 * @return mixed
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\DbException
	 * @throws \think\db\exception\ModelNotFoundException
	 */
	public function cascaderList(bool $isPid = false)
	{
		$where = ['is_show' => 1, 'type' => 1, 'group' => self::GROUP];
		if ($isPid) $where['pid'] = 0;
		$data = get_tree_children($this->dao->getCateList($where, 0, 0, ['id as value', 'name as label', 'pid']), 'children', 'value');
		return $data;
	}

	/**
	 * 获取一级分类组合数据
	 * @return array[]
	 */
	public function menus()
	{
		$list = $this->dao->getMenus(['pid' => 0, 'group' => self::GROUP, 'type' => 1]);
		$menus = [['value' => 0, 'label' => '顶级菜单']];
		foreach ($list as $menu) {
			$menus[] = ['value' => $menu['id'], 'label' => $menu['name']];
		}
		return $menus;
	}


    /**
     * 创建表单
     * @return array
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function createForm($id = 0)
    {
		$info = [];
		if ($id) {
			$info = $this->dao->get($id);
		}
		if (isset($info['pid'])) {
			if ($info['pid']) {
				$f[] = Form::select('pid', '父级', (int)($info['pid'] ?? ''))->setOptions($this->menus())->filterable(1);
			} else {
				$f[] = Form::select('pid', '父级', (int)($info['pid'] ?? ''))->setOptions($this->menus())->filterable(1)->disabled(true);
			}
		} else {
			$f[] = Form::select('pid', '父级', (int)($info['pid'] ?? ''))->setOptions($this->menus())->filterable(1);
		}
		$f[] = Form::input('name', '分类名称', $info['name'] ?? '')->maxlength(30)->required();
		$f[] = Form::number('sort', '排序', (int)($info['sort'] ?? 0))->min(0)->min(0);
		$f[] = Form::radio('is_show', '状态', $info['is_show'] ?? 1)->options([['label' => '显示', 'value' => 1], ['label' => '隐藏', 'value' => 0]]);
        return create_form($id ? '编辑' : '添加分类', $f, $this->url('/store/category/' . $id), 'POST');
    }


}
