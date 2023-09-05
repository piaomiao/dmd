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

namespace app\listener\product;


use app\jobs\product\ProductCopyJob;
use app\jobs\product\ProductCouponJob;
use app\jobs\product\ProductRelationJob;
use app\jobs\product\ProductCategoryBrandJob;
use app\jobs\product\ProductStockRecordJob;
use app\jobs\product\ProductStockTips;
use app\jobs\product\ProductSupplierJob;
use app\jobs\product\ProductSyncErp;
use app\jobs\product\ProductSyncStoreJob;
use app\services\store\SystemStoreServices;
use crmeb\interfaces\ListenerInterface;

/**
 * 创建商品成功事件
 * Class CreateSuccess
 * @package app\listener\product
 */
class CreateSuccess implements ListenerInterface
{

    public function handle($event): void
    {
        event('get.config');
        [$id, $data, $skuList, $is_new, $slider_image, $description, $is_copy, $relationData] = $event;
        $id = (int)$id;
        $type = $data['type'] ?? 0;
        $relation_id = $data['relation_id'] ?? 0;
        //检测库存警戒和检测是否售罄
        ProductStockTips::dispatch([$id, 0]);
        //商品分类关联
        if ($cate_id = $relationData['cate_id'] ?? []) ProductRelationJob::dispatch([$id, $cate_id, 1, (int)($data['is_show'] ?? 0)]);
        //商品品牌 为空直接清楚
        $brand_id = $relationData['brand_id'] ?? [];
		ProductRelationJob::dispatch([$id, $brand_id, 2]);
		//商品、分类、品牌三个关联
		ProductCategoryBrandJob::dispatch([$id, $cate_id, $brand_id]);
        //商品标签关联
		ProductRelationJob::dispatch([$id, $relationData['store_label_id'] ?? [], 3]);
        //用户标签关联
		ProductRelationJob::dispatch([$id, $relationData['label_id'] ?? [], 4]);
        //保障服务关联
		ProductRelationJob::dispatch([$id, $relationData['ensure_id'] ?? [], 5]);
        //商品参数关联
		ProductRelationJob::dispatch([$id, $relationData['specs_id'] ?? [], 6]);
        //保存商品关联优惠券
		ProductCouponJob::dispatchDo('setProductCoupon', [$id, $relationData['coupon_ids'] ?? []]);

        //保存库存记录
        ProductStockRecordJob::dispatch($id, $skuList['valueGroup'] ?? []);
        switch ($type) {
            case 0://平台
                //商品同步至门店
                ProductSyncStoreJob::dispatchDo('syncProductToStores', [$id, $data['applicable_type'] ?? 0, $data['applicable_store_id'] ?? []]);
                break;
            case 1://门店
                break;
            case 2://供应商
                //活动商品供应商ID修改
                ProductSupplierJob::dispatchDo('updateSupplierId', [$id, $relation_id]);
                break;
        }


        //采集商品下载图片
        if ($is_copy == -1) {
            //下载商品轮播图
            foreach ($slider_image as $s_image) {
                ProductCopyJob::dispatchDo('copySliderImage', [$id, $s_image, count($slider_image)]);
            }
            preg_match_all('#<img.*?src="([^"]*)"[^>]*>#i', $description, $match);
            foreach ($match[1] as $d_image) {
                ProductCopyJob::dispatchDo('copyDescriptionImage', [$id, $description, $d_image, count($match[1])]);
            }
        }

        //ERP功能开启
        if (sys_config('erp_open') && $data['product_type'] == 0) {
            //上传商品至ERP平台
            ProductSyncErp::dispatchDo('upProductToErp', [$id]);

            //上传商品至ERP默认门店
            ProductSyncErp::dispatchDo('upBranchProductToErp', [$id, ['erp_shop_id' => sys_config('jst_default_shopid')]]);

            //给所有绑定了ERP店铺的门店增加该商品，并完成ERP店铺资料上传
            /** @var SystemStoreServices $systemStoreServices */
            $systemStoreServices = app()->make(SystemStoreServices::class);
            $erpStoreList = $systemStoreServices->getStoreList([['erp_shop_id', '>', 0]]);
            foreach ($erpStoreList['list'] as $item) {
                if ($item['erp_shop_id'] < 1) continue;
                ProductSyncErp::dispatchDo('productToBranch', [$id, $item]);
                ProductSyncErp::dispatchDo('upBranchProductToErp', [$id, $item]);
            }

            if ($is_new) {
                ProductSyncErp::dispatchDo('stockFromErp', [[$id]]);
            }
        }
    }
}
