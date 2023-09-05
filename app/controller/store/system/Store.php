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

namespace app\controller\store\system;


use app\services\other\QrcodeServices;
use app\services\store\SystemStoreStaffServices;
use app\validate\store\system\StoreValidata;
use crmeb\services\DeliverySevices;
use crmeb\utils\Canvas;
use think\facade\App;
use app\controller\store\AuthController;
use app\services\store\SystemStoreServices;

/**
 * 门店
 * Class Store
 * @package app\controller\store\system
 */
class Store extends AuthController
{

    public function __construct(App $app, SystemStoreServices $services)
    {
        parent::__construct($app);
        $this->services = $services;
    }

    /**
     * 获取门店信息
     * @return mixed
     */
    public function info()
    {
        return app('json')->success($this->services->getStoreInfo($this->storeId));
    }

    /**
     * 修改当前登录门店
     * @return mixed
     */
    public function update(SystemStoreStaffServices $services)
    {
        $data = $this->request->postMore([
            ['image', ''],
            ['name', ''],
            ['introduction', ''],
            ['phone', ''],
            ['address', ''],
            ['province', ''],
            ['city', ''],
            ['area', ''],
            ['street', ''],
            ['valid_range', 0],
            ['detailed_address', ''],
            ['is_show', ''],
            ['day_time', []],
            ['latitude', ''],
            ['longitude', ''],
            ['is_store', 0],
            ['customer_type', 1],
            ['home_style', 1],
            ['city_delivery_status', 1],
            ['city_delivery_type', 1],
            ['delivery_goods_type', 1],
            ['business', 0]
        ]);

        validate(StoreValidata::class)->check($data);
        [$data['day_start'], $data['day_end']] = $data['day_time'];
        $data['day_time'] = $data['day_time'] ? implode('-', $data['day_time']) : '';
        $data['valid_range'] = bcmul($data['valid_range'], '1000', 0);
        $phone = $this->services->value(['id' => $this->storeId], 'phone');
        if ($this->services->update($this->storeId, $data)) {
            if ($phone) {
                $services->update(['phone' => $phone], ['phone' => $data['phone']]);
            }
			$storeInfo = $this->services->get((int)$this->storeId);
			$this->services->cacheUpdate($storeInfo->toArray());
            return app('json')->success('修改成功');
        } else {
            return app('json')->fail('修改失败');
        }
    }

    /**
     * 获取门店财务信息
     * @return mixed
     */
    public function getFinanceInfo()
    {
        $storeInfo = $this->services->get((int)$this->storeId);
        if (!$storeInfo) {
            return app('json')->fail('门店不存在');
        }
        return app('json')->success($storeInfo->toArray());
    }

    /**
     * 设置门店财务信息
     * @return mixed
     */
    public function setFinanceInfo()
    {
        $data = $this->request->postMore([
            ['bank_code', ''],
            ['bank_address', ''],
            ['alipay_account', ''],
            ['alipay_qrcode_url', ''],
            ['wechat', ''],
            ['wechat_qrcode_url', '']
        ]);
        $storeInfo = $this->services->get((int)$this->storeId);
        if (!$storeInfo) {
            return app('json')->fail('门店不存在');
        }
        if ($this->services->update($storeInfo['id'], $data)) {
            return app('json')->success('设置成功');
        } else {
            return app('json')->fail('设置失败');
        }
    }

    /**
     * 获取uu、达达配送商品类型
     * @param $type
     * @return mixed
     */
    public function getBusiness($type = 1)
    {
        return app('json')->success(DeliverySevices::init((int)$type)->getBusiness());
    }

    /**获取门店二维码
     * @return mixed
     */
    public function store_qrcode()
    {
        $id = (int)$this->storeId;
        //生成h5地址
        $weixinPage = "/pages/store_cate/store_cate?id=" . $id;
        $weixinFileName = "wechat_store_cate_id_" . $id . ".png";
        /** @var QrcodeServices $QrcodeService */
        $QrcodeService = app()->make(QrcodeServices::class);
        $wechatQrcode = $QrcodeService->getWechatQrcodePath($weixinFileName, $weixinPage, false, false);
        //生成小程序地址
        $routineQrcode = $QrcodeService->getRoutineQrcodePath($id, 0, 10, [], true);
        $data = ['wechat' => $wechatQrcode, 'routine' => $routineQrcode];
        return $this->success($data);
    }
}
