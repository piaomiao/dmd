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
namespace app\controller\api\v2\activity;


use app\Request;
use app\services\activity\collage\UserCollagePartakeServices;
use app\services\activity\collage\UserCollageServices;
use app\services\store\SystemStoreServices;
use app\services\user\UserAddressServices;

/**
 *
 * Class UserCollageController
 * @package app\controller\api\v2\activity
 */
class UserCollageController
{
    protected $services;

    public function __construct(UserCollageServices $services)
    {
        $this->services = $services;
    }

    /**
     * 验证是否在配送范围
     * @param Request $request
     * @return \think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function isWithinScopeDistribution(Request $request)
    {
        [$store_id, $address_id] = $request->getMore([
            ['store_id', 0],
            ['address_id', 0],
        ], true);
        $uid = (int)$request->uid();
        if (!$store_id || !$address_id) return app('json')->fail('参数有误!');
        /** @var UserAddressServices $addressServices */
        $addressServices = app()->make(UserAddressServices::class);
        if (!$addressInfo = $addressServices->getOne(['uid' => $uid, 'id' => $address_id, 'is_del' => 0]))
            return app('json')->fail('地址选择有误!');
        $addressInfo = $addressInfo->toArray();
        /** @var SystemStoreServices $storeService */
        $storeService = app()->make(SystemStoreServices::class);
        $res = $storeService->checkStoreDeliveryScope((int)$store_id, $addressInfo);
        if ($res) {
            return app('json')->successful('ok');
        } else {
            return app('json')->fail('不在配送范围');
        }
    }

    /**
     * 发起拼单
     * @param Request $request
     * @return \think\Response
     */
    public function userInitiateCollage(Request $request)
    {
        [$store_id, $address_id, $shipping_type] = $request->getMore([
            ['store_id', 0],
            ['address_id', 0],
            ['shipping_type', 1]
        ], true);
        $uid = (int)$request->uid();
        if ($shipping_type == 1 && !$address_id) {
            return app('json')->fail('请选择收货地址!');
        } else if ($shipping_type == 2 && !$store_id) {
            return app('json')->fail('请选择门店!');
        } else if ($shipping_type == 3 && !$address_id) {
            return app('json')->fail('请选择收货地址!');
        }
        if (!$this->services->checkCollageStatus()) return app('json')->fail('门店或拼单未开启!');
        try {
            $res = $this->services->setUserCollage($uid, (int)$store_id, (int)$address_id, (int)$shipping_type);
            return app('json')->successful('ok', ['collageId' => $res->id]);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            \think\facade\Log::error('发起拼单失败，原因：' . $msg . $e->getFile() . $e->getLine());
            return app('json')->fail('发起拼单失败');
        }

    }

    /**
     * 检查用户是否发起拼单
     * @param Request $request
     * @return \think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function isUserInitiateCollage(Request $request)
    {
        $uid = (int)$request->uid();
        $collageId = 0;
        $where = ['uid' => $uid, 'type' => 9, 'status' => ['0', '1']];
        $collage = $this->services->getUserCollage($where);
        if ($collage) {
            $collageId = $collage->id;
        }
        return app('json')->successful(['collageId' => $collageId]);
    }

    /**
     * 检查拼单
     * @param Request $request
     * @return \think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function isInitiateCollage(Request $request)
    {
        [$collage_id] = $request->getMore([
            ['collage_id', 0]
        ], true);
        if (!$collage_id) return app('json')->fail('参数有误!');
        $where = ['id' => $collage_id, 'type' => 9];
        $collage = $this->services->getUserCollage($where);
        if (!$collage) return app('json')->fail('拼单不存在');
        return app('json')->successful(['collage' => $collage]);
    }

    /**
     * 取消拼单
     * @param Request $request
     * @return \think\Response
     */
    public function cancelInitiateCollage(Request $request)
    {
        [$collage_id] = $request->getMore([
            ['collage_id', 0]
        ], true);
        $where = ['status' => -1];
        if (!$collage_id) return app('json')->fail('参数有误!');
        $res = $this->services->userUpdate((int)$collage_id, $where);
        if ($res) {
            return app('json')->successful('ok');
        } else {
            return app('json')->fail('取消失败');
        }
    }

    /**
     * 购物车 统计 数量
     * @param Request $request
     * @return mixed
     */
    public function count(Request $request)
    {
        [$numType, $collage_id, $store_id] = $request->getMore([
            ['numType', false],//购物车编号
            ['collage_id', 0],
            ['store_id', 0]
        ], true);
        $uid = (int)$request->uid();
        if (!$collage_id || !$store_id) return app('json')->fail('参数有误!');
        /** @var UserCollagePartakeServices $partakeService */
        $partakeService = app()->make(UserCollagePartakeServices::class);
        $where = ['uid' => $uid, 'collate_code_id' => $collage_id, 'store_id' => $store_id, 'status'=> 1];
        return app('json')->success('ok', $partakeService->getUserPartakeCount($where, (string)$numType, (int)$collage_id, (int)$store_id));
    }

    /**
     * 获取用户购物车
     * @param Request $request
     * @return mixed
     */
    public function getCartList(Request $request)
    {
        $uid = (int)$request->uid();
        [$collage_id, $store_id] = $request->getMore([
            ['collage_id', 0],
            ['store_id', 0]
        ], true);
        if (!$collage_id || !$store_id) return app('json')->fail('参数有误!');
        /** @var UserCollagePartakeServices $partakeService */
        $partakeService = app()->make(UserCollagePartakeServices::class);
        $data = $partakeService->getPartakeList(['uid' => $uid, 'collate_code_id' => $collage_id, 'store_id' => $store_id, 'status'=> 1], 0, 0, ['productInfo', 'attrInfo']);
        [$data, $valid, $invalid] = $partakeService->handleCartList($uid, $data, -1, $store_id);
        return app('json')->successful($valid);
    }

    /**
     * 获取自提门店信息
     * @param Request $request
     * @return \think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getStoredata(Request $request)
    {
        [$store_id, $latitude, $longitude] = $request->getMore([
            ['store_id', 0],
            ['latitude', ''],
            ['longitude', '']
        ], true);
        if (!$store_id || !$latitude || !$longitude) return app('json')->fail('参数有误!');
        /** @var SystemStoreServices $storeService */
        $storeService = app()->make(SystemStoreServices::class);
        $storeInfo = $storeService->getStoreInfo((int)$store_id);
        $distance = $storeService->distance($latitude, $longitude, $storeInfo['latitude'], $storeInfo['longitude']);
        if ($distance) {
            $storeInfo['range'] = bcdiv($distance, '1000', 1);
        } else {
            $storeInfo['range'] = 0;
        }
        return app('json')->successful($storeInfo);
    }

    /**
     * 用户添加拼单商品
     * @param Request $request
     * @return \think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function addCollagePartake(Request $request)
    {
        $where = $request->postMore([
            ['productId', 0],//普通商品编号
            [['cartNum', 'd'], 1], //购物车数量
            ['uniqueId', ''],//属性唯一值
            ['collageId', 0],//拼单ID
            ['storeId', 0],//门店ID
            ['isAdd', 1],//购物车数量加减 1 加 0 减
        ]);
        if (!$where['productId'] || !$where['storeId'] || !$where['collageId']) {
            return app('json')->fail('参数错误');
        }
        $uid = (int)$request->uid();
        $wheredata = ['id' => $where['collageId'], 'type' => 9];
        $collage = $this->services->getUserCollage($wheredata);
        if ($collage['store_id'] != $where['storeId']) return app('json')->fail('选择门店有误！');
        if ($collage['status'] == 1) return app('json')->fail('订单提交中，不能在添加商品！');
        if ($collage['status'] >= 2) return app('json')->fail('拼单完成，不能在添加商品！');
        /** @var UserCollagePartakeServices $partakeService */
        $partakeService = app()->make(UserCollagePartakeServices::class);
        $res = $partakeService->addUserPartakeProduct($uid, (int)$where['productId'], $where['cartNum'], $where['uniqueId'], $where['collageId'], $where['storeId'], 9, $where['isAdd']);
        if ($res) {
            return app('json')->successful('ok');
        } else {
            return app('json')->fail('添加失败');
        }
    }

    /**
     * 获取拼单数据
     * @param Request $request
     * @return \think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getUserCollagePartake(Request $request)
    {
        [$collage_id] = $request->getMore([
            ['collage_id', 0]
        ], true);
        if (!$collage_id) return app('json')->fail('参数有误!');
        $uid = (int)$request->uid();
        /** @var UserCollagePartakeServices $partakeService */
        $partakeService = app()->make(UserCollagePartakeServices::class);
        $cartList = $partakeService->getUserPartakeProduct($collage_id, $uid);
        return app('json')->successful($cartList);
    }

    /**
     * 用户清空拼单数据
     * @param Request $request
     * @return \think\Response
     */
    public function emptyCollagePartake(Request $request)
    {
        [$collage_id] = $request->getMore([
            ['collage_id', 0]
        ], true);
        if (!$collage_id) return app('json')->fail('参数有误!');
        $uid = (int)$request->uid();
        /** @var UserCollagePartakeServices $partakeService */
        $partakeService = app()->make(UserCollagePartakeServices::class);
        $res = $partakeService->emptyUserCollagePartake((int)$collage_id, $uid);
        if ($res) {
            return app('json')->successful('ok');
        } else {
            return app('json')->fail('清空失败');
        }
    }

    /**
     * 复制他人拼单商品
     * @param Request $request
     * @return \think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function duplicateCollagePartake(Request $request)
    {
        [$collage_id, $c_uid] = $request->getMore([
            ['collage_id', 0],
            ['c_uid', 0],
        ], true);
        if (!$collage_id || !$c_uid) return app('json')->fail('参数有误!');
        $uid = (int)$request->uid();
        if ($c_uid == $uid) return app('json')->fail('您不能复制自己的商品!');
        $where = ['id' => $collage_id, 'type' => 9];
        $collage = $this->services->getUserCollage($where);
        if ($collage['status'] >= 2) return app('json')->fail('拼单完成，不能在复制商品！');
        /** @var UserCollagePartakeServices $partakeService */
        $partakeService = app()->make(UserCollagePartakeServices::class);
        $res = $partakeService->duplicateUserCollagePartake((int)$collage_id, (int)$c_uid, $uid);
        if ($res) {
            return app('json')->successful('ok');
        } else {
            return app('json')->fail('复制失败');
        }
    }

    /**
     * 结算拼单
     * @param Request $request
     * @return \think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function userSettleAccountsCollage(Request $request)
    {
        [$collage_id] = $request->getMore([
            ['collage_id', 0],
        ], true);
        if (!$collage_id) return app('json')->fail('参数有误!');
        $uid = (int)$request->uid();
        /** @var UserCollagePartakeServices $partakeService */
        $partakeService = app()->make(UserCollagePartakeServices::class);
        $cartIds = $partakeService->allUserSettleAccountsCollage((int)$collage_id, $uid, 9);
        if ($cartIds) {
            return app('json')->successful('ok', ['cartIds' => $cartIds]);
        } else {
            return app('json')->fail('结算失败');
        }
    }

    /**用户删除拼单商品
     * @param Request $request
     * @return \think\Response
     */
    public function delUserCollagePartake(Request $request)
    {
        $where = $request->postMore([
            ['collage_id', 0],
            ['storeId', 0],
            ['productId', 0],//普通商品编号
            ['uniqueId', ''],//属性唯一值
        ]);
        if (!$where['collage_id'] || !$where['storeId'] || !$where['productId'] || !$where['uniqueId']) return app('json')->fail('参数有误!');
        /** @var UserCollagePartakeServices $partakeService */
        $partakeService = app()->make(UserCollagePartakeServices::class);
        $res = $partakeService->delUserCatePartake((int)$where['collage_id'], (int)$where['storeId'], (int)$where['productId'], $where['uniqueId']);
        if ($res) {
            return app('json')->successful('删除成功');
        } else {
            return app('json')->fail('删除失败');
        }
    }
}
