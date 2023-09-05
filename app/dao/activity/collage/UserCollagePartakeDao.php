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

namespace app\dao\activity\collage;


use app\dao\BaseDao;
use app\model\activity\collage\UserCollageCodePartake;

/**
 * 用户参与拼单
 * Class UserCollagePartakeDao
 * @package app\dao\collage
 */
class UserCollagePartakeDao extends BaseDao
{
    /**
     * 设置模型
     * @return string
     */
    protected function setModel(): string
    {
        return UserCollageCodePartake::class;
    }

    /**
     * @param array $where
     * @return \crmeb\basic\BaseModel|mixed|\think\Model
     */
    public function search(array $where = [])
    {
        return parent::search($where)->when(isset($where['uid']) && $where['uid'], function ($query) use ($where) {
            $query->where('uid', $where['uid']);
        })->when(isset($where['collate_code_id']) && $where['collate_code_id'], function ($query) use ($where) {
            $query->where('collate_code_id', $where['collate_code_id']);
        })->when(isset($where['store_id']) && $where['store_id'], function ($query) use ($where) {
            $query->where('store_id', $where['store_id']);
        })->when(isset($where['status']) && $where['status'], function ($query) use ($where) {
            $query->where('status', 1);
        });
    }

    /**
     * 用户拼单商品统计数据
     * @param int $uid
     * @param string $field
     * @param array $with
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getUserPartakeList(array $where, string $field = '*', array $with = [])
    {
        return $this->getModel()->where($where)->when(count($with), function ($query) use ($with) {
            $query->with($with);
        })->order('add_time DESC')->field($field)->select()->toArray();
    }

    /**
	 * 获取拼单信息
     * @param array $where
     * @param string $field
     * @param array $with
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getUserPartakeProductList(array $where, string $field = '*', array $with = [])
    {
        return $this->getModel()->where($where)->when(count($with), function ($query) use ($with) {
            $query->with($with);
        })->order('add_time DESC')->field($field)->select()->toArray();
    }

    /**
	 * 用户删除拼单、桌码商品
     * @param array $where
     * @return bool
     */
    public function del(array $where)
    {
        return $this->getModel()->where($where)->delete();
    }

    /**最后加入的记录
     * @param $where
     * @return array|\crmeb\basic\BaseModel|mixed|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getUserPartake(array $where)
    {
        return $this->getModel()->where($where)->order('add_time DESC')->find();
    }
}
