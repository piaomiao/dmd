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

namespace app\services\shareholder;

use app\jobs\shareholder\AutoShareholderJob;
use app\services\BaseServices;
use app\services\order\StoreOrderServices;
use app\services\order\StoreOrderStatusServices;
use app\services\other\QrcodeServices;
use app\services\system\attachment\SystemAttachmentServices;
use app\services\user\UserBrokerageServices;
use app\services\user\UserExtractServices;
use app\services\user\UserServices;
use crmeb\exceptions\AdminException;
use crmeb\services\{QrcodeService, UploadService, wechat\MiniProgram};
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 分销员
 * Class ShareholderManageServices
 * @package app\services\shareholder
 */
class ShareholderManageServices extends BaseServices
{


    public function getShareLogs($id)
    {
        [$page, $limit] = $this->getPageValue();
        $list = Db::name("system_store_share_log")->where("share_id", $id)->order("id desc")->page($page, $limit)->select()->toArray();
        $count = Db::name("system_store_share_log")->where("share_id", $id)->count();
        foreach($list as $k=>$v) {
            $list[$k]['add_time'] = date('Y-m-d H:i', $v['add_time']);
        }
        return [
            'list' => $list,
            'data' => $count,
        ];

    }
    /**
     * @param array $where
     * @return array
     */
    public function shareholderSystemPage(array $where, $is_page = true)
    {
        [$page, $limit] = $this->getPageValue($is_page);
        $list = Db::name("system_store_share")->alias("s")
        ->field("s.id, u.uid, u.nickname, u.avatar, u.phone, u.real_name, u.divide_price, st.name as store_name, s.number as share_number, s.number2, s.add_time, s.status")
        ->leftJoin("user u", "s.uid=u.uid")
        ->leftJoin("system_store st", "s.store_id=st.id")
        ->when(!empty($where['store_id']), function($query) use ($where) {
            $query->where('s.store_id', '=', $where['store_id']);
        })
        ->when(!empty($where['nickname']), function($query) use ($where) {
            $query->where('u.nickname|u.real_name', 'like', $where['nickname']);
        })
        ->when(!empty($where['data']), function($query) use ($where) {
            $query->where('s.add_time', 'between', $where['data']);
        })

        ->page($page, $limit)->select()->toArray();
        $count = Db::name("system_store_share")->alias("s")
        ->leftJoin("user u", "s.uid=u.uid")
        ->leftJoin("system_store st", "s.store_id=st.id")
        ->when(!empty($where['store_id']), function($query) use ($where) {
            $query->where('s.store_id', '=', $where['store_id']);
        })
        ->when(!empty($where['nickname']), function($query) use ($where) {
            $query->where('u.nickname|u.real_name', 'like', $where['nickname']);
        })
        ->when(!empty($where['data']), function($query) use ($where) {
            $query->where('s.add_time', 'between', $where['data']);
        })

        ->count();

        foreach($list as $k=>$v) {
            $list[$k]['add_time'] = date('Y-m-d H:i', $v['add_time']);
        }


        return [
            'list' => $list,
            'data' => $count,
        ];
        // /** @var UserServices $userServices */
        // $userServices = app()->make(UserServices::class);
        // $data = $userServices->getShareholderUserList($where, '*', $is_page);
        // /** @var UserBrokerageServices $userBrokerageServices */
        // $userBrokerageServices = app()->make(UserBrokerageServices::class);
        // foreach ($data['list'] as &$item) {
        //     $item['headimgurl'] = $item['avatar'];
        //     $item['extract_count_price'] = $item['extract'][0]['extract_count_price'] ?? 0;
        //     $item['extract_count_num'] = $item['extract'][0]['extract_count_num'] ?? 0;
        //     $item['broken_commission'] = $userBrokerageServices->getUserFrozenPrice((int)$item['uid'], 2);
        //     // echo Db::getLastSql();

        //     //d//
        //     if ($item['broken_commission'] < 0)
        //         $item['broken_commission'] = 0;
        //     $item['brokerage_money'] = $item['brokerage'][0]['brokerage_money'] ?? 0;
        //     if ($item['brokerage_price'] > $item['broken_commission'])
        //         $item['brokerage_money'] = bcsub($item['divide_price'], $item['broken_commission'], 2);//全部佣金-冻结佣金
        //     else
        //         $item['brokerage_money'] = 0;
        //     $item['new_money'] =  bcadd($item['divide_price'], $item['brokerage_price'], 2);
        //     $item['share_number'] = $item['share'][0]['share_number']??0;
        //     unset($item['extract'], $item['bill']);
        // }
        // return $data;
    }

    /**
     * 分销头部信息
     * @param $where
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getSpreadBadge($where)
    {
        /** @var UserServices $userServices */
        $userServices = app()->make(UserServices::class);
        //获取某个用户可提现金额
        // $data['extract_price'] = $userServices->getSumBrokerage([]);
        
