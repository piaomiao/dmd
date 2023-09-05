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

namespace app\jobs\product;


use app\services\product\product\StoreProductServices;
use app\services\product\sku\StoreProductAttrValueServices;
use app\webscoket\SocketPush;
use crmeb\basic\BaseJobs;
use crmeb\traits\QueueTrait;

/**
 * 商品库存警戒提示
 * Class ProductStockTips
 * @package app\jobs\product
 */
class ProductStockTips extends BaseJobs
{
    use QueueTrait;


    public function doJob($productId, $send = 1)
    {
        /** @var StoreProductServices $make */
        $make = app()->make(StoreProductServices::class);
        $product = $make->get(['id' => $productId], ['stock', 'id', 'is_police', 'is_sold']);
        $store_stock = sys_config('store_stock') ?? 0;//库存预警界限
        /** @var StoreProductAttrValueServices $storeValueService */
        $storeValueService = app()->make(StoreProductAttrValueServices::class);
        $count = $storeValueService->getPolice([
            ['type', '=', 0],
            ['stock', '<=', $store_stock],
            ['product_id', '=', $productId]
        ]);
        $product->is_sold = $storeValueService->value(['type' => 0, 'product_id' => $productId], 'stock') === 0 ? 1 : 0;
        if ($store_stock >= $product['stock'] || !$count) {
            $product->is_police = 1;
            if ($send) {
                try {
                    SocketPush::admin()->type('STORE_STOCK')->data(['id' => $productId])->push();
                } catch (\Exception $e) {
                }
            }
        } else {
            $product->is_police = 0;
        }
        $product->save();
        return true;
    }

}
