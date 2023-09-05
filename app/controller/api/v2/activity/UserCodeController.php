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
use app\services\activity\table\TableQrcodeServices;
use app\services\store\SystemStoreServices;
use app\services\system\config\SystemConfigServices;

/**
 *
 * Class UserCodeController
 * @package app\controller\api\v2\activity
 */
class UserCodeController
{
    protected $services;

    public function __construct(UserCollageServices $services)
    {
        $this->services = $services;
    }

    /**门店桌码配置
     * @param $store_id
     * @return \think\Response
     */
    public function getData($store_id)
    {
        if (!$store_id) return app('json')->fail('参数有误!');
        $configName = ['store_code_switch', 'store_checkout_method', 'store_number_diners_window'];
        /** @var SystemConfigServices $configServices */
        $configServices = app()->make(SystemConfigServices::class);
        $data = $configServices->getConfigAll($configName, (int)$store_id);
        return app('json')->successful($data);
    }

    /**记录桌码
     * @param Request $request
     * @return \think\Response
     */
    public function setTableCode(Request $request)
    {
        [$store_id, $qrcode_id, $number] = $request->getMore([
            ['store_id', 0],
            ['qrcode_id', 0],
            ['number', 1],
        ], true);
        $uid = (int)$request->uid();
        if (!$store_id || !$qrcode_id) return app('json')->fail('参数有误!');
        if (!$this->services->checkTabldeCodeStatus((int)$store_id)) return app('json')->fail('门店或桌码未开启!');
        try {
            if ($number <= 0) $number = 1;
            /** @var TableQrcodeServices $qrcodeService */
            $qrcodeService = app()->make(TableQrcodeServices::class);
            $is_using = $qrcodeService->value(['id' => $qrcode_id], 'is_using');
            if (!$is_using) return app('json')->fail('桌码未启用');
            $res = $this->services->setUserTableCode($uid, $store_id, $qrcode_id, $number);
            $qrcodeService->update($qrcode_id, ['is_use' => 1, 'eat_number' => $number, 'order_time' => time()]);
            return app('json')->successful('ok', ['tableId' => $res->id]);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            \think\facade\Log::error('桌码失败，原因：' . $msg . $e->getFile() . $e->getLine());
            return app('json')->fail('桌码失败');
        }
    }

    /**
     * 检查是否开启桌码记录 是否换桌
     * @param Request $request
     * @return \think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function isUserTableCode(Request $request)
    {
        [$store_id, $qrcode_id] = $request->getMore([
            ['store_id', 0],
            ['qrcode_id', 0],
        ], true);
        $uid = (int)$request->uid();
        if (!$store_id || !$qrcode_id) return app('json')->fail('参数有误!');
        //1=>合并结账 2=>单独结账
        $store_checkout_method = store_config((int)$store_id, 'store_checkout_method', 1);
        $data = $this->services->isUserChangingTables($uid, $store_id, $qrcode_id, $store_checkout_method);
        return app('json')->successful($data);
    }

    /**处理换桌商品
     * @param Request $request
     * @return \think\Response
     */
    public function userChangingTables(Request $request)
    {
        [$tableId, $y_tableId] = $request->getMore([
            ['tableId', 0],
            ['y_tableId', 0],
        ], true);
        if (!$tableId || !$y_tableId) return app('json')->fail('参数有误!');
        $res = $this->services->userChangingTables($tableId, $y_tableId);
        if ($res) {
            return app('json')->successful('ok');
        } else {
            return app('json')->fail('换桌失败');
        }
    }

