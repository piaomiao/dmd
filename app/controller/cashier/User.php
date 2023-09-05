<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------
namespace app\controller\cashier;

use app\Request;
use app\services\cashier\UserServices;
use app\services\order\OtherOrderServices;
use app\services\store\StoreUserServices;
use app\services\order\StoreCartServices;
use app\services\store\SystemStoreStaffServices;
use app\services\user\member\MemberCardServices;
use app\webscoket\SocketPush;
use crmeb\services\CacheService;
use think\exception\ValidateException;

/**
 * 收银台用户控制器
 */
class User extends AuthController
{
    /**
     * 修改收银员信息
     * @param Request $request
     * @param SystemStoreStaffServices $services
     * @return mixed
     */
    public function updatePwd(Request $request, SystemStoreStaffServices $services)
    {
        $data = $request->postMore([
            ['real_name', ''],
            ['pwd', ''],
            ['new_pwd', ''],
            ['conf_pwd', ''],
            ['avatar', ''],
        ]);
        if ($data['pwd'] && !preg_match('/^(?![^a-zA-Z]+$)(?!\D+$).{6,}$/', $data['new_pwd'])) {
            return $this->fail('设置的密码过于简单(不小于六位包含数字字母)');
        }
        if ($services->updateStaffPwd($this->cashierId, $data))
            return $this->success('修改成功');
        else
            return $this->fail('修改失败');
    }

    /**
     * 获取登录店员详情
     * @return mixed
     */
    public function getCashierInfo()
    {
        return $this->success($this->cashierInfo);
    }

    /**
     * 收银台选择用户列表
     * @param Request $request
     * @param UserServices $services
     * @return mixed
     */
    public function getUserList(Request $request, StoreUserServices $storeUserservices, \app\services\user\UserServices $services)
    {
        $data = $request->getMore([
            ['keyword', ''],
            ['field_key', '']
        ]);
        if ($data['keyword']) {
            if ($data['field_key'] == 'all') {
                $data['field_key'] = '';
            }
            if ($data['field_key'] && in_array($data['field_key'], ['uid', 'phone'])) {
                $where[$data['field_key']] = trim($data['keyword']);
            } else {
                $where['store_like'] = trim($data['keyword']);
            }
            $where['is_filter_del'] = 1;
            $list = $services->getUserList($where);
            if (isset($list['list']) && $list['list']) {
                foreach ($list['list'] as &$item) {
                    //用户类型
                    if ($item['user_type'] == 'routine') {
                        $item['user_type'] = '小程序';
                    } else if ($item['user_type'] == 'wechat') {
                        $item['user_type'] = '公众号';
                    } else if ($item['user_type'] == 'h5') {
                        $item['user_type'] = 'H5';
                    } else if ($item['user_type'] == 'pc') {
                        $item['user_type'] = 'PC';
                    } else if ($item['user_type'] == 'app') {
                        $item['user_type'] = 'APP';
                    } else $item['user_type'] = '其他';
                }
            }
            return $this->success($list);
        } else {
            $data['is_filter_del'] = 1;
            return app('json')->success($storeUserservices->index($data, $this->storeId));
        }
    }

    /**
     * 获取当前门店店员列表和店员信息
     * @param Request $request
     * @param SystemStoreStaffServices $services
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getCashierList(Request $request, SystemStoreStaffServices $services)
    {
        $where = $request->getMore([
            ['keyword', '']
        ]);
        $where['store_id'] = $this->storeId;
        $where['is_del'] = 0;
        return $this->success([
            'staffInfo' => $request->cashierInfo(),
            'staffList' => $services->getStoreStaff($where),
            'count' => $services->count($where)
        ]);
    }

    /**
     * 游客切换到用户
     * @param Request $request
     * @param StoreCartServices $services
     * @param $cashierId
     * @return mixed
     */
    public function switchCartUser(Request $request, StoreCartServices $services, $cashierId)
    {
        [$uid, $toUid, $isTourist] = $request->postMore([
            ['uid', 0],
            ['to_uid', 0],
            ['is_tourist', 0]
        ], true);
        if ($isTourist && $uid) {
            $where = ['tourist_uid' => $uid, 'store_id' => $this->storeId, 'staff_id' => $cashierId];
            $touristCart = $services->getCartList($where);
            if ($touristCart) {
                $userWhere = ['uid' => $toUid, 'store_id' => $this->storeId, 'staff_id' => $cashierId];
                $userCarts = $services->getCartList($userWhere);
                if ($userCarts) {
                    foreach ($touristCart as $cart) {
                        foreach ($userCarts as $userCart) {
                            //游客商品 存在用户购物车商品中
                            if ($cart['product_id'] == $userCart['product_id'] && $cart['product_attr_unique'] == $userCart['product_attr_unique']) {
                                //修改用户商品数量 删除游客购物车这条数据
                                $services->update(['id' => $userCart['id']], ['cart_num' => bcadd((string)$cart['cart_num'], (string)$userCart['cart_num'])]);
                                $services->delete(['id' => $cart['id']]);
                            }
                        }
                    }
                }
                //发送消息
                try {
                    SocketPush::instance()
                        ->to($this->cashierId)
                        ->setUserType('cashier')
                        ->type('changCart')
                        ->data(['uid' => $uid])
                        ->push();
                } catch (\Throwable $e) {
                }
            }
            $services->update($where, ['uid' => $toUid, 'tourist_uid' => '']);
        }
        return $this->success('修改成功');
    }

