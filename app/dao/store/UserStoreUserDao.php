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

namespace app\dao\store;

use think\model;
use app\dao\BaseDao;
use app\model\user\User;
use app\model\store\StoreUser;

/**
 * Class UserStoreUserDao
 * @package app\dao\store
 */
class UserStoreUserDao extends BaseDao
{
    /**
     * @var string
     */
    protected $alias = '';

    /**
     * @var string
     */
    protected $join_alis = '';

    /**
     * 精确搜索白名单
     * @var string[]
     */
    protected $withField = ['uid', 'nickname', 'user_type', 'phone'];

    /**
     * 设置模型
     * @return string
     */
    protected function setModel(): string
    {
        return User::class;
    }

    public function joinModel(): string
    {
        return StoreUser::class;
    }

    /**
     * 关联模型
     * @param string $alias
     * @param string $join_alias
     * @param string $join
     * @return \crmeb\basic\BaseModel
     */
    public function getModel(string $alias = 'u', string $join_alias = 's', $join = '')
    {
        $this->alias = $alias;
        $this->join_alis = $join_alias;
        /** @var StoreUser $storeUser */
        $storeUser = app()->make($this->joinModel());
        $table = $storeUser->getName();
        return parent::getModel()->withTrashed()->alias($alias)->join($table . ' ' . $join_alias, $alias . '.uid = ' . $join_alias . '.uid', $join);
    }

    /**
     * 获取列表
     * @param array $where
     * @param string $field
     * @param int $page
     * @param int $limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getList(array $where, $field = '*', int $page = 0, int $limit = 0)
    {
        return $this->getModel()->where($where)->field($field)->when($page && $limit, function ($query) use ($page, $limit) {
            $query->page($page, $limit);
        })->select()->toArray();
    }

    /**
     * 获取总数
     * @param array $where
     * @return int
     */
    public function getCount(array $where): int
    {
        return $this->getModel()->where($where)->count();
    }

    /**
     * 组合条件模型条数
     * @param Model $model
     * @return int
     */
    public function getCountByWhere(array $where): int
    {
        return $this->searchWhere($where)->group($this->alias . '.uid')->count();
    }

    /**
     * 组合条件模型查询列表
     * @param array $where
     * @param string $field
     * @param string $order
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getListByModel(array $where, string $field = '', string $order = '', int $page = 0, int $limit = 0): array
    {
        return $this->searchWhere($where)->field($field)->when($page && $limit, function ($query) use ($page, $limit) {
            $query->page($page, $limit);
        })->group($this->alias . '.uid')->order(($order ? $order . ' ,' : '') . $this->alias . '.uid desc')->select()->toArray();
    }

    /**
     * @param $where
     * @param array|null $field
     * @param int $page
     * @param int $limit
     * @return \crmeb\basic\BaseModel
     */
    public function searchWhere($where, ?array $field = [])
    {
        $model = $this->getModel();
        $userAlias = $this->alias . '.';
        $storeUserAlias = $this->join_alis . '.';
        if (isset($where['is_filter_del']) && $where['is_filter_del'] == 1) {
            $model = $model->where($userAlias . 'delete_time', null);
        }
        //门店
        if (isset($where['store_id']) && $where['store_id'] !== '') {
            $model = $model->where($storeUserAlias . 'store_id', $where['store_id']);
        }
        // 用户访问时间
        if (isset($where['user_time_type']) && isset($where['user_time'])) {
            //最后一次访问时间
            if ($where['user_time_type'] == 'visitno' && $where['user_time'] != '') {
                [$startTime, $endTime] = explode('-', $where['user_time']);
                if ($startTime && $endTime) {
                    $endTime = strtotime($endTime) + 24 * 3600;
                    $model = $model->where($userAlias . "last_time < " . strtotime($startTime) . " OR " . $userAlias . "last_time > " . $endTime);
                }
            }
            //访问时间
            if ($where['user_time_type'] == 'visit' && $where['user_time'] != '') {
                [$startTime, $endTime] = explode('-', $where['user_time']);
                if ($startTime && $endTime) {
                    $model = $model->where($userAlias . 'last_time', '>', strtotime($startTime));
                    $model = $model->where($userAlias . 'last_time', '<', strtotime($endTime) + 24 * 3600);
                }
            }
            //添加时间
            if ($where['user_time_type'] == 'add_time' && $where['user_time'] != '') {
                [$startTime, $endTime] = explode('-', $where['user_time']);
                if ($startTime && $endTime) {
                    $model = $model->where($userAlias . 'add_time', '>', strtotime($startTime));
                    $model = $model->where($userAlias . 'add_time', '<', strtotime($endTime) + 24 * 3600);
                }
            }
        }
        //购买次数
        if (isset($where['pay_count']) && $where['pay_count'] != '') {
            if ($where['pay_count'] == '-1') {
                $model = $model->where($userAlias . 'pay_count', 0);
            } else {
                $model = $model->where($userAlias . 'pay_count', '>', $where['pay_count']);
            }
        }

        //用户等级
        if (isset($where['level']) && $where['level']) {
            $model = $model->where($userAlias . 'level', $where['level']);
        }
        //用户分组
        if (isset($where['group_id']) && $where['group_id']) {
            $model = $model->where($userAlias . 'group_id', $where['group_id']);
        }
        //用户状态
        if (isset($where['status']) && $where['status'] != '') {
            $model = $model->where($userAlias . 'status', $where['status']);
        }
        //用户是否为推广员
        if (isset($where['is_promoter']) && $where['is_promoter'] != '') {
            $model = $model->where($userAlias . 'is_promoter', $where['is_promoter']);
        }
        //用户标签
        if (isset($where['label_id']) && $where['label_id']) {
            $model = $model->whereIn($userAlias . 'uid', function ($query) use ($where) {
                if (is_array($where['label_id'])) {
                    $query->name('user_label_relation')->whereIn('label_id', $where['label_id'])->field('uid')->select();
                } else {
                    $query->name('user_label_relation')->where('label_id', $where['label_id'])->field('uid')->select();
                }
            });
        }

        //是否付费会员
        if (isset($where['isMember']) && $where['isMember'] != '') {
            if ($where['isMember'] == 0) {
                $model = $model->where($userAlias . 'is_money_level', 0);
            } else {
                $model = $model->where($userAlias . 'is_money_level', '>', 0);
            }

        }
        //用户昵称,uid,手机号搜索
        $fieldKey = $where['field_key'] ?? '';
        $nickname = $where['nickname'] ?? '';
        if ($fieldKey && $nickname && in_array($fieldKey, $this->withField)) {
            switch ($fieldKey) {
                case "nickname":
                    $model = $model->where($userAlias . trim($fieldKey), 'like', "%" . trim($nickname) . "%");
                    break;
                case "phone":
                    $model = $model->where($userAlias . trim($fieldKey), 'like', "%" . trim($nickname));
                    break;
                case "uid":
                    $model = $model->where($userAlias . trim($fieldKey), trim($nickname));
                    break;
            }
        } else if (!$fieldKey && $nickname) {
            $model = $model->where($userAlias . 'nickname|' . $userAlias . 'uid|' . $userAlias . 'phone', 'LIKE', "%$where[nickname]%");
        }
        //用户类型
        if (isset($where['user_type']) && $where['user_type']) {
            $model = $model->where($userAlias . 'user_type', $where['user_type']);
        }
        if (isset($where['time'])) {
            $model->withSearch(['time'], ['time' => $where['time'], 'timeKey' => 'u.add_time']);
        }
        return $field ? $model->field($field) : $model;
    }
}
