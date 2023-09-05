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
use app\model\activity\collage\UserCollageCode;

/**
 * 拼单
 * Class UserCollageDao
 * @package app\dao\collage
 */
class UserCollageDao extends BaseDao
{
    /**
     * 设置模型
     * @return string
     */
    protected function setModel(): string
    {
        return UserCollageCode::class;
    }

    /**搜索条件处理
     * @param array $where
     * @return \crmeb\basic\BaseModel|mixed|\think\Model
     */
    public function search(array $where = [])
    {
        return parent::search($where)->when(isset($where['serial_number']) && $where['serial_number'] != '', function ($query) use ($where) {
            if (substr($where['serial_number'], 0, 2) == 'wx') {
                $query->where(function ($que) use ($where) {
                    $que->where('oid', 'in', function ($q) use ($where) {
                        $q->name('store_order')->where('order_id', 'LIKE', '%' . $where['serial_number'] . '%')->where('type', 10)->field(['id'])->select();
                    });
                });
            } else {
                $query->where('serial_number', 'LIKE', '%' . $where['serial_number'] . '%');
            }
        })->when(isset($where['status']) && $where['status'], function ($query) use ($where) {
            if (is_array($where['status'])) {
                $query->whereIn('status', $where['status']);
            } else {
                $query->where('status', $where['status']);
            }
        })->when(isset($where['type']) && $where['type'], function ($query) use ($where) {
            $query->where('type', $where['type']);
        })->when(isset($where['store_id']) && $where['store_id'], function ($query) use ($where) {
            $query->where('store_id', $where['store_id']);
        })->when(isset($where['staff_id']) && $where['staff_id'] > 0, function ($query) use ($where) {
            $query->where(function ($que) use ($where) {
                $que->where('oid', 'in', function ($q) use ($where) {
                    $q->name('store_order')->where('staff_id', $where['staff_id'])->where('type', 10)->field(['id'])->select();
                });
            });
        });
    }

    /**获取当天最大流水号
     * @param $where
     * @return mixed
     */
    public function getMaxSerialNumber($where)
    {
        $now_day = strtotime(date('Y-m-d'));
        return $this->search($where)->where('add_time', '>', $now_day)->order('add_time desc')->value('serial_number');
    }

    /**桌码订单
     * @param array $where
     * @param int $store_id
     * @param array $with
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function searchTableCodeList(array $where, array $field = ['*'], int $page = 0, int $limit = 0, array $with = [])
    {
        return $this->search($where)->field($field)->when(count($with), function ($query) use ($with) {
            $query->with($with);
        })->when($page && $limit, function ($query) use ($page, $limit) {
            $query->page($page, $limit);
        })->when(!$page && $limit, function ($query) use ($limit) {
            $query->limit($limit);
        })->order('add_time desc')->select()->toArray();
    }
}