    /**
     * 获取桌码记录
     * @param Request $request
     * @return \think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getTableCode(Request $request)
    {
        [$tableId] = $request->getMore([
            ['tableId', 0]
        ], true);
        if (!$tableId) return app('json')->fail('参数有误!');
        $where = ['id' => $tableId, 'type' => 10];
        $table = $this->services->getUserCollage($where);
        if (!$table) return app('json')->fail('桌码记录不存在');
        return app('json')->successful(['table' => $table]);
    }

    /**
     * 获取门店信息
     * @param Request $request
     * @return \think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getStoredata(Request $request)
    {
        [$store_id] = $request->getMore([
            ['store_id', 0],
        ], true);
        if (!$store_id) return app('json')->fail('参数有误!');
        /** @var SystemStoreServices $storeService */
        $storeService = app()->make(SystemStoreServices::class);
        $storeInfo = $storeService->getStoreInfo((int)$store_id);
        return app('json')->successful($storeInfo);
    }


    /**获取二维码信息
     * @param Request $request
     * @return \think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getTableCodeData(Request $request)
    {
        [$tableId] = $request->getMore([
            ['tableId', 0]
        ], true);
        if (!$tableId) return app('json')->fail('参数有误!');
        $where = ['id' => $tableId, 'type' => 10];
        $table = $this->services->getUserCollage($where, 'qrcode_id,serial_number,number_diners,status');
        if (!$table) return app('json')->fail('桌码记录不存在');
        if ($table['status'] == -1) return app('json')->fail('桌码已取消');
        $qrcode_id = $table['qrcode_id'];
        /** @var TableQrcodeServices $qrcodeService */
        $qrcodeService = app()->make(TableQrcodeServices::class);
        $Info = $qrcodeService->getQrcodeyInfo((int)$qrcode_id, ['category', 'storeName']);
        $Info['serial_number'] = $table['serial_number'];
        $Info['number_diners'] = $table['number_diners'];
        return app('json')->successful($Info);
    }

    /**
     * 购物车 统计 数量
     * @param Request $request
     * @return mixed
     */
    public function count(Request $request)
    {
        [$numType, $tableId, $store_id] = $request->getMore([
            ['numType', false],//购物车编号
            ['tableId', 0],
            ['store_id', 0]
        ], true);
        $uid = (int)$request->uid();
        if (!$tableId || !$store_id) return app('json')->fail('参数有误!');
        //1=>合并结账 2=>单独结账
        $store_checkout_method = store_config((int)$store_id, 'store_checkout_method', 1);
        $whereUid = ['uid' => $uid];
        $where = ['collate_code_id' => $tableId, 'store_id' => $store_id, 'status'=> 1];
        if ($store_checkout_method == 2) $where = $where + $whereUid;
        /** @var UserCollagePartakeServices $partakeService */
        $partakeService = app()->make(UserCollagePartakeServices::class);
        return app('json')->success('ok', $partakeService->getUserPartakeCount($where, (string)$numType, (int)$tableId, (int)$store_id));
    }

    /**
     * 获取购物车
     * @param Request $request
     * @return mixed
     */
    public function getCartList(Request $request)
    {
        $uid = (int)$request->uid();
        [$tableId, $store_id] = $request->getMore([
            ['tableId', 0],
            ['store_id', 0]
        ], true);
        if (!$tableId || !$store_id) return app('json')->fail('参数有误!');
        //1=>合并结账 2=>单独结账
        $store_checkout_method = store_config((int)$store_id, 'store_checkout_method', 1);
        $whereUid = ['uid' => $uid];
        $where = ['collate_code_id' => $tableId, 'store_id' => $store_id, 'status'=> 1];
        if ($store_checkout_method == 2) $where = $where + $whereUid;
        /** @var UserCollagePartakeServices $partakeService */
        $partakeService = app()->make(UserCollagePartakeServices::class);
        $valid = $partakeService->getTableCatePartakeList($uid, $where, (int)$store_id);
        return app('json')->successful($valid);
    }

    /**
     * 用户添加桌码商品
     * @param Request $request
     * @return \think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function addTableCodePartake(Request $request)
    {
        $where = $request->postMore([
            ['productId', 0],//普通商品编号
            [['cartNum', 'd'], 1], //购物车数量
            ['uniqueId', ''],//属性唯一值
            ['tableId', 0],//桌码ID
            ['storeId', 0],//门店ID
            ['isAdd', 1],//购物车数量加减 1 加 0 减
        ]);
        if (!$where['productId'] || !$where['storeId'] || !$where['tableId']) {
            return app('json')->fail('参数错误');
        }
        $uid = (int)$request->uid();
        $wheredata = ['id' => $where['tableId'], 'type' => 10];
        $table = $this->services->getUserCollage($wheredata);
        if ($table['store_id'] != $where['storeId']) return app('json')->fail('选择门店有误！');
        if ($table['status'] >= 2) return app('json')->fail('结算完成，不能在添加商品！');
        if ($table['status'] == -1) return app('json')->fail('桌码已取消');
        /** @var UserCollagePartakeServices $partakeService */
        $partakeService = app()->make(UserCollagePartakeServices::class);
        $res = $partakeService->addUserPartakeProduct($uid, (int)$where['productId'], $where['cartNum'], $where['uniqueId'], $where['tableId'], $where['storeId'], 10, $where['isAdd']);
        if ($res) {
            return app('json')->successful('ok');
        } else {
            return app('json')->fail('添加失败');
        }
    }

    /**
     * 用户清空购物车
     * @param Request $request
     * @return \think\Response
     */
    public function emptyTablePartake(Request $request)
    {
        [$tableId] = $request->getMore([
            ['tableId', 0]
        ], true);
        if (!$tableId) return app('json')->fail('参数有误!');
        /** @var UserCollagePartakeServices $partakeService */
        $partakeService = app()->make(UserCollagePartakeServices::class);
        $res = $partakeService->emptyUserTablePartake((int)$tableId);
        if ($res) {
            return app('json')->successful('ok');
        } else {
            return app('json')->fail('清空失败');
        }
    }

    /**确认下单
     * @param Request $request
     * @return \think\Response
     */
    public function userPlaceOrder(Request $request)
    {
        [$tableId, $storeId] = $request->getMore([
            ['tableId', 0],
            ['storeId', 0]
        ], true);
        if (!$tableId || !$storeId) return app('json')->fail('参数有误!');
        $table = $this->services->collageStatus($tableId);
        if ($table['status'] == -1) return app('json')->fail('桌码已取消');
        if ($table['status'] >= 2) return app('json')->fail('桌码已结算！');
        $this->services->userTablePlaceOrder((int)$tableId, (int)$storeId);
        return app('json')->successful('ok');
    }

    /**
     * 获取桌码数据
     * @param Request $request
     * @return \think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getUserTableCodePartake(Request $request)
    {
        [$tableId] = $request->getMore([
            ['tableId', 0]
        ], true);
        if (!$tableId) return app('json')->fail('参数有误!');
        /** @var UserCollagePartakeServices $partakeService */
        $partakeService = app()->make(UserCollagePartakeServices::class);
        $cartList = $partakeService->getUserTablePartakeProduct($tableId);
        return app('json')->successful($cartList);
    }

    /**
     * 结算桌码商品
     * @param Request $request
     * @return \think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function userSettleAccountsCollage(Request $request)
    {
        [$tableId] = $request->getMore([
            ['tableId', 0],
        ], true);
        if (!$tableId) return app('json')->fail('参数有误!');
        $table = $this->services->collageStatus($tableId);
        if ($table['status'] == -1) return app('json')->fail('桌码已取消');
        if ($table['status'] >= 2) return app('json')->fail('桌码已完成结算');
        $uid = (int)$request->uid();
        /** @var UserCollagePartakeServices $partakeService */
        $partakeService = app()->make(UserCollagePartakeServices::class);
        $cartIds = $partakeService->allUserSettleAccountsTableCode((int)$tableId, $uid, 10);
        if ($cartIds) {
            return app('json')->successful('ok', ['cartIds' => $cartIds]);
        } else {
            return app('json')->fail('结算失败');
        }
    }

    /**用户删除桌码商品
     * @param Request $request
     * @return \think\Response
     */
    public function delUserTableCodePartake(Request $request)
    {
        $where = $request->postMore([
            ['tableId', 0],
            ['storeId', 0],
            ['productId', 0],//普通商品编号
            ['uniqueId', ''],//属性唯一值
        ]);
        if (!$where['tableId'] || !$where['storeId'] || !$where['productId'] || !$where['uniqueId']) return app('json')->fail('参数有误!');
        /** @var UserCollagePartakeServices $partakeService */
        $partakeService = app()->make(UserCollagePartakeServices::class);
        $res = $partakeService->delUserCatePartake((int)$where['tableId'], (int)$where['storeId'], (int)$where['productId'], $where['uniqueId']);
        if ($res) {
            return app('json')->successful('删除成功');
        } else {
            return app('json')->fail('删除失败');
        }
    }
}
