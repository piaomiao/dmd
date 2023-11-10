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

namespace app\dao\store;


use app\dao\BaseDao;
use app\model\store\SystemStoreShare;

/**
 * 门店股东
 * Class SystemStoreShareDao
 * @package app\dao\system\store
 */
class SystemStoreShareDao extends BaseDao
{
    /**
     * 设置模型
     * @return string
     */
    protected function setModel(): string
    {
        return SystemStoreShare::class;
    }

    /**
     * @return \crmeb\basic\BaseModel
     */
    public function getWhere()
    {
        return $this->getModel();
    }

    /**
     * 门店股东搜索器
     * @param array $where
     * @return \crmeb\basic\BaseModel|mixed|\think\Model
     */
    public function search(array $where = [])
    {
        return parent::search($where)->when(isset($where['keyword']) && $where['keyword'], function ($query) use ($where) {
            if (!isset($where['field_key']) || $where['field_key'] == '') {
                $query->whereLike('id|uid|share_name|phone', '%' . $where['keyword'] . '%');
            } else {
                $query->where($where['field_key'], $where['keyword']);
            }
        });
    }
    
    /**
     * 获取股东列表
     * @param array $where
     * @param string $field
     * @param int $page
     * @param int $limit
     * @param array|string[] $with
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getStoreShareList(array $where, string $field = '*', int $page = 0, int $limit = 0, array $with = ['store', 'user'])
    {
        return $this->search($where)->field($field)->when($with, function ($query) use ($with) {
            $query->with(array_merge($with, ['store', 'user']));
        })->when($page && $limit, function ($query) use ($page, $limit) {
            $query->page($page, $limit);
        })->when(isset($where['notId']), function ($query) use ($where) {
            $query->where('id', '<>', $where['notId']);
        })->when(isset($where['store_id']), function ($query) use ($where) {
            $query->where('store_id', $where['store_id']);
        })->order('add_time DESC')->select()->toArray();
    }

    /**
     * 获取股东select
     * @param array $where
     * @return array
     */
    public function getSelectList(array $where)
    {
        return $this->search($where)->field('id,share_name')->select()->toArray();
    }
}
