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

namespace app\services\order;

use app\dao\order\StoreCartDao;
use app\services\activity\discounts\StoreDiscountsProductsServices;
use app\services\activity\bargain\StoreBargainServices;
use app\services\activity\combination\StoreCombinationServices;
use app\services\activity\newcomer\StoreNewcomerServices;
use app\services\activity\promotions\StorePromotionsServices;
use app\services\activity\seckill\StoreSeckillServices;
use app\services\BaseServices;
use app\services\other\CityAreaServices;
use app\services\product\branch\StoreBranchProductServices;
use app\services\product\product\StoreProductServices;
use app\services\product\sku\StoreProductAttrValueServices;
use app\services\product\shipping\ShippingTemplatesServices;
use app\services\product\shipping\ShippingTemplatesNoDeliveryServices;
use app\services\store\SystemStoreServices;
use app\services\user\level\SystemUserLevelServices;
use app\services\user\member\MemberCardServices;
use app\services\user\UserAddressServices;
use app\services\user\UserServices;
use app\jobs\product\ProductLogJob;
use crmeb\services\CacheService;
use crmeb\traits\OptionTrait;
use crmeb\traits\ServicesTrait;
use think\exception\ValidateException;

/**
 *
 * Class StoreCartServices
 * @package app\services\order
 * @mixin StoreCartDao
 */
class StoreCartServices extends BaseServices
{

    use OptionTrait, ServicesTrait;

    //库存字段比对
    const STOCK_FIELD = 'sum_stock';
    //购物车最大数量
    protected $maxCartNum = 100;