        //分销员人数

        $data['divide_price'] = $userServices->getSumDivide([]);
		//提现次数
        $data['extract_count'] = app()->make(UserExtractServices::class)->getCount(['status'=> 1]);
        
        return [
            [
                'name' => '分成金额(元)',
                'count' => $data['divide_price'],
                'className' => 'md-bug',
                'col' => 6,
            ],
            [
                'name' => '提现次数(次)',
                'count' => $data['extract_count'],
                'className' => 'md-basket',
                'col' => 6,
            ],
            // [
            //     'name' => '未提现金额(元)',
            //     'count' => $data['extract_price'],
            //     'className' => 'ios-at-outline',
            //     'col' => 6,
            // ],
        ];
    }

    /**
     * 推广人列表
     * @param array $where
     * @return mixed
     */
    public function getShareList(array $where)
    {
        /** @var UserServices $userServices */
        $userServices = app()->make(UserServices::class);
        $data = $userServices->getShareList($where);
        foreach ($data['list'] as &$item) {
            $item['create_time'] = $item['create_time'] ? date("Y-m-d H:i:s", $item['create_time']) : '';
        }
        return $data;
    }

    /**
     * 推广人头部信息
     * @param array $where
     * @return array[]
     */
    public function getSairBadge(array $where)
    {
        /** @var UserServices $userServices */
        $userServices = app()->make(UserServices::class);
        $data['number'] = $userServices->getSairCount($where);
        $where['type'] = 1;
        $data['one_number'] = $userServices->getSairCount($where);
        $where['type'] = 2;
        $data['two_number'] = $userServices->getSairCount($where);

        $col = $data['two_number'] > 0 ? 4 : 6;
        return [
            [
                'name' => '总人数(人)',
                'count' => $data['number'],
                'col' => $col,
            ],
            [
                'name' => '一级人数(人)',
                'count' => $data['one_number'],
                'col' => $col,
            ],
            [
                'name' => '二级人数(人)',
                'count' => $data['two_number'],
                'col' => $col,
            ],
        ];
    }

    /**
     * 推广订单
     * @param array $where
     * @return array
     */
    public function getStairOrderList(int $uid, array $where)
    {
        /** @var UserServices $userServices */
        $userServices = app()->make(UserServices::class);
        $userInfo = $userServices->getUserInfo($uid);
        if (!$userInfo) {
            return ['count' => 0, 'list' => []];
        }
        /** @var StoreOrderServices $storeOrder */
        $storeOrder = app()->make(StoreOrderServices::class);
        $data = $storeOrder->getUserStairOrderList($uid, $where);
        if ($data['list']) {
            $uids = array_unique(array_column($data['list'], 'uid'));
            $userList = [];
            if ($uids) {
                $userList = $userServices->getColumn([['uid', 'IN', $uids]], 'nickname,phone,avatar,real_name', 'uid');
            }
            $orderIds = array_column($data['list'], 'id');
            $orderChangTimes = [];
            if ($orderIds) {
                /** @var StoreOrderStatusServices $storeOrderStatus */
                $storeOrderStatus = app()->make(StoreOrderStatusServices::class);
                $orderChangTimes = $storeOrderStatus->getColumn([['oid', 'IN', $orderIds], ['change_type', '=', 'user_take_delivery']], 'change_time', 'oid');
            }
            foreach ($data['list'] as &$item) {
                $user = $userList[$item['uid']] ?? [];
                $item['user_info'] = '';
                $item['avatar'] = '';
                if (count($user)) {
                    $item['user_info'] = $user['nickname'] . '|' . ($user['phone'] ? $user['phone'] . '|' : '') . $user['real_name'];
                    $item['avatar'] = $user['avatar'];
                }
                $item['brokerage_price'] = $item['spread_uid'] == $uid ? $item['one_brokerage'] : $item['two_brokerage'];
                $item['_pay_time'] = $item['pay_time'] ? date('Y-m-d H:i:s', $item['pay_time']) : '';
                $item['_add_time'] = $item['add_time'] ? date('Y-m-d H:i:s', $item['add_time']) : '';
                $item['take_time'] = ($change_time = $orderChangTimes[$item['id']] ?? '') ? date('Y-m-d H:i:s', $change_time) : '暂无';
            }
        }
        return $data;
    }

    /**
     * 清除推广关系
     * @param int $uid
     * @return mixed
     */
    public function delSpread(int $uid)
    {
        /** @var UserServices $userServices */
        $userServices = app()->make(UserServices::class);
        if (!$userServices->userExist($uid)) {
            throw new AdminException('数据不存在');
        }
        if ($userServices->update($uid, ['spread_uid' => 0]) !== false)
            return true;
        else
            throw new AdminException('解除失败');
    }

}