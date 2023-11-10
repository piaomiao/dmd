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

use app\dao\store\SystemStoreShareDao;
use app\services\BaseServices;
use app\services\order\OtherOrderServices;
use app\services\order\store\BranchOrderServices;
use app\services\system\SystemRoleServices;
use app\services\user\UserCardServices;
use app\services\user\UserRechargeServices;
use app\services\user\UserSpreadServices;
use crmeb\exceptions\AdminException;
use crmeb\services\FormBuilder;
use think\exception\ValidateException;

/**
 * 门店股东
 * Class SystemStoreShareServices
 * @package app\services\system\store
 * @mixin SystemStoreShareDao
 */
class SystemStoreShareServices extends BaseServices
{
    /**
     * @var FormBuilder
     */
    protected $builder;

    /**
     * 构造方法
     * SystemStoreShareServices constructor.
     * @param SystemStoreShareDao $dao
     */
    public function __construct(SystemStoreShareDao $dao, FormBuilder $builder)
    {
        $this->dao = $dao;
        $this->builder = $builder;
    }


    /**
     * 获取门店客服列表
     * @param int $store_id
     * @param string $field
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getShareholderList(int $store_id, string $field = '*')
    {
        return $this->dao->getWhere()->where('store_id', $store_id)->where('status', 1)->where('is_del', 0)->field($field)->select()->toArray();
    }

    public function get($id) {
        return $this->dao->get($id);
    }

    public function getSharesByUid($uid)
    {
        $res = [];
        $list = $this->dao->getWhere()->with('store')->where('uid', $uid)->where('status', 1)->where('is_del', 0)
            ->select();
        return $list;
        // foreach ($list as $v) {
        //     $row = $v->toArray();
        //     $res[] = $row;
        // }
        // return $res;
    }

    /**
     * Undocumented function
     *
     * @param integer $store_id
     * @return array
     */
    public function getActiveShareholders(int $store_id): array
    {
        return $this->dao->getWhere()->where('store_id', $store_id)->where('status', 1)->where('is_del', 0)
            ->where('add_time', ">", strtotime("-2 years"))->field("*, sum(number) as number")->group("uid")->select()->toArray();
    }

    /**
     * Undocumented function
     *
     * @param integer $store_id
     * @return int
     */
    public function getActiveShareholderTotal(int $store_id): int
    {
        return $this->dao->getWhere()->where('store_id', $store_id)->where('status', 1)->where('is_del', 0)
            ->where('add_time', ">", strtotime("-2 years"))->sum("number");
    }

    /**
     * 获取股东详情
     * @param int $id
     * @param string $field
     * @return array|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getShareInfo(int $id, string $field = '*')
    {
        $info = $this->dao->getOne(['id' => $id, 'is_del' => 0], $field);
        if (!$info) {
            throw new ValidateException('股东不存在');
        }
        return $info;
    }

    /**
     * 根据uid获取门店股东信息
     * @param int $uid
     * @param int $store_id
     * @param string $field
     * @return array|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getShareInfoByUid(int $uid, int $store_id = 0, string $field = '*')
    {
        $where = ['uid' => $uid, 'is_del' => 0, 'status' => 1];
        if ($store_id) $where['store_id'] = $store_id;
        $info = $this->dao->getOne($where, $field);
        if (!$info) {
            throw new ValidateException('股东不存在');
        }
        return $info;
    }

    /**
     * 获取门店｜股东统计
     * @param int $store_id
     * @param int $share_id
     * @param string $time
     * @return array
     */
    public function getStoreData(int $uid, int $store_id, int $share_id = 0, string $time = 'today')
    {
        $where = ['store_id' => $store_id, 'time' => $time];
        if ($share_id) {
            $where['share_id'] = $share_id;
        }
        $data = [];
        $order_where = ['pid' => 0, 'paid' => 1, 'refund_status' => [0, 3], 'is_del' => 0, 'is_system_del' => 0];
        /** @var BranchOrderServices $orderServices */
        $orderServices = app()->make(BranchOrderServices::class);
        $data['send_price'] = $orderServices->sum($where + $order_where + ['type' => 7], 'pay_price', true);
        $data['send_count'] = $orderServices->count($where + $order_where + ['type' => 7]);
        $data['refund_price'] = $orderServices->sum($where + ['status' => -3], 'pay_price', true);
        $data['refund_count'] = $orderServices->count($where + ['status' => -3]);
        $data['cashier_price'] = $orderServices->sum($where + $order_where + ['type' => 6], 'pay_price', true);
        $data['writeoff_price'] = $orderServices->sum($where + $order_where + ['type' => 5], 'pay_price', true);
        /** @var OtherOrderServices $otherOrder */
        $otherOrder = app()->make(OtherOrderServices::class);
        $data['svip_price'] = $otherOrder->sum($where + ['paid' => 1, 'type' => [0, 1, 2, 4]], 'pay_price', true);
        /** @var UserRechargeServices $userRecharge */
        $userRecharge = app()->make(UserRechargeServices::class);
        $data['recharge_price'] = $userRecharge->getWhereSumField($where + ['paid' => 1], 'price');
        /** @var UserSpreadServices $userSpread */
        $userSpread = app()->make(UserSpreadServices::class);
        $data['spread_count'] = $userSpread->count($where + ['timeKey' => 'spread_time']);
        /** @var UserCardServices $userCard */
        $userCard = app()->make(UserCardServices::class);
        $data['card_count'] = $userCard->count($where + ['is_submit' => 1]);
        return $data;
    }

    /**
     * 判断是否是有权限核销的股东
     * @param $uid
     * @return bool
     */
    public function verifyStatus($uid)
    {
        return (bool)$this->dao->getOne(['uid' => $uid, 'status' => 1, 'is_del' => 0, 'verify_status' => 1]);
    }

    /**
     * 获取股东列表
     * @param array $where
     * @param array $with
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getStoreShareList(array $where, array $with = [])
    {
        //        $with = array_merge($with, [
        //            'workMember' => function ($query) {
        //                $query->field(['uid', 'name', 'position', 'qr_code', 'external_position']);
        //            }
        //        ]);
        [$page, $limit] = $this->getPageValue();
        $list = $this->dao->getStoreShareList($where, '*', $page, $limit, $with);
        if ($list) {
            foreach ($list as &$item) {
                // $item['delete_time'] = $item['user']['delete_time'];
                // $item['share_name'] = $item['user']['share_name'];
                // $item['share_phone'] = $item['user']['share_phone'];
            }
        }
        $count = $this->dao->count($where);
        return compact('list', 'count');
    }

    /**
     * 不查询总数
     * @param array $where
     * @param array $with
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getStoreShare(array $where, array $with = [])
    {
        [$page, $limit] = $this->getPageValue();
        $list = $this->dao->getStoreShareList($where, '*', $page, $limit, $with);
        foreach ($list as $key => $item) {
            unset($list[$key]['pwd']);
        }
        return $list;
    }

    /**
     * 股东详情
     * @param int $id
     * @return array|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function read(int $id)
    {
        $shareInfo = $this->getShareInfo($id);
        $info = [
            'id' => $id,
            'headerList' => $this->getHeaderList($id, $shareInfo),
            'ps_info' => $shareInfo
        ];
        return $info;
    }

    /**
     * 获取单个股东统计信息
     * @param $id 用户id
     * @return mixed
     */
    public function shareDetail(int $id, string $type)
    {
        $shareInfo = $this->getShareInfo($id);
        if (!$shareInfo) {
            throw new AdminException('股东不存在');
        }
        $where = ['store_id' => $shareInfo['store_id'], 'share_id' => $shareInfo['id']];
        $data = [];
        switch ($type) {
            case 'cashier_order':
                /** @var BranchOrderServices $orderServices */
                $orderServices = app()->make(BranchOrderServices::class);
                $where = array_merge($where, ['pid' => 0, 'type' => 6, 'paid' => 1, 'refund_status' => [0, 3], 'is_del' => 0, 'is_system_del' => 0]);
                $field = ['uid', 'order_id', 'real_name', 'total_num', 'total_price', 'pay_price', 'FROM_UNIXTIME(pay_time,"%Y-%m-%d") as pay_time', 'paid', 'pay_type', 'type', 'activity_id', 'pink_id'];
                $data = $orderServices->getStoreOrderList($where, $field, [], true);
                break;
            case 'self_order':
                /** @var BranchOrderServices $orderServices */
                $orderServices = app()->make(BranchOrderServices::class);
                $where = array_merge($where, ['pid' => 0, 'type' => 7, 'paid' => 1, 'refund_status' => [0, 3], 'is_del' => 0, 'is_system_del' => 0]);
                $field = ['uid', 'order_id', 'real_name', 'total_num', 'total_price', 'pay_price', 'FROM_UNIXTIME(pay_time,"%Y-%m-%d") as pay_time', 'paid', 'pay_type', 'type', 'activity_id', 'pink_id'];
                $data = $orderServices->getStoreOrderList($where, $field, [], true);
                break;
            case 'writeoff_order':
                /** @var BranchOrderServices $orderServices */
                $orderServices = app()->make(BranchOrderServices::class);
                $where = array_merge($where, ['pid' => 0, 'type' => 5, 'paid' => 1, 'refund_status' => [0, 3], 'is_del' => 0, 'is_system_del' => 0]);
                $field = ['uid', 'order_id', 'real_name', 'total_num', 'total_price', 'pay_price', 'FROM_UNIXTIME(pay_time,"%Y-%m-%d") as pay_time', 'paid', 'pay_type', 'type', 'activity_id', 'pink_id'];
                $data = $orderServices->getStoreOrderList($where, $field, [], true);
                break;
            case 'recharge':
                /** @var UserRechargeServices $userRechargeServices */
                $userRechargeServices = app()->make(UserRechargeServices::class);
                $data = $userRechargeServices->getRechargeList($where + ['paid' => 1]);
                break;
            case 'spread':
                /** @var UserSpreadServices $userSpreadServices */
                $userSpreadServices = app()->make(UserSpreadServices::class);
                $data = $userSpreadServices->getSpreadList($where);
                break;
            case 'card':
                /** @var UserCardServices $userCardServices */
                $userCardServices = app()->make(UserCardServices::class);
                $data = $userCardServices->getCardList($where + ['is_submit' => 1]);
                break;
            case 'svip':
                /** @var OtherOrderServices $otherOrderServices */
                $otherOrderServices = app()->make(OtherOrderServices::class);
                $data = $otherOrderServices->getMemberRecord($where);
                break;
            default:
                throw new AdminException('type参数错误');
        }
        return $data;
    }