    /**
     * 用户信息
     * @param Request $request
     * @param \app\services\user\UserServices $services
     * @param StoreCartServices $cartServices
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getUserInfo(Request $request, \app\services\user\UserServices $services, StoreCartServices $cartServices)
    {
        $code = $request->post('code', '');
        $uid = $request->post('uid', '');
        if (!$code && !$uid) {
            return $this->fail('缺少参数');
        }
        $field = ['uid', 'avatar', 'phone', 'nickname', 'now_money', 'integral'];
        if ($uid) {
            $userInfo = $services->getUserInfo($uid, $field);
        } elseif ($code) {
            $userInfo = $services->get(['uniqid' => $code], $field);
        }

        if (!isset($userInfo) && !$userInfo) {
            return $this->fail('用户不存在');
        }
        $cart = $request->post('cart', []);
        if ($cart) {
            $cartServices->batchAddCart($cart, $this->storeId, $userInfo->uid);
        }

        return $this->success($userInfo->toArray());
    }

    /**
     * 收银台获取当前用户信息
     * @param \app\services\user\UserServices $userServices
     * @param $uid
     * @return mixed
     */
    public function getUidInfo(\app\services\user\UserServices $userServices, $uid)
    {
        return $this->success($userServices->read((int)$uid));
    }

    /**
     * 收银台用户记录
     * @param Request $request
     * @param \app\services\user\UserServices $userServices
     * @param $uid
     * @return mixed
     */
    public function userRecord(Request $request, \app\services\user\UserServices $userServices, $uid)
    {
        $type = $request->get('type', '');
        return $this->success($userServices->oneUserInfo((int)$uid, $type));
    }

    /**
     * 切换用户、用户切换到其他用户、用户切换到游客
     * @param Request $request
     * @return mixed
     */
    public function swithUser(Request $request)
    {
        $uid = $request->post('uid', 0);
        $touristUid = $request->post('tourist_uid', 0);
        $cashierId = $request->post('cashier_id', 0);
        $changePrice = $request->post('change_price', 0);
        $changCartRemove = $request->post('chang_cart_remove', 0);

        if (!$uid && !$touristUid && !$cashierId && !$changePrice && !$changCartRemove) {
            return $this->fail('缺少参数');
        }

        $res = CacheService::redisHandler()->get('aux_screen_' . $this->cashierId);

        if ($uid) {

            if ($res && is_array($res)) {
                $res['uid'] = $uid;
                $res['tourist'] = false;
                $res['tourist_uid'] = 0;
                CacheService::redisHandler(CacheService::CASHIER_AUX_SCREEN_TAG . '_' . $this->storeId)
                    ->set('aux_screen_' . $this->cashierId, $res);
            } else {
                //游客切换到用户。或者用户之间切换
                CacheService::redisHandler(CacheService::CASHIER_AUX_SCREEN_TAG . '_' . $this->storeId)
                    ->set('aux_screen_' . $this->cashierId, [
                        'uid' => $uid,
                        'cashier_id' => 0,
                        'tourist_uid' => 0,
                        'tourist' => false
                    ]);
            }

            //发送消息
            try {
                SocketPush::instance()->to($this->cashierId)->setUserType('cashier')->type('changUser')->data(['uid' => $uid])->push();
            } catch (\Throwable $e) {
            }

        } else if ($touristUid) {
            if ($res && is_array($res)) {
                $res['tourist_uid'] = $touristUid;
                $res['tourist'] = true;
                $res['uid'] = 0;
                CacheService::redisHandler(CacheService::CASHIER_AUX_SCREEN_TAG . '_' . $this->storeId)
                    ->set('aux_screen_' . $this->cashierId, $res);
            } else {
                //用户切换到游客
                CacheService::redisHandler(CacheService::CASHIER_AUX_SCREEN_TAG . '_' . $this->storeId)
                    ->set('aux_screen_' . $this->cashierId, [
                        'uid' => 0,
                        'cashier_id' => 0,
                        'tourist_uid' => $touristUid,
                        'tourist' => true
                    ]);
            }

            //发送消息
            try {
                SocketPush::instance()->to($this->cashierId)->setUserType('cashier')->type('changUser')->data(['tourist_uid' => $touristUid])->push();
            } catch (\Throwable $e) {
            }
        } else if ($cashierId) {
            //切换店员
            if ($res && is_array($res)) {
                $res['cashier_id'] = $cashierId;
                CacheService::redisHandler(CacheService::CASHIER_AUX_SCREEN_TAG . '_' . $this->storeId)
                    ->set('aux_screen_' . $this->cashierId, $res);
            } else {
                CacheService::redisHandler(CacheService::CASHIER_AUX_SCREEN_TAG . '_' . $this->storeId)
                    ->set('aux_screen_' . $this->cashierId, [
                        'uid' => 0,
                        'cashier_id' => $cashierId,
                        'tourist_uid' => 0,
                        'tourist' => true
                    ]);
            }

            //发送消息
            try {
                SocketPush::instance()->to($this->cashierId)->setUserType('cashier')->type('changUser')->data(['cashier_id' => $cashierId])->push();
            } catch (\Throwable $e) {
            }
        } else if ($changePrice) {
            //发送消息
            try {
                SocketPush::instance()->to($this->cashierId)->setUserType('cashier')->type('changUser')->data(['change_price' => $changePrice])->push();
            } catch (\Throwable $e) {
            }
        } else if ($changCartRemove) {
            //发送消息
            try {
                SocketPush::instance()->to($this->cashierId)->setUserType('cashier')->type('changCartRemove')->push();
            } catch (\Throwable $e) {
            }
        }

        return $this->success();
    }

    /**
     * 获取副屏用户信息
     * @return mixed
     */
    public function getAuxScreenInfo()
    {
        $res = CacheService::redisHandler()->get('aux_screen_' . $this->cashierId);

        $data = [];
        $key = ['cashier_id' => 0, 'tourist_uid' => 0, 'uid' => 0, 'tourist' => false];
        foreach ($key as $k => $v) {
            $data[$k] = $res[$k] ?? $v;
        }
        return $this->success($data);
    }

    /**获取会员类型
     * @param Request $request
     * @return mixed
     */
    public function getMemberCard(Request $request)
    {
        [$is_money_level, $overdue_time] = $request->getMore([
            ['is_money_level', 0],
            ['overdue_time', 0],
        ], true);
        /** @var MemberCardServices $memberCardServices */
        $memberCardServices = app()->make(MemberCardServices::class);
        $member_type = $memberCardServices->DoMemberType(false);
        if (!$is_money_level) $overdue_time = time();
        foreach ($member_type as $key => &$item) {
            if (!$overdue_time || $item['type'] == 'ever' && $item['vip_day'] == -1) {
                $item['overdue_time'] = '';
            } else {
                $item['overdue_time'] = date('Y-m-d H:i:s', $overdue_time + $item['vip_day'] * 86400);
            }
        }
        return $this->success($member_type);
    }

    /**会员充值
     * @param Request $request
     * @param UserServices $userServices
     * @return mixed
     */
    public function merberRecharge(Request $request, UserServices $userServices)
    {
        [$uid, $price, $memberType, $payType, $authCode] = $request->postMore([
            ['uid', 0],
            ['price', 0],
            ['merber_id', 0],
            [['pay_type', 'd'], 2], //2=用户扫码支付，3=付款码扫码支付, 4=现金支付
            ['auth_code', '']
        ], true);
        if (!$authCode && $payType == 3) {
            return $this->fail('缺少付款码二维码CODE');
        }
        if (!$price || $price <= 0) {
            return $this->fail('充值金额不能为0元!');
        }

        $storeMinRecharge = sys_config('store_user_min_recharge');
        if ($price < $storeMinRecharge) return $this->fail('充值金额不能低于' . $storeMinRecharge);
        /** @var OtherOrderServices $OtherOrderServices */
        $OtherOrderServices = app()->make(OtherOrderServices::class);
        $re = $OtherOrderServices->recharge($uid, $price, $memberType, (int)$payType, 'store', $this->cashierInfo, $authCode);
        if ($re) {
            $msg = $re['msg'];
            unset($re['msg']);
            return $this->success($msg, $re);
        }
        return $this->fail('充值失败');
    }

    /**显示指定的资源
     * @param $id
     * @param \app\services\user\UserServices $services
     * @return mixed
     */
    public function read($id, \app\services\user\UserServices $services)
    {
        if (is_string($id)) {
            $id = (int)$id;
        }
        return $this->success($services->read($id));
    }

    /**获取单个用户信息
     * @param Request $request
     * @param $id
     * @param \app\services\user\UserServices $services
     * @return mixed
     */
    public function oneUserInfo(Request $request, $id, \app\services\user\UserServices $services)
    {
        $data = $request->getMore([
            ['type', ''],
        ]);
        $id = (int)$id;
        if ($data['type'] == '') return $this->fail('缺少参数');
        return $this->success($services->oneUserInfo($id, $data['type']));
    }
}
