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

namespace app\services\other;


use app\dao\other\CategoryDao;
use app\services\BaseServices;
use crmeb\traits\ServicesTrait;

/**
 * Class CategoryServices
 * @package app\services\other
 * @mixin CategoryDao
 */
class CategoryServices extends BaseServices
{

    use ServicesTrait;

    protected $cacheName = 'crmeb_cate';

    /**
     * CategoryServices constructor.
     * @param CategoryDao $dao
     */
    public function __construct(CategoryDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取分类列表
     * @param array $where
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getCateList(array $where = [], array $field = ['*'], array $with = [])
    {
        [$page, $limit] = $this->getPageValue();
        $data = $this->dao->getCateList($where, $page, $limit, $field, $with);
        $count = $this->dao->count($where);
        return compact('data', 'count');
    }

    /**桌码管理
     * @param array $where
     * @param array $field
     * @param array $with
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getTableCodeCateList(array $where = [], array $field = ['*'], array $with = [])
    {
        $data = $this->dao->getCateList($where, 0, 0, $field, $with);
        return $data;
    }

}