    /**
     * StoreCartServices constructor.
     * @param StoreCartDao $dao
     */
    public function __construct(StoreCartDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取某个用户下的购物车数量
     * @param array $unique
     * @param int $productId
     * @param int $uid
     * @param string $userKey
     * @return array
     */
    public function getUserCartNums(array $unique, int $productId, int $uid, string $userKey = 'uid')
    {
        $where['is_pay'] = 0;
        $where['is_del'] = 0;
        $where['is_new'] = 0;
        $where['product_id'] = $productId;
        $where[$userKey] = $uid;
        return $this->dao->getUserCartNums($where, $unique);
    }

    /**
     * 计算首单优惠
     * @param int $uid
     * @param array $cartInfo
     * @param array $newcomerArr
     * @return array
     */
    public function computedFirstDiscount(int $uid, array $cartInfo, array $newcomerArr = [])
    {
        $first_order_price = $first_discount = $first_discount_limit = 0;
        if ($uid && $cartInfo) {
            if (!$newcomerArr) {
                /** @var StoreNewcomerServices $newcomerServices */
                $newcomerServices = app()->make(StoreNewcomerServices::class);
                $newcomerArr = $newcomerServices->checkUserFirstDiscount($uid);
            }
            if ($newcomerArr) {//首单优惠
                [$first_discount, $first_discount_limit] = $newcomerArr;
                /** @var StoreOrderComputedServices $orderServices */
                $orderServices = app()->make(StoreOrderComputedServices::class);
                $totalPrice = $orderServices->getOrderSumPrice($cartInfo, 'truePrice');//获取订单svip、用户等级优惠之后总金额
                $first_discount = bcsub('1', (string)bcdiv($first_discount, '100', 2), 2);
                $first_order_price = (float)bcmul((string)$totalPrice, (string)$first_discount, 2);
                $first_order_price = min($first_order_price, $first_discount_limit, $totalPrice);
            }
        }
        return [$cartInfo, $first_order_price, $first_discount, $first_discount_limit];
    }

    /**
     * 获取用户下的购物车列表
     * @param int $uid
     * @param $cartIds
     * @param bool $new
     * @param array $addr
     * @param int $shipping_type
     * @param int $store_id
     * @param int $coupon_id
     * @param bool $isCart
     * @return array
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getUserProductCartListV1(int $uid, $cartIds, bool $new, array $addr = [], int $shipping_type = 1, int $store_id = 0, int $coupon_id = 0, int $luckRecordId = 0, bool $isCart = false)
    {
        if ($new) {
            $cartIds = $cartIds && is_string($cartIds) ? explode(',', $cartIds) : (is_array($cartIds) ? $cartIds : []);
            $cartInfo = [];
            if ($cartIds) {
                foreach ($cartIds as $key) {
                    $info = CacheService::redisHandler()->get($key);
                    if ($info) {
                        $cartInfo[] = $info;
                    }
                }
            }
        } else {
            $cartInfo = $this->dao->getCartList(['uid' => $uid, 'status' => 1, 'id' => $cartIds], 0, 0, ['productInfo', 'attrInfo']);
        }
        if (!$cartInfo) {
            throw new ValidateException('获取购物车信息失败');
        }
        foreach ($cartInfo as $cart) {
            //检查限购
            if (!$luckRecordId) {
                $this->checkLimit($uid, $cart['product_id'] ?? 0, $cart['cart_num'] ?? 1, true, $store_id);
            }
        }

        [$cartInfo, $valid, $invalid] = $this->handleCartList($uid, $cartInfo, $addr, $shipping_type, $store_id);
        $type = array_unique(array_column($cartInfo, 'type'));
        $product_type = array_unique(array_column($cartInfo, 'product_type'));
        $activity_id = array_unique(array_column($cartInfo, 'activity_id'));
        $collate_code_id = array_unique(array_column($cartInfo, 'collate_code_id'));
        $deduction = ['product_type' => $product_type[0] ?? 0, 'type' => $type[0] ?? 0, 'activity_id' => $activity_id[0] ?? 0, 'collate_code_id' => $collate_code_id[0] ?? 0];
        $promotions = $giveCoupon = $giveCartList = $useCoupon = $giveProduct = [];
        $giveIntegral = $couponPrice = $firstOrderPrice = 0;
        if (!$deduction['activity_id'] && !$luckRecordId) {
            /** @var StoreNewcomerServices $newcomerServices */
            $newcomerServices = app()->make(StoreNewcomerServices::class);
            $newcomerArr = $newcomerServices->checkUserFirstDiscount($uid);
            if ($newcomerArr) {//首单优惠
                //计算首单优惠
                [$valid, $firstOrderPrice, $first_discount, $first_discount_limit] = $this->computedFirstDiscount($uid, $valid, $newcomerArr);
            } else {
                /** @var StorePromotionsServices $storePromotionsServices */
                $storePromotionsServices = app()->make(StorePromotionsServices::class);
                //计算相关优惠活动
                [$valid, $couponPrice, $useCoupon, $promotions, $giveIntegral, $giveCoupon, $giveCartList] = $storePromotionsServices->computedPromotions($uid, $valid, $store_id, $coupon_id, $isCart);
                if ($giveCartList) {
                    foreach ($giveCartList as $key => $give) {
                        $giveProduct[] = [
                            'promotions_id' => $give['promotions_id'][0] ?? 0,
                            'product_id' => $give['product_id'] ?? 0,
                            'unique' => $give['product_attr_unique'] ?? '',
                            'cart_num' => $give['cart_num'] ?? 1,
                        ];
                    }
                }
            }
        }
        return compact('cartInfo', 'valid', 'invalid', 'deduction', 'couponPrice', 'useCoupon', 'promotions', 'giveCartList', 'giveIntegral', 'giveCoupon', 'giveProduct', 'firstOrderPrice');
    }

    /**
     * 验证库存
     * @param int $uid
     * @param int $productId
     * @param int $cartNum
     * @param int $store_id
     * @param string $unique
     * @param bool $new
     * @param int $type
     * @param int $activity_id
     * @param int $discount_product_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function checkProductStock(int $uid, int $productId, int $cartNum = 1, int $store_id = 0, string $unique = '', bool $new = false, int $type = 0, int $activity_id = 0, $discount_product_id = 0, $sum_cart_num = 0)
    {
        //验证限量
        $this->checkLimit($uid, $productId, $cartNum, $new, $store_id);
        /** @var StoreProductAttrValueServices $attrValueServices */
        $attrValueServices = app()->make(StoreProductAttrValueServices::class);
        $isSet = $this->getItem('is_set', 0);
        switch ($type) {
            case 0://普通
                if ($unique == '') {
                    $unique = $attrValueServices->value(['product_id' => $productId, 'type' => 0], 'unique');
                }
                /** @var StoreProductServices $productServices */
                $productServices = app()->make(StoreProductServices::class);
                $productInfo = $productServices->isValidProduct($productId);
                if (!$productInfo) {
                    throw new ValidateException('该商品已下架或删除');
                }
                if ($productInfo['is_vip_product']) {
                    /** @var UserServices $userServices */
                    $userServices = app()->make(UserServices::class);
                    $is_vip = $userServices->value(['uid' => $uid], 'is_money_level');
                    if (!$is_vip) {
                        throw new ValidateException('该商品为付费会员专享商品');
                    }
                }
                //预售商品
                if ($productInfo['is_presale_product']) {
                    if ($productInfo['presale_start_time'] > time()) throw new ValidateException('预售活动未开始');
                    if ($productInfo['presale_end_time'] < time()) throw new ValidateException('预售活动已结束');
                }

                $attrInfo = $attrValueServices->getOne(['unique' => $unique, 'type' => 0]);
                if (!$unique || !$attrInfo || $attrInfo['product_id'] != $productId) {
                    throw new ValidateException('请选择有效的商品属性');
                }
                $nowStock = $attrInfo['stock'];//现有平台库存
                if ($cartNum > $nowStock) {
                    throw new ValidateException('该商品库存不足' . $cartNum);
                }
                //直接设置购物车商品数量
                if ($isSet) {
                    $stockNum = 0;
                } else {
                    $stockNum = $this->dao->value(['product_id' => $productId, 'product_attr_unique' => $unique, 'uid' => $uid, 'status' => 1, 'store_id' => $store_id], 'cart_num') ?: 0;
                }
                if ($nowStock < ($cartNum + $stockNum)) {
                    if ($store_id) {
                        throw new ValidateException('该商品库存不足');
                    }
                    $surplusStock = $nowStock - $cartNum;//剩余库存
                    if ($surplusStock < $stockNum) {
                        $this->dao->update(['product_id' => $productId, 'product_attr_unique' => $unique, 'uid' => $uid, 'status' => 1, 'store_id' => $store_id], ['cart_num' => $surplusStock]);
                    }
                }
                break;
            case 1://秒杀
                /** @var StoreSeckillServices $seckillService */
                $seckillService = app()->make(StoreSeckillServices::class);
                [$attrInfo, $unique, $productInfo] = $seckillService->checkSeckillStock($uid, $activity_id, $cartNum, $store_id, $unique);
                break;
            case 2://砍价
                /** @var StoreBargainServices $bargainService */
                $bargainService = app()->make(StoreBargainServices::class);
                [$attrInfo, $unique, $productInfo, $bargainUserInfo] = $bargainService->checkBargainStock($uid, $activity_id, $cartNum, $unique);
                break;
            case 3://拼团
                /** @var StoreCombinationServices $combinationService */
                $combinationService = app()->make(StoreCombinationServices::class);
                [$attrInfo, $unique, $productInfo] = $combinationService->checkCombinationStock($uid, $activity_id, $cartNum, $unique);
                break;
            case 5://套餐
                /** @var StoreDiscountsProductsServices $discountProduct */
                $discountProduct = app()->make(StoreDiscountsProductsServices::class);
                [$attrInfo, $unique, $productInfo] = $discountProduct->checkDiscountsStock($uid, $discount_product_id, $cartNum, $unique);
                break;
            case 7://新人专享
                if ($cartNum > 1) {
                    throw new ValidateException('新人专享商品限购一件');
                }
                /** @var StoreNewcomerServices $newcomerServices */
                $newcomerServices = app()->make(StoreNewcomerServices::class);
                [$attrInfo, $unique, $productInfo] = $newcomerServices->checkNewcomerStock($uid, $activity_id, $cartNum, $unique);
                break;
            case 8://抽奖
                if ($unique == '') {
                    $unique = $attrValueServices->value(['product_id' => $productId, 'type' => 0], 'unique');
                }
                /** @var StoreProductServices $productServices */
                $productServices = app()->make(StoreProductServices::class);
                $productInfo = $productServices->isValidProduct($productId);
                if (!$productInfo) {
                    throw new ValidateException('该商品已下架或删除');
                }
                $attrInfo = $attrValueServices->getOne(['unique' => $unique, 'type' => 0]);
                if (!$unique || !$attrInfo || $attrInfo['product_id'] != $productId) {
                    throw new ValidateException('请选择有效的商品属性');
                }
                $nowStock = $attrInfo['stock'];//现有平台库存
                if ($cartNum > $nowStock) {
                    throw new ValidateException('该商品库存不足' . $cartNum);
                }
                break;
            case 9://拼单
                if ($unique == '') {
                    $unique = $attrValueServices->value(['product_id' => $productId, 'type' => 0], 'unique');
                }
                /** @var StoreProductServices $productServices */
                $productServices = app()->make(StoreProductServices::class);
                $productInfo = $productServices->isValidProduct($productId);
                if (!$productInfo) {
                    throw new ValidateException('该商品已下架或删除');
                }
                $attrInfo = $attrValueServices->getOne(['unique' => $unique, 'type' => 0]);
                if (!$unique || !$attrInfo || $attrInfo['product_id'] != $productId) {
                    throw new ValidateException('请选择有效的商品属性');
                }
                $nowStock = $attrInfo['stock'];//现有平台库存
                if (bcadd((string)$cartNum, (string)$sum_cart_num, 0) > $nowStock) {
                    throw new ValidateException('拼单中该商品库存不足' . bcadd((string)$cartNum, (string)$sum_cart_num, 0));
                }
                break;
            case 10://桌码
                if ($unique == '') {
                    $unique = $attrValueServices->value(['product_id' => $productId, 'type' => 0], 'unique');
                }
                /** @var StoreProductServices $productServices */
                $productServices = app()->make(StoreProductServices::class);
                $productInfo = $productServices->isValidProduct($productId);
                if (!$productInfo) {
                    throw new ValidateException('该商品已下架或删除');
                }
                $attrInfo = $attrValueServices->getOne(['unique' => $unique, 'type' => 0]);
                if (!$unique || !$attrInfo || $attrInfo['product_id'] != $productId) {
                    throw new ValidateException('请选择有效的商品属性');
                }
                $nowStock = $attrInfo['stock'];//现有平台库存
                if (bcadd((string)$cartNum, (string)$sum_cart_num, 0) > $nowStock) {
                    throw new ValidateException('桌码中该商品库存不足' . bcadd((string)$cartNum, (string)$sum_cart_num, 0));
                }
                break;
            default:
                throw new ValidateException('请刷新后重试');
                break;
        }
        if (in_array($type, [1, 2, 3])) {
            //根商品规格库存
            $product_stock = $attrValueServices->value(['product_id' => $productInfo['product_id'], 'suk' => $attrInfo['suk'], 'type' => 0], 'stock');
            if ($product_stock < $cartNum) {
                throw new ValidateException('商品库存不足' . $cartNum);
            }
            if (!CacheService::checkStock($unique, (int)$cartNum, $type)) {
                throw new ValidateException('商品库存不足' . $cartNum . ',无法购买请选择其他商品!');
            }
        }
        return [$attrInfo, $unique, $bargainUserInfo['bargain_price_min'] ?? 0, $cartNum, $productInfo];
    }

    /**
     * 添加购物车
     * @param int $uid
     * @param int $product_id
     * @param int $cart_num
     * @param string $product_attr_unique
     * @param int $type
     * @param bool $new
     * @param int $activity_id
     * @param int $discount_product_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function setCart(int $uid, int $product_id, int $cart_num = 1, string $product_attr_unique = '', int $type = 0, bool $new = true, int $activity_id = 0, int $discount_product_id = 0)
    {
        if ($cart_num < 1) $cart_num = 1;
        //检测库存限量
        $store_id = $this->getItem('store_id', 0);
        $staff_id = $this->getItem('staff_id', 0);
        $tourist_uid = $this->getItem('tourist_uid', '');
        [$attrInfo, $product_attr_unique, $bargainPriceMin, $cart_num, $productInfo] = $this->checkProductStock(
            $uid,
            $product_id,
            $cart_num,
            $store_id,
            $product_attr_unique,
            $new,
            $type, $activity_id,
            $discount_product_id
        );
        $product_type = $productInfo['product_type'];
        if ($new) {
            /** @var StoreOrderCreateServices $storeOrderCreateService */
            $storeOrderCreateService = app()->make(StoreOrderCreateServices::class);
            $key = $storeOrderCreateService->getNewOrderId((string)$uid);
            //普通订单 && 商品是预售商品 订单类型改为预售订单
            if ($type == 0 && $productInfo['is_presale_product']) {
                $type = 6;
            }
            $info['id'] = $key;
            $info['type'] = $type;
            $info['store_id'] = $store_id;
            $info['tourist_uid'] = $tourist_uid;
            $info['product_type'] = $product_type;
            if ($type == 10 || $type == 9) {
                $info['collate_code_id'] = $activity_id;
                $activity_id = 0;
            }
            $info['activity_id'] = $activity_id;
            $info['discount_product_id'] = $discount_product_id;
            $info['product_id'] = $product_id;
            $info['product_attr_unique'] = $product_attr_unique;
            $info['cart_num'] = $cart_num;
            $info['productInfo'] = [];
            if ($productInfo) {
                $info['productInfo'] = is_object($productInfo) ? $productInfo->toArray() : $productInfo;
            }
            $info['attrInfo'] = $attrInfo->toArray();
            $info['productInfo']['attrInfo'] = $info['attrInfo'];
            $info['sum_price'] = $info['productInfo']['attrInfo']['price'] ?? $info['productInfo']['price'] ?? 0;
            //砍价
            if ($type == 2 && $activity_id) {
                $info['truePrice'] = $bargainPriceMin;
                $info['productInfo']['attrInfo']['price'] = $bargainPriceMin;
            } else {
                $info['truePrice'] = $info['productInfo']['attrInfo']['price'] ?? $info['productInfo']['price'] ?? 0;
            }
            //活动商品不参与会员价
            if ($type > 0 && $activity_id) {
                $info['truePrice'] = $info['productInfo']['attrInfo']['price'] ?? 0;
                $info['vip_truePrice'] = 0;
            }
            if ($type == 8) $info['is_luck'] = true;
            $info['trueStock'] = $info['productInfo']['attrInfo']['stock'] ?? 0;
            $info['costPrice'] = $info['productInfo']['attrInfo']['cost'] ?? 0;
            try {
                CacheService::redisHandler()->set($key, $info, 3600);
            } catch (\Throwable $e) {
                throw new ValidateException($e->getMessage());
            }
            return [$key, $cart_num];
        } else {//加入购物车记录
            ProductLogJob::dispatch(['cart', ['uid' => $uid, 'product_id' => $product_id, 'cart_num' => $cart_num]]);
            $cart = $this->dao->getOne(['type' => $type, 'uid' => $uid, 'tourist_uid' => $tourist_uid, 'product_id' => $product_id, 'product_attr_unique' => $product_attr_unique, 'is_del' => 0, 'is_new' => 0, 'is_pay' => 0, 'status' => 1, 'store_id' => $store_id, 'staff_id' => $staff_id]);
            if ($cart) {
                $cart->cart_num = $cart_num + $cart->cart_num;
                $cart->add_time = time();
                $cart->save();
                return [$cart->id, $cart->cart_num];
            } else {
                $add_time = time();
                $id = $this->dao->save(compact('uid', 'tourist_uid', 'store_id', 'staff_id', 'product_id', 'product_type', 'cart_num', 'product_attr_unique', 'type', 'activity_id', 'add_time'))->id;
                event('cart.add', [$uid, $tourist_uid, $store_id, $staff_id]);
                return [$id, $cart_num];
            }

        }
    }

//    /**拼单/桌码商品写入购物车
//     * @param int $uid
//     * @param int $product_id
//     * @param int $cart_num
//     * @param string $product_attr_unique
//     * @param int $type
//     * @param bool $new
//     * @param int $activity_id
//     * @param int $store_id
//     * @param int $discount_product_id
//     * @return mixed
//     * @throws \think\db\exception\DataNotFoundException
//     * @throws \think\db\exception\DbException
//     * @throws \think\db\exception\ModelNotFoundException
//     */
//    public function addCollageCart(int $uid, int $product_id, int $cart_num = 1, string $product_attr_unique = '', int $type = 0, bool $new = true, int $activity_id = 0, int $collate_code_id = 0, int $store_id = 0, int $is_settle = 0)
//    {
//        if ($cart_num < 1) $cart_num = 1;
//        //检测库存限量
//        $staff_id = $this->getItem('staff_id', 0);
//        $tourist_uid = $this->getItem('tourist_uid', '');
//        [$attrInfo, $product_attr_unique, $bargainPriceMin, $cart_num, $productInfo] = $this->checkProductStock(
//            $uid,
//            $product_id,
//            $cart_num,
//            $store_id,
//            $product_attr_unique,
//            $new,
//            $type,
//            $activity_id,
//            0
//        );
//        $product_type = $productInfo['product_type'];
//        ProductLogJob::dispatch(['cart', ['uid' => $uid, 'product_id' => $product_id, 'cart_num' => $cart_num]]);
//        $cart = $this->dao->getOne(['type' => $type, 'uid' => $uid, 'product_id' => $product_id, 'product_attr_unique' => $product_attr_unique, 'activity_id' => $activity_id, 'collate_code_id' => $collate_code_id, 'is_del' => 0, 'is_new' => 0, 'is_pay' => 0, 'status' => 1, 'store_id' => $store_id]);
//        if ($cart) {
//            if (!$is_settle) {
//                $cart->cart_num = $cart_num + $cart->cart_num;
//            }
//            $cart->add_time = time();
//            $cart->save();
//            return $cart->id;
//        } else {
//            $add_time = time();
//            $id = $this->dao->save(compact('uid', 'store_id', 'staff_id', 'product_id', 'product_type', 'cart_num', 'product_attr_unique', 'type', 'activity_id', 'collate_code_id', 'add_time'))->id;
//            event('cart.add', [$uid, $tourist_uid, $store_id, $staff_id]);
//            return $id;
//        }
//    }

    /**
     * 移除购物车商品
     * @param int $uid
     * @param array $ids
     * @return StoreCartDao|bool
     */
    public function removeUserCart(int $uid, array $ids)
    {
        return $this->dao->removeUserCart($uid, $ids) !== false;
    }

    /**
     * 购物车 修改商品数量
     * @param $id
     * @param $number
     * @param $uid
     * @return bool|\crmeb\basic\BaseModel
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function changeUserCartNum($id, $number, $uid)
    {
        if (!$id || !$number) return false;
        $where = ['uid' => $uid, 'id' => $id];
        $carInfo = $this->dao->getOne($where, 'product_id,type,activity_id,product_attr_unique,cart_num');
        /** @var StoreProductServices $StoreProduct */
        $StoreProduct = app()->make(StoreProductServices::class);
        $stock = $StoreProduct->getProductStock($carInfo->product_id, $carInfo->product_attr_unique);
        if (!$stock) throw new ValidateException('暂无库存');
        if (!$number) throw new ValidateException('库存错误');
        if ($stock < $number) throw new ValidateException('库存不足' . $number);
        if ($carInfo->cart_num == $number) return true;
        $this->checkProductStock($uid, (int)$carInfo->product_id, (int)$number, 0, $carInfo->product_attr_unique, true);
        return $this->dao->changeUserCartNum(['uid' => $uid, 'id' => $id], (int)$number);
    }

    /**
     * 修改购物车状态
     * @param int $productId
     * @param int $status 0 商品下架
     */
    public function changeStatus(int $productId, $status = 0)
    {
        $this->dao->update($productId, ['status' => $status], 'product_id');
    }

    /**
     * 获取购物车列表
     * @param int $uid
     * @param int $status
     * @param string $cartIds
     * @param int $storeId
     * @param int $staff_id
     * @param int $shipping_type
     * @param int $numType
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getUserCartList(int $uid, int $status, array $cartIds = [], int $storeId = 0, int $staff_id = 0, int $shipping_type = -1, int $touristUid = 0, int $numType = 0, bool $new = false)
    {
        // [$page, $limit] = $this->getPageValue();
        if ($new) {
            $cartIds = $cartIds && is_string($cartIds) ? explode(',', $cartIds) : (is_array($cartIds) ? $cartIds : []);
            $list = [];
            if ($cartIds) {
                foreach ($cartIds as $key) {
                    $info = CacheService::redisHandler()->get($key);
                    if ($info) {
                        $list[] = $info;
                    }
                }
            }
        } else {
            $where = ['uid' => $uid, 'store_id' => $storeId, 'tourist_uid' => $touristUid, 'cart_ids' => $cartIds];
            //有店员就证明在收银台中
            if ($staff_id) {
                $where['staff_id'] = $staff_id;
            }
            if ($status != -1) $where = array_merge($where, ['status' => $status]);

            $list = $this->dao->getCartList($where, 0, 0, ['productInfo', 'attrInfo']);
        }
        $count = $promotionsPrice = $coupon_price = $firstOrderPrice = 0;
        $cartList = $valid = $promotions = $coupon = $invalid = $type = $activity_id = [];
        if ($list) {
            [$list, $valid, $invalid] = $this->handleCartList($uid, $list, [], $shipping_type, $storeId);
            $activity_id = array_unique(array_column($list, 'activity_id'));
            $type = array_unique(array_column($list, 'type'));

            if (!($activity_id[0] ?? 0)) {
                /** @var StoreNewcomerServices $newcomerServices */
                $newcomerServices = app()->make(StoreNewcomerServices::class);
                $newcomerArr = $newcomerServices->checkUserFirstDiscount($uid);
                if ($newcomerArr) {
                    //计算首单优惠
                    [$valid, $firstOrderPrice, $first_discount, $first_discount_limit] = $this->computedFirstDiscount($uid, $valid, $newcomerArr);
                } else {
                    /** @var StorePromotionsServices $storePromotionsServices */
                    $storePromotionsServices = app()->make(StorePromotionsServices::class);
                    //计算相关优惠活动
                    [$valid, $coupon_price, $coupon, $promotions, $giveIntegral, $giveCoupon, $giveCartList] = $storePromotionsServices->computedPromotions($uid, $valid, $storeId, 0, true);
                    $cartList = array_merge($valid, $giveCartList);
                    foreach ($cartList as $key => $cart) {
                        if (isset($cart['promotions_true_price']) && isset($cart['price_type']) && $cart['price_type'] == 'promotions') {
                            $promotionsPrice = bcadd((string)$promotionsPrice, (string)bcmul((string)$cart['promotions_true_price'], (string)$cart['cart_num'], 2), 2);
                        }
                    }
                }
            }
            if ($numType) {
                $count = count($valid);
            } else {
                $count = array_sum(array_column($valid, 'cart_num'));
            }
        }
        $deduction = ['type' => $type[0] ?? 0, 'activity_id' => $activity_id[0] ?? 0];
        $deduction['promotions_price'] = $promotionsPrice;
        $deduction['coupon_price'] = $coupon_price;
        $deduction['first_order_price'] = $firstOrderPrice;

        $user_store_id = $this->getItem('store_id', 0);
        $invalid_key = 'invalid_' . $user_store_id . '_' . $uid;
        //写入缓存
        if ($status == 1) {
            CacheService::redisHandler()->delete($invalid_key);
            if ($invalid) CacheService::redisHandler()->set($invalid_key, $invalid, 60);
        }
        //读取缓存
        if ($status == 0) {
            $other_invalid = CacheService::redisHandler()->get($invalid_key);
            if ($other_invalid) $invalid = array_merge($invalid, $other_invalid);
        }

        return ['promotions' => $promotions, 'coupon' => $coupon, 'valid' => $valid, 'invalid' => $invalid, 'deduction' => $deduction, 'count' => $count];
    }

    /**
     * 购物车重选
     * @param int $cart_id
     * @param int $product_id
     * @param string $unique
     */
    public function modifyCart(int $cart_id, int $product_id, string $unique)
    {
        /** @var StoreProductAttrValueServices $attrService */
        $attrService = app()->make(StoreProductAttrValueServices::class);
        $stock = $attrService->value(['product_id' => $product_id, 'unique' => $unique, 'type' => 0], 'stock');
        if ($stock > 0) {
            $this->dao->update($cart_id, ['product_attr_unique' => $unique, 'cart_num' => 1]);
        } else {
            throw new ValidateException('选择的规格库存不足');
        }
    }

    /**
     * 重选购物车
     * @param $id
     * @param $uid
     * @param $productId
     * @param $unique
     * @param $num
     * @param int $store_id
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function resetCart($id, $uid, $productId, $unique, $num, int $store_id = 0)
    {
        $res = $this->dao->getOne(['uid' => $uid, 'product_id' => $productId, 'product_attr_unique' => $unique, 'store_id' => $store_id]);
        if ($res) {
            $res->cart_num = $res->cart_num + $num;
            $res->save();
            if ($res['id'] != $id) $this->dao->delete($id);
        } else {
            $this->dao->update($id, ['product_attr_unique' => $unique, 'cart_num' => $num]);
        }
    }

    /**
     * 首页加入购物车
     * @param int $uid
     * @param int $productId
     * @param int $num
     * @param string $unique
     * @param int $type
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function setCartNum(int $uid, int $productId, int $num, string $unique, int $type)
    {
        /** @var StoreProductAttrValueServices $attrValueServices */
        $attrValueServices = app()->make(StoreProductAttrValueServices::class);

        if ($unique == '') {
            $unique = $attrValueServices->value(['product_id' => $productId, 'type' => 0], 'unique');
        }
        /** @var StoreProductServices $productServices */
        $productServices = app()->make(StoreProductServices::class);
        $productInfo = $productServices->isValidProduct((int)$productId);
        if (!$productInfo) {
            throw new ValidateException('该商品已下架或删除');
        }
        if (!($unique && $attrValueServices->getAttrvalueCount($productId, $unique, 0))) {
            throw new ValidateException('请选择有效的商品属性');
        }
        $stock = $productServices->getProductStock((int)$productId, $unique);
        if ($stock < $num) {
            throw new ValidateException('该商品库存不足' . $num);
        }
        //预售商品
        if ($productInfo['is_presale_product']) {
            if ($productInfo['presale_start_time'] > time()) throw new ValidateException('预售活动未开始');
            if ($productInfo['presale_end_time'] < time()) throw new ValidateException('预售活动已结束');
        }
        //检查限购
        if ($type != 0) $this->checkLimit($uid, $productId, $num);

        $cart = $this->dao->getOne(['uid' => $uid, 'product_id' => $productId, 'product_attr_unique' => $unique, 'store_id' => 0]);
        if ($cart) {
            if ($type == -1) {
                $cart->cart_num = $num;
            } elseif ($type == 0) {
                $cart->cart_num = $cart->cart_num - $num;
            } elseif ($type == 1) {
                if ($cart->cart_num >= $stock) {
                    throw new ValidateException('该商品库存只有' . $stock);
                }
                $new_cart_num = $cart->cart_num + $num;
                if ($new_cart_num > $stock) {
                    $new_cart_num = $stock;
                }
                $cart->cart_num = $new_cart_num;
            }
            if ($cart->cart_num === 0) {
                return $this->dao->delete($cart->id);
            } else {
                $cart->add_time = time();
                $cart->save();
                return $cart->id;
            }
        } else {
            $data = [
                'uid' => $uid,
                'product_id' => $productId,
                'product_type' => $productInfo['product_type'],
                'cart_num' => $num,
                'product_attr_unique' => $unique,
                'type' => 0,
                'add_time' => time()
            ];
            $id = $this->dao->save($data)->id;
            event('cart.add', [$uid, 0, 0, 0]);
            return $id;
        }
    }

    /**
     * 用户购物车商品统计
     * @param int $uid
     * @param string $numType
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getUserCartCount(int $uid, string $numType = '0', int $store_id = 0)
    {
        $count = 0;
        $ids = [];
        $cartNums = [];
        $sum_price = 0;
        $cartList = $this->dao->getUserCartList(['uid' => $uid, 'status' => 1, 'store_id' => 0], 'id,cart_num,product_id,product_attr_unique');
        if ($cartList) {
            /** @var StoreProductServices $storeProductServices */
            $storeProductServices = app()->make(StoreProductServices::class);
            $productInfos = $storeProductServices->getColumn([['id', 'in', array_column($cartList, 'product_id')]], 'id,pid,type,relation_id', 'id');
            /** @var StoreProductAttrValueServices $storePrdouctAttrValueServices */
            $storePrdouctAttrValueServices = app()->make(StoreProductAttrValueServices::class);
            $attrInfos = $storePrdouctAttrValueServices->getColumn([['unique', 'in', array_column($cartList, 'product_attr_unique')]], 'id,unique', 'unique');
            foreach ($cartList as $cart) {
                $productInfo = $productInfos[$cart['product_id']] ?? [];
                if (!$productInfo) continue;
                $attrInfo = $attrInfos[$cart['product_attr_unique']] ?? [];
                if (!$attrInfo) continue;
                if ($store_id) {//某门店加入购物车商品数量
                    if (in_array($productInfo['type'], [0, 2]) || ($productInfo['type'] == 1 && $productInfo['relation_id'] == $store_id) || ($productInfo['type'] == 1 && $productInfo['pid'] > 0)) {
                        $ids[] = $cart['id'];
                        $cartNums[] = $cart['cart_num'];
                    }
                } else {
                    if (in_array($productInfo['type'], [0, 2]) || ($productInfo['type'] == 1 && $productInfo['pid'] > 0)) {
                        $ids[] = $cart['id'];
                        $cartNums[] = $cart['cart_num'];
                    }
                }
            }
            if ($numType) {
                $count = count($ids);
            } else {
                $count = array_sum($cartNums);
            }
        }
        return compact('count', 'ids', 'sum_price');
    }

    /**
     * 处理购物车数据
     * @param int $uid
     * @param array $cartList
     * @param array $addr
     * @param int $shipping_type
     * @param int $store_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function handleCartList(int $uid, array $cartList, array $addr = [], int $shipping_type = 1, int $store_id = 0)
    {
        if (!$cartList) {
            return [$cartList, [], [], [], 0, [], []];
        }
        /** @var StoreProductServices $productServices */
        $productServices = app()->make(StoreProductServices::class);
        /** @var MemberCardServices $memberCardService */
        $memberCardService = app()->make(MemberCardServices::class);
        $vipStatus = $memberCardService->isOpenMemberCardCache('vip_price', false);
        $tempIds = [];
        $userInfo = [];
        $discount = 100;
        $productIds = $allStock = $attrUniquesArr = [];
        if ($uid) {
            /** @var UserServices $user */
            $user = app()->make(UserServices::class);
            $userInfo = $user->getUserCacheInfo($uid);
            if (!$userInfo) {
                throw new ValidateException('用户不存在');
            }
            $userInfo = $userInfo->toArray();
            //用户等级是否开启
            if (sys_config('member_func_status', 1) && $userInfo) {
                /** @var SystemUserLevelServices $systemLevel */
                $systemLevel = app()->make(SystemUserLevelServices::class);
                $discount = $systemLevel->getDiscount($uid, (int)$userInfo['level'] ?? 0);
            }
        }
        //不送达运费模板
        if ($shipping_type == 1 && $addr) {
            $cityId = (int)($addr['city_id'] ?? 0);
            if ($cityId) {
                /** @var CityAreaServices $cityAreaServices */
                $cityAreaServices = app()->make(CityAreaServices::class);
                $cityIds = $cityAreaServices->getRelationCityIds($cityId);
                foreach ($cartList as $item) {
                    $tempIds[] = $item['productInfo']['temp_id'];
                }
                $tempIds = array_unique($tempIds);
                /** @var ShippingTemplatesServices $shippingService */
                $shippingService = app()->make(ShippingTemplatesServices::class);
                $tempIds = $shippingService->getColumn([['id', 'in', $tempIds], ['no_delivery', '=', 1]], 'id');
                if ($tempIds) {
                    /** @var ShippingTemplatesNoDeliveryServices $noDeliveryServices */
                    $noDeliveryServices = app()->make(ShippingTemplatesNoDeliveryServices::class);
                    $tempIds = $noDeliveryServices->isNoDelivery($tempIds, $cityIds);
                }
            }
        }
        $latitude = $this->getItem('latitude', '');
        $longitude = $this->getItem('longitude', '');
        $user_store_id = $this->getItem('store_id', 0);
        $store_id = $store_id ?: $user_store_id;
        //平台是否开启门店自提
        $store_self_mention = sys_config('store_func_status', 1) && sys_config('store_self_mention');
        $cart_type = 0;
        if ($store_id) {//平台商品，在门店购买 验证门店库存
            /** @var StoreProductAttrValueServices $skuValueServices */
            $skuValueServices = app()->make(StoreProductAttrValueServices::class);
            /** @var StoreBranchProductServices $branchProductServics */
            $branchProductServics = app()->make(StoreBranchProductServices::class);
            foreach ($cartList as $cart) {
                $productInfo = $cart['productInfo'] ?? [];
                if (!$productInfo) continue;
                $product_id = 0;
                if (in_array($productInfo['type'], [0, 2])) {
                    $product_id = $productInfo['id'];
                } else {//门店商品
                    if ($productInfo['pid'] && $productInfo['relation_id'] != $store_id) {//平台共享商品到另一个门店购买
                        $product_id = $productInfo['pid'];
                    }
                }
                if (!$product_id) {//自己门店购买不用再次验证库存
                    continue;
                }
                $productIds[] = $cart['product_id'];
                $suk = '';
                $cart_type = $cart['type'];
                //类型 0:普通、1：秒杀、2:砍价、3:拼团、4:积分、5:套餐、6:预售、7:新人礼、8:抽奖、9:拼单、10:桌码
                switch ($cart['type']) {
                    case 0:
                    case 6:
                    case 8:
                    case 9:
                    case 10:
                        $suk = $skuValueServices->value(['unique' => $cart['product_attr_unique'], 'product_id' => $cart['product_id'], 'type' => 0], 'suk');
                        break;
                    case 1:
                    case 2:
                    case 3:
                    case 5:
                    case 7:
                        if ($cart['type'] == 5 && isset($cart['discount_product_id'])) {
                            $product_id = $cart['discount_product_id'];
                        } else {
                            $product_id = $cart['activity_id'];
                        }
                        $suk = $skuValueServices->value(['unique' => $cart['product_attr_unique'], 'product_id' => $product_id, 'type' => $cart['type']], 'suk');
                        break;
                }
                $branchProductInfo = $branchProductServics->isValidStoreProduct((int)$cart['product_id'], $store_id);
                if (!$branchProductInfo) {
                    continue;
                }
                $attrValue = '';
                if ($suk) {
                    $attrValue = $skuValueServices->get(['suk' => $suk, 'product_id' => $branchProductInfo['id'], 'type' => 0]);
                }
                if (!$attrValue) {
                    continue;
                }
                $allStock[$attrValue['unique']] = $attrValue['stock'];
                $attrUniquesArr[$cart['product_attr_unique']] = $attrValue['unique'];
            }
        } else {
            $productIds = array_unique(array_column($cartList, 'product_id'));
        }

        $storeInfo = [];
        /** @var SystemStoreServices $storeServices */
        $storeServices = app()->make(SystemStoreServices::class);
        if ($user_store_id) {
            $storeInfo = $storeServices->getNearbyStore(['id' => $user_store_id], '', '', '', 1);
        } else if ($latitude && $longitude) {
            $storeInfo = $storeServices->getNearbyStore([], $latitude, $longitude, '', 1);
        }
        $valid = $invalid = [];
        foreach ($cartList as &$item) {
            $item['is_gift'] = 0;
            if (isset($item['productInfo']['delivery_type'])) {
                $item['productInfo']['delivery_type'] = is_string($item['productInfo']['delivery_type']) ? explode(',', $item['productInfo']['delivery_type']) : $item['productInfo']['delivery_type'];
            } else {
                $item['productInfo']['delivery_type'] = [];
            }
            $item['productInfo']['express_delivery'] = in_array(1, $item['productInfo']['delivery_type']);
            $item['productInfo']['store_mention'] = in_array(2, $item['productInfo']['delivery_type']);
            $item['productInfo']['store_delivery'] = in_array(3, $item['productInfo']['delivery_type']);

            if (isset($item['attrInfo']) && $item['attrInfo'] && (!isset($item['productInfo']['attrInfo']) || !$item['productInfo']['attrInfo'])) {
                $item['productInfo']['attrInfo'] = $item['attrInfo'] ?? [];
            }
            $item['attrStatus'] = isset($item['productInfo']['attrInfo']['stock']) && $item['productInfo']['attrInfo']['stock'];
            $item['productInfo']['attrInfo']['image'] = $item['productInfo']['attrInfo']['image'] ?? $item['productInfo']['image'] ?? '';
            $item['productInfo']['attrInfo']['suk'] = $item['productInfo']['attrInfo']['suk'] ?? '已失效';
            if (isset($item['productInfo']['attrInfo'])) {
                $item['productInfo']['attrInfo'] = get_thumb_water($item['productInfo']['attrInfo']);
            }
            $item['productInfo'] = get_thumb_water($item['productInfo']);
            $productInfo = $item['productInfo'];
            $item['vip_truePrice'] = 0;
            //门店独立商品
            $isBranchProduct = isset($productInfo['type']) && isset($productInfo['pid']) && $productInfo['type'] == 1 && !$productInfo['pid'];
            $product_store_id = $isBranchProduct ? $productInfo['relation_id'] : 0;

            if (isset($productInfo['attrInfo']['product_id']) && $item['product_attr_unique']) {
                $item['costPrice'] = $productInfo['attrInfo']['cost'] ?? 0;
                $item['trueStock'] = $item['branch_stock'] = $productInfo['attrInfo']['stock'] ?? 0;
                $item['branch_sales'] = $productInfo['attrInfo']['sales'] ?? 0;
                $item['truePrice'] = $productInfo['attrInfo']['price'] ?? 0;
                $item['sum_price'] = $productInfo['attrInfo']['price'] ?? 0;
                if ((!$item['type'] || !$item['activity_id']) && !$isBranchProduct) {
                    [$truePrice, $vip_truePrice, $type] = $productServices->setLevelPrice($productInfo['attrInfo']['price'] ?? 0, $uid, $userInfo, $vipStatus, $discount, $productInfo['attrInfo']['vip_price'] ?? 0, $productInfo['is_vip'] ?? 0, true);
                    $item['truePrice'] = $truePrice;
                    $item['vip_truePrice'] = $vip_truePrice;
                    $item['price_type'] = $type;
                }
            } else {
                $item['costPrice'] = $item['productInfo']['cost'] ?? 0;
                $item['trueStock'] = $item['branch_sales'] = $item['productInfo']['stock'] ?? 0;
                $item['branch_sales'] = $item['productInfo']['sales'] ?? 0;
                $item['truePrice'] = $item['productInfo']['price'] ?? 0;
                $item['sum_price'] = $item['productInfo']['price'] ?? 0;
                if ((!$item['type'] || !$item['activity_id']) && !$isBranchProduct) {
                    [$truePrice, $vip_truePrice, $type] = $productServices->setLevelPrice($item['productInfo']['price'] ?? 0, $uid, $userInfo, $vipStatus, $discount, $item['productInfo']['vip_price'] ?? 0, $item['productInfo']['is_vip'] ?? 0, true);
                    $item['truePrice'] = $truePrice;
                    $item['vip_truePrice'] = $vip_truePrice;
                    $item['price_type'] = $type;
                }
            }
            $applicable_type = $item['productInfo']['applicable_type'] ?? 1;
            $applicable_store_id = [];
            if (isset($item['productInfo']['applicable_store_id'])) {
                $applicable_store_id = is_string($item['productInfo']['applicable_store_id']) ? explode(',', $item['productInfo']['applicable_store_id']) : $item['productInfo']['applicable_store_id'];
            }
            $applicableStatus = !$store_id || $applicable_type == 1 || ($applicable_type == 2 && in_array($store_id, $applicable_store_id));
            if (isset($item['status']) && $item['status'] == 0) {
                $item['is_valid'] = 0;
                $item['invalid_desc'] = '此商品已失效';
                $invalid[] = $item;
            } elseif (($item['productInfo']['type'] ?? 0) == 1 && ($item['productInfo']['pid'] ?? 0) == 0 && $storeInfo && ($item['productInfo']['relation_id'] ?? 0) != $storeInfo['id'] && $cart_type != 10) {
                $item['is_valid'] = 0;
                $item['invalid_desc'] = '此商品超出配送/自提范围';
                $invalid[] = $item;
            } elseif ((isset($item['productInfo']['delivery_type']) && !$item['productInfo']['delivery_type']) || in_array($item['productInfo']['product_type'], [1, 2, 3])) {
                $item['is_valid'] = 1;
                $valid[] = $item;
            } elseif (!$applicableStatus) {
                $item['is_valid'] = 0;
                $item['invalid_desc'] = '此商品超出配送/自提范围';
                $invalid[] = $item;
            } else {
                $condition = !in_array(isset($item['productInfo']['product_id']) ? $item['productInfo']['product_id'] : $item['productInfo']['id'], $productIds) || $item['cart_num'] > ($allStock[$attrUniquesArr[$item['product_attr_unique']] ?? ''] ?? 0);
                switch ($shipping_type) {
                    case -1://购物车列表展示
                        if ($isBranchProduct && $store_id && ($store_id != $product_store_id || !in_array(3, $item['productInfo']['delivery_type']))) {
                            $item['is_valid'] = 0;
                            $item['invalid_desc'] = '此商品超出配送/自提范围';
                            $invalid[] = $item;
                        } else {
                            $item['is_valid'] = 1;
                            $valid[] = $item;
                        }
                        break;
                    case 1:
                        //不送达
                        if (in_array($item['productInfo']['temp_id'], $tempIds) || (isset($item['productInfo']['delivery_type']) && !in_array(1, $item['productInfo']['delivery_type']) && !in_array(3, $item['productInfo']['delivery_type']))) {
                            $item['is_valid'] = 0;
                            $item['invalid_desc'] = '此商品超出配送/自提范围';
                            $invalid[] = $item;
                        } elseif ($isBranchProduct && $store_id && ($store_id != $product_store_id || !in_array(3, $item['productInfo']['delivery_type']))) {
                            $item['is_valid'] = 0;
                            $item['invalid_desc'] = '此商品超出配送/自提范围';
                            $invalid[] = $item;
                        } elseif ((in_array($productInfo['type'], [0, 2]) || $productInfo['relation_id'] != $store_id) && $store_id && ($condition || (!in_array(2, $item['productInfo']['delivery_type']) && !in_array(3, $item['productInfo']['delivery_type'])))) {//平台商品 在门店购买 验证门店库存
                            $item['is_valid'] = 0;
                            $item['invalid_desc'] = '此商品超出配送/自提范围';
                            $invalid[] = $item;
                        } elseif (!$storeServices->checkStoreDeliveryScope($store_id, $addr, $latitude, $longitude)) {
                            $item['is_valid'] = 0;
                            $item['invalid_desc'] = '此商品超出配送/自提范围';
                            $invalid[] = $item;
                        } else {
                            $item['is_valid'] = 1;
                            $valid[] = $item;
                        }
                        break;
                    case 2:
                        //不支持到店自提
                        if (!$store_self_mention) {
                            $item['is_valid'] = 0;
                            $item['invalid_desc'] = '平台/门店已关闭自提';
                            $invalid[] = $item;
                        } elseif (isset($item['productInfo']['delivery_type']) && $item['productInfo']['delivery_type'] && !in_array(2, $item['productInfo']['delivery_type'])) {
                            $item['is_valid'] = 0;
                            $item['invalid_desc'] = '此商品超出配送/自提范围';
                            $invalid[] = $item;
                        } elseif ($isBranchProduct && $store_id && $store_id != $product_store_id) {
                            $item['is_valid'] = 0;
                            $item['invalid_desc'] = '此商品超出配送/自提范围';
                            $invalid[] = $item;
                        } elseif ($item['productInfo']['product_type'] == 1) {
                            $item['is_valid'] = 0;
                            $item['invalid_desc'] = '此商品超出配送/自提范围';
                            $invalid[] = $item;
                        } elseif ((in_array($productInfo['type'], [0, 2]) || $productInfo['relation_id'] != $store_id) && $store_id && $condition) {//平台、供应商商品 在门店购买 验证门店库存
                            $item['is_valid'] = 0;
                            $item['invalid_desc'] = '此商品超出配送/自提范围';
                            $invalid[] = $item;
                        } else {
                            $item['is_valid'] = 1;
                            $valid[] = $item;
                        }
                        break;
                    case 4:
                        //无库存｜｜下架
                        if ($isBranchProduct && $store_id && $store_id != $product_store_id) {
                            $item['is_valid'] = 0;
                            $item['invalid_desc'] = '此商品超出配送/自提范围';
                            $invalid[] = $item;
                        } elseif (in_array($productInfo['type'], [0, 2]) && $store_id && $condition) {
                            $item['is_valid'] = 0;
                            $invalid[] = $item;
                        } else {
                            $item['is_valid'] = 1;
                            $valid[] = $item;
                        }
                        break;
                    default:
                        $item['is_valid'] = 1;
                        $valid[] = $item;
                        break;
                }
            }
            unset($item['attrInfo']);
        }


        return [$cartList, $valid, $invalid];
    }


    /**
     * 门店给用户加入购物车
     * @param int $uid
     * @param int $productId
     * @param int $cartNum
     * @param string $unique
     * @param int $staff_id
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function addCashierCart(int $uid, int $productId, int $cartNum, string $unique, int $staff_id = 0)
    {
        $store_id = $this->getItem('store_id', 0);
        $tourist_uid = $this->getItem('tourist_uid', '');
        if (!$store_id) {
            throw new ValidateException('缺少门店ID');
        }

        [$nowStock, $unique, $bargainPriceMin, $cart_num, $productInfo] = $this->checkProductStock($uid, $productId, $cartNum, $store_id, $unique, true);

        ProductLogJob::dispatch(['cart', ['uid' => $uid, 'product_id' => $productId, 'cart_num' => $cartNum]]);
        $cart = $this->dao->getOne([
            'uid' => $uid,
            'product_id' => $productId,
            'product_attr_unique' => $unique,
            'store_id' => $store_id,
            'staff_id' => $staff_id,
            'tourist_uid' => $tourist_uid,
            'is_del' => 0,
            'is_new' => 0,
            'is_pay' => 0,
            'status' => 1
        ]);
        if ($cart) {
            if ($nowStock < ($cartNum + $cart['cart_num'])) {
                $cartNum = $nowStock - $cartNum;//剩余库存
            }
            if ($cartNum == 0) throw new ValidateException('库存不足');
            $cart->cart_num = $cartNum + $cart->cart_num;
            $cart->add_time = time();
            $cart->save();
            return $cart->id;
        } else {
            $add_time = time();
            $data = compact('uid', 'store_id', 'add_time', 'tourist_uid');
            $data['type'] = 0;
            $data['product_id'] = $productId;
            $data['product_type'] = $productInfo['product_type'];
            $data['cart_num'] = $cartNum;
            $data['product_attr_unique'] = $unique;
            $data['store_id'] = $store_id;
            $data['staff_id'] = $staff_id;
            $id = $this->dao->save($data)->id;
            event('cart.add', [$uid, $tourist_uid, $store_id, $staff_id]);
            return $id;
        }
    }

    /**
     * @param int $id
     * @param int $number
     * @param int $uid
     * @param int $storeId
     * @return bool|\crmeb\basic\BaseModel
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function changeCashierCartNum(int $id, int $number, int $uid, int $storeId)
    {
        if (!$id || !$number) return false;
        $where = ['uid' => $uid, 'id' => $id, 'store_id' => $storeId];
        $carInfo = $this->dao->getOne($where, 'product_id,product_attr_unique,cart_num');
        /** @var StoreBranchProductServices $storeProduct */
        $storeProduct = app()->make(StoreBranchProductServices::class);
        $stock = $storeProduct->getProductStock($carInfo->product_id, $storeId, $carInfo->product_attr_unique);
        if (!$stock) throw new ValidateException('暂无库存');
        if ($stock < $number) throw new ValidateException('库存不足' . $number);
        if ($carInfo->cart_num == $number) return true;
        $this->setItem('is_set', 1);
        $this->checkProductStock($uid, (int)$carInfo->product_id, $number, $storeId, $carInfo->product_attr_unique, true);
        $this->reset();
        return $this->dao->changeUserCartNum(['uid' => $uid, 'id' => $id], (int)$number);
    }

    /**
     * 购物车重选
     * @param int $cart_id
     * @param int $product_id
     * @param string $unique
     */
    public function modifyCashierCart(int $storeId, int $cart_id, int $product_id, string $unique)
    {
        /** @var StoreProductAttrValueServices $attrService */
        $attrService = app()->make(StoreProductAttrValueServices::class);
        $stock = $attrService->value(['product_id' => $product_id, 'unique' => $unique, 'type' => 0], 'stock');
        if ($stock > 0) {
            $this->dao->update($cart_id, ['product_attr_unique' => $unique, 'cart_num' => 1]);
        } else {
            throw new ValidateException('选择的规格库存不足');
        }
    }

    /**
     * 批量加入购物车
     * @param array $cart
     * @param int $storeId
     * @param int $uid
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function batchAddCart(array $cart, int $storeId, int $uid)
    {
        $this->setItem('store_id', $storeId);
        $cartIds = [];
        foreach ($cart as $item) {
            if (!isset($item['productId'])) {
                throw new ValidateException('缺少商品ID');
            }
            if (!isset($item['cartNum'])) {
                throw new ValidateException('缺少购买商品数量');
            }
            if (!isset($item['uniqueId'])) {
                throw new ValidateException('缺少唯一值');
            }
            $cartIds[] = $this->addCashierCart($uid, (int)$item['productId'], (int)$item['cartNum'], $item['uniqueId']);
        }
        $this->reset();
        return $cartIds;
    }

    /**
     * 组合前端购物车需要的数据结构
     * @param array $cartList
     * @param array $protmoions
     * @return array
     */
    public function getReturnCartList(array $cartList, array $promotions)
    {
        $result = [];
        if ($cartList) {
            if ($promotions) $promotions = array_combine(array_column($promotions, 'id'), $promotions);
            $i = 0;
            foreach ($cartList as $key => $cart) {
                $data = ['promotions' => [], 'pids' => [], 'cart' => []];
                if ($result && isset($cart['promotions_id']) && $cart['promotions_id'] && (!isset($cart['collate_code_id']) || $cart['collate_code_id'] <= 0)) {
                    $isTure = false;
                    foreach ($result as $key => &$res) {
                        if (array_intersect($res['pids'], $cart['promotions_id'])) {
                            $res['pids'] = array_unique(array_merge($res['pids'], $cart['promotions_id'] ?? []));
                            $res['cart'][] = $cart;
                            $isTure = true;
                            break;
                        }
                    }
                    if (!$isTure) {
                        $data['cart'][] = $cart;
                        $data['pids'] = array_unique($cart['promotions_id'] ?? []);
                        $result[$i] = $data;
                        $i++;
                    }
                } else {
                    $data['cart'][] = $cart;
                    $data['pids'] = array_unique($cart['promotions_id'] ?? []);
                    $result[$i] = $data;
                    $i++;
                }
            }

            foreach ($result as $key => &$item) {
                if ($item['pids']) {
                    foreach ($item['pids'] as $key => $id) {
                        $item['promotions'][] = $promotions[$id] ?? [];
                    }
                }
            }
        }
        return $result;
    }


    /**
     * 控制购物车加入商品最大数量
     * @param int $uid
     * @param int $tourist_uid
     * @param int $store_id
     * @param int $staff_id
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function controlCartNum(int $uid, int $tourist_uid = 0, int $store_id = 0, int $staff_id = 0)
    {
        $maxCartNum = $this->maxCartNum;
        $where = [
            'is_del' => 0,
            'is_new' => 0,
            'is_pay' => 0,
            'status' => 1
        ];
        if ($uid) $where['uid'] = $uid;
        if ($tourist_uid) $where['tourist_uid'] = $tourist_uid;
        if ($store_id) $where['store_id'] = $store_id;
        if ($staff_id) $where['staff_id'] = $staff_id;
        try {
            $count = $this->dao->count($where);
            if ($count >= $maxCartNum) {//删除一个最早加入购物车商品
                $one = $this->dao->search($where)->order('id asc')->find();
                if ($one) {
                    $this->dao->delete($one['id']);
                }
            }
        } catch (\Throwable $e) {
            \think\facade\Log::error('自动控制购物车数量，删除最早加入商品失败：' . $e->getMessage());
        }
        return true;
    }

    /**
     * 检测限购
     * @param int $uid
     * @param int $product_id
     * @param int $num
     * @param bool $new
     * @param int $store_id
     * @return bool
     */
    public function checkLimit(int $uid, int $product_id, int $num, bool $new = false, int $store_id = 0)
    {
        /** @var StoreProductServices $productServices */
        $productServices = app()->make(StoreProductServices::class);
        /** @var StoreOrderCartInfoServices $orderCartServices */
        $orderCartServices = app()->make(StoreOrderCartInfoServices::class);

        $limitInfo = $productServices->get($product_id, ['id', 'pid', 'is_limit', 'limit_type', 'limit_num']);
        if (!$limitInfo) throw new ValidateException('商品不存在');
        $limitInfo = $limitInfo->toArray();
        if (!$limitInfo['is_limit']) return true;
        $cartNum = 0;
        //收银台游客限购
        $tourist_uid = 0;
        if (!$uid) {
            $tourist_uid = $this->getItem('tourist_uid', '');
        }
        $pid = $limitInfo['pid'] ? $limitInfo['pid'] : $limitInfo['id'];
        $product_ids = $productServices->getColumn(['pid' => $pid], 'id');
        $product_ids[] = $pid;

        if ($limitInfo['limit_type'] == 1) {
            if (!$new) {
                $cartNum = $this->dao->sum(['uid' => $uid, 'tourist_uid' => $tourist_uid, 'product_id' => $product_ids, 'store_id' => $store_id, 'status' => 1, 'is_del' => 0], 'cart_num', true);
            }
            if (($num + $cartNum) > $limitInfo['limit_num']) {
                throw new ValidateException('单次购买不能超过 ' . $limitInfo['limit_num'] . ' 件');
            }
        } else if ($limitInfo['limit_type'] == 2) {
            //	购物车商品数量
//            $cartNum = $this->dao->sum($where, 'cart_num');
            $orderPayNum = $orderCartServices->sum(['uid' => $uid, 'product_id' => $product_ids], 'cart_num');
            $orderRefundNum = $orderCartServices->sum(['uid' => $uid, 'product_id' => $product_ids], 'refund_num');
            $orderNum = $orderPayNum - $orderRefundNum;
            if (($num + $orderNum) > $limitInfo['limit_num']) {
                throw new ValidateException('该商品限购 ' . $limitInfo['limit_num'] . ' 件，您已经购买 ' . $orderNum . ' 件');

            }
        }
        return true;
    }

    /**
     * 计算用户购物车商品（优惠活动、最优优惠券）
     * @param array $user
     * @param $cartId
     * @param bool $new
     * @param int $addressId
     * @param int $shipping_type
     * @param int $store_id
     * @return array
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \throwable
     */
    public function computeUserCart(array $user, $cartId, bool $new, int $addressId, int $shipping_type = 1, int $store_id = 0)
    {
        $addr = $data = [];
        $uid = (int)$user['uid'];
        /** @var UserAddressServices $addressServices */
        $addressServices = app()->make(UserAddressServices::class);
        if ($addressId) {
            $addr = $addressServices->getAdderssCache($addressId);
        }
        //没传地址id或地址已删除未找到 ||获取默认地址
        if (!$addr) {
            $addr = $addressServices->getUserDefaultAddressCache($uid);
        }
        $data['upgrade_addr'] = 0;
        if ($addr) {
            $addr = is_object($addr) ? $addr->toArray() : $addr;
            if (isset($addr['upgrade']) && $addr['upgrade'] == 0) {
                $data['upgrade_addr'] = 1;
            }
        } else {
            $addr = [];
        }
        if ($store_id) {
            /** @var SystemStoreServices $storeServices */
            $storeServices = app()->make(SystemStoreServices::class);
            $storeServices->getStoreInfo($store_id);
        }
        //获取购物车信息
        $cartGroup = $this->getUserProductCartListV1($uid, $cartId, $new, $addr, $shipping_type, $store_id, 0, 0, true);
        $storeFreePostage = floatval(sys_config('store_free_postage')) ?: 0;//满额包邮金额
        $valid = $cartGroup['valid'] ?? [];
        /** @var StoreOrderComputedServices $computedServices */
        $computedServices = app()->make(StoreOrderComputedServices::class);
        $priceGroup = $computedServices->getOrderPriceGroup($valid, $addr, $user, $storeFreePostage);


        $invalid = $cartGroup['invalid'] ?? [];
        $deduction = $cartGroup['deduction'] ?? [];
        $coupon = $cartGroup['useCoupon'] ?? [];
        $promotions = $cartGroup['promotions'] ?? [];
        $giveCartList = $cartGroup['giveCartList'] ?? [];
        $couponPrice = $cartGroup['couponPrice'] ?? 0;
        $firstOrderPrice = $cartGroup['firstOrderPrice'];

        $cartList = array_merge($valid, $giveCartList);
        $promotionsPrice = 0;
        if ($cartList) {
            foreach ($cartList as $key => $cart) {
                if (isset($cart['promotions_true_price']) && isset($cart['price_type']) && $cart['price_type'] == 'promotions') {
                    $promotionsPrice = bcadd((string)$promotionsPrice, (string)bcmul((string)$cart['promotions_true_price'], (string)$cart['cart_num'], 2), 2);
                }
            }
        }
        $deduction['promotions_price'] = $promotionsPrice;
        $deduction['coupon_price'] = $couponPrice;
        $deduction['first_order_price'] = $firstOrderPrice;
        $deduction['sum_price'] = $priceGroup['sumPrice'];
        $deduction['vip_price'] = $priceGroup['vipPrice'];

        $payPrice = (float)$priceGroup['totalPrice'];
        if ($couponPrice < $payPrice) {//优惠券金额
            $payPrice = bcsub((string)$payPrice, (string)$couponPrice, 2);
        } else {
            $payPrice = 0;
        }
        if ($firstOrderPrice < $payPrice) {//首单优惠金额
            $payPrice = bcsub((string)$payPrice, (string)$firstOrderPrice, 2);
        } else {
            $payPrice = 0;
        }
        $deduction['pay_price'] = $payPrice;
        return compact('promotions', 'coupon', 'deduction');
    }

}