    /**
     * 股东详情头部信息
     * @param int $id
     * @param array $shareInfo
     * @return array[]
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getHeaderList(int $id, $shareInfo = [])
    {
        if (!$shareInfo) {
            $shareInfo = $this->dao->get($id);
        }
        $where = ['store_id' => $shareInfo['store_id'], 'share_id' => $shareInfo['id']];
        /** @var BranchOrderServices $orderServices */
        $orderServices = app()->make(BranchOrderServices::class);
        $cashier_order = $orderServices->sum($where + ['pid' => 0, 'type' => 6, 'paid' => 1, 'refund_status' => 0, 'is_del' => 0, 'is_system_del' => 0], 'pay_price', true);
        $writeoff_order = $orderServices->sum($where + ['pid' => 0, 'type' => 5, 'paid' => 1, 'refund_status' => 0, 'is_del' => 0, 'is_system_del' => 0], 'pay_price', true);
        $self_order = $orderServices->sum($where + ['pid' => 0, 'type' => 7, 'paid' => 1, 'refund_status' => 0, 'is_del' => 0, 'is_system_del' => 0], 'pay_price', true);
        /** @var UserRechargeServices $userRechargeServices */
        $userRechargeServices = app()->make(UserRechargeServices::class);
        $recharge = $userRechargeServices->sum($where + ['paid' => 1], 'price', true);
        /** @var UserSpreadServices $userSpreadServices */
        $userSpreadServices = app()->make(UserSpreadServices::class);
        $spread = $userSpreadServices->count($where);
        /** @var UserCardServices $userCardServices */
        $userCardServices = app()->make(UserCardServices::class);
        $card = $userCardServices->count($where + ['is_submit' => 1]);
        /** @var OtherOrderServices $otherOrderServices */
        $otherOrderServices = app()->make(OtherOrderServices::class);
        $svip = $otherOrderServices->sum($where, 'pay_price', true);
        return [
            [
                'title' => '收银订单',
                'value' => $cashier_order,
                'key' => '元',
            ],
            [
                'title' => '核销订单',
                'value' => $writeoff_order,
                'key' => '元',
            ],
            [
                'title' => '配送订单',
                'value' => $self_order,
                'key' => '元',
            ],
            [
                'title' => '充值订单',
                'value' => $recharge,
                'key' => '元',
            ],
            [
                'title' => '付费会员',
                'value' => $svip,
                'key' => '元',
            ],
            [
                'title' => '推广用户数',
                'value' => $spread,
                'key' => '人',
            ],
            [
                'title' => '激活会员卡',
                'value' => $card,
                'key' => '张',
            ]
        ];
    }

    /**
     * 获取select选择框中的门店列表
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getStoreSelectFormData()
    {
        /** @var SystemStoreServices $service */
        $service = app()->make(SystemStoreServices::class);
        $menus = [];
        foreach ($service->getStore() as $menu) {
            $menus[] = ['value' => $menu['id'], 'label' => $menu['name']];
        }
        return $menus;
    }

    /**
     * 编辑核销员form表单
     * @param int $id
     * @return array
     * @throws \FormBuilder\Exception\FormBuilderException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function updateForm(int $id)
    {
        $storeShare = $this->dao->get($id);
        if (!$storeShare) {
            throw new AdminException('没有查到信息,无法修改');
        }
        return create_form('修改核销员', $this->createShareForm($storeShare->toArray()), $this->url('/merchant/store_share/save/' . $id));
    }

    /**
     * 获取门店股东
     * @param $where
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getStoreAdminList($where)
    {
        [$page, $limit] = $this->getPageValue();
        $list = $this->dao->getStoreAdminList($where, $page, $limit);
        /** @var SystemRoleServices $service */
        $service = app()->make(SystemRoleServices::class);
        $allRole = $service->getRoleArray(['type' => 2, 'store_id' => $where['store_id']]);
        foreach ($list as &$item) {
            if ($item['roles']) {
                $roles = [];
                foreach ($item['roles'] as $id) {
                    if (isset($allRole[$id])) $roles[] = $allRole[$id];
                }
                if ($roles) {
                    $item['roles'] = implode(',', $roles);
                } else {
                    $item['roles'] = '';
                }
            }
        }
        $count = $this->dao->count($where);
        return compact('list', 'count');
    }

    /**
     * 添加门店管理员
     * @param int $store_id
     * @param $level
     * @return array
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function createStoreAdminForm(int $store_id)
    {
        // $field[] = $this->builder->input('share_name', '管理员名称')->col(24)->required();
        $field[] = $this->builder->frameImage('avatar', '股东头像', $this->url(config('admin.admin_prefix') . '/widget.images/index', ['fodder' => 'avatar'], true))->icon('ios-add')->width('960px')->height('505px')->modal(['footer-hide' => true]);
        // $field[] = $this->builder->input('phone', '手机号码')->col(24)->required();
        $field[] = $this->builder->radio('status', '状态', 1)->options([['value' => 1, 'label' => '开启'], ['value' => 0, 'label' => '关闭']]);
        return create_form('添加门店股东', $field, $this->url('/system/admin'));
    }

    /**
     * 修改门店管理员
     * @param $id
     * @param $level
     * @return array
     * @throws \FormBuilder\Exception\FormBuilderException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function updateStoreAdminForm($id, $level)
    {
        $adminInfo = $this->dao->get($id);
        if (!$adminInfo) {
            throw new AdminException('股东不存在!');
        }
        if ($adminInfo->is_del) {
            throw new AdminException('门店股东已经删除');
        }
        $adminInfo = $adminInfo->toArray();
        // $field[] = $this->builder->input('share_name', '门店管理员名称', $adminInfo['share_name'])->col(24)->required('请填写门店管理员名称');
        $field[] = $this->builder->frameImage('avatar', '管理员头像', $this->url(config('admin.store_prefix') . '/widget.images/index', ['fodder' => 'avatar'], true), $adminInfo['avatar'] ?? '')->icon('ios-add')->width('960px')->height('505px')->modal(['footer-hide' => true]);
        // $field[] = $this->builder->input('phone', '手机号码', $adminInfo['phone'])->col(24)->required();
        $field[] = $this->builder->radio('status', '状态', (int)$adminInfo['status'])->options([['value' => 1, 'label' => '开启'], ['value' => 0, 'label' => '关闭']]);
        return create_form('添加门店股东', $field, $this->url('/system/admin/' . $id), 'put');
    }

    /**
     * 添加门店股东
     * @param int $store_id
     * @param $level
     * @return array
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function createStoreShareForm(int $store_id)
    {
        // $field[] = $this->builder->input('share_name', '股东名称')->col(24)->required('请输入门店股东名称');
        $field[] = $this->builder->frameImage('image', '商城用户', $this->url(config('admin.store_prefix') . '/system.User/list', ['fodder' => 'image'], true))->icon('ios-add')->width('960px')->height('450px')->modal(['footer-hide' => true])->Props(['srcKey' => 'image']);
        $field[] = $this->builder->hidden('uid', 0);
        $field[] = $this->builder->hidden('avatar', '');
        // $field[] = $this->builder->input('phone', '手机号码')->col(24)->required('请输入手机号');
        $field[] = $this->builder->radio('status', '状态', 1)->options([['value' => 1, 'label' => '开启'], ['value' => 0, 'label' => '关闭']]);
        return create_form('添加门店股东', $field, $this->url('/share/share'));
    }

    /**
     * 编辑门店股东
     * @param $id
     * @return array
     * @throws \FormBuilder\Exception\FormBuilderException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function updateStoreShareForm($id)
    {
        // return $shareInfo->user;
        // $field[] = $this->builder->input('share_name', '股东名称', $shareInfo['share_name'])->col(24)->required('请填写门店股东名称');
        if ($id) {
            $shareInfo = $this->dao->get($id);
            if (!$shareInfo) {
                throw new AdminException('门店股东不存在!');
            }
            $field[] = $this->builder->frameImage('avatar', '用户头像', $this->url(config('admin.store_prefix') . '/widget.images/index', ['fodder' => 'avatar'], true), $shareInfo['user']['avatar'] ?? '')
                ->icon('ios-add')->width('100px')->height('100px')
                ->allowRemove(false)->modal(['footer-hide' => true]);
            $field[] = $this->builder->input('nickname', '股东', $shareInfo['nickname'] . ($shareInfo['real_name'] ? '/' . $shareInfo['real_name'] : '') . ($shareInfo['phone']?'/' . $shareInfo['phone']: ''))->disabled(true);
            $field[] = $this->builder->input('storename', '店铺', $shareInfo['store']['name'])->disabled(true);
        } else { //没绑定过商城用户
            $field[] = $this->builder->frameImage('image', '商城用户', $this->url(config('admin.admin_prefix') . '/system.user/list', ['fodder' => 'image'], true))->icon('ios-add')->width('960px')->height('610px')->modal(['footer-hide' => true])->Props(['srcKey' => 'image']);
            $field[] = $this->builder->hidden('uid', 0);
            $field[] = $this->builder->hidden('avatar', '');
            $field[] = $this->builder->frameImage('image2', '店铺', $this->url(config('admin.admin_prefix') . '/system.store/list', ['fodder' => 'image'], true))->icon('ios-add')->width('960px')->height('610px')->modal(['footer-hide' => true])->Props(['srcKey' => 'store_image']);
            $field[] = $this->builder->hidden('store_id', 0);
            $field[] = $this->builder->hidden('store_image', '');
        }
        $field[] = $this->builder->number('number', '股份数', $shareInfo['number']??1)->min(0);
        // $field[] = $this->builder->input('phone', '手机号码', $shareInfo['phone'])->col(24)->required('请输入手机号');
        $field[] = $this->builder->radio('status', '状态', 1)->options([['value' => 1, 'label' => '开启'], ['value' => 0, 'label' => '关闭']]);
        return create_form('编辑门店股东', $field, $this->url('/shareholder/save/' . $id), 'post');
    }

}
