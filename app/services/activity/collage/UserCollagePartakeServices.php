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

namespace app\services\activity\collage;

use app\services\BaseServices;
use app\dao\activity\collage\UserCollagePartakeDao;
use app\services\product\product\StoreProductServices;
use app\services\product\sku\StoreProductAttrValueServices;
use app\services\product\branch\StoreBranchProductServices;
use app\services\store\SystemStoreServices;
use app\services\order\StoreCartServices;
use app\services\user\level\SystemUserLevelServices;
use app\services\user\member\MemberCardServices;
use app\services\user\UserServices;
use think\exception\ValidateException;

/**
 *
 * Class UserCollagePartakeServices
 * @package app\services\activity\collage
 * @mixin UserCollagePartakeDao
 */
class UserCollagePartakeServices extends BaseServices
{
    /**
     * UserCollagePartakeServices constructor.
     * @param UserCollagePartakeDao $dao
     */
    public function __construct(UserCollagePartakeDao $dao)
    {
        $this->dao = $dao;
    }

    /**用户拼单/桌码商品统计
     * @param array $where
     * @param string $numType
     * @param int $collage_id
     * @param int $store_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getUserPartakeCount(array $where, string $numType = '0', int $collage_id = 0, int $store_id = 0)
    {
        $count = 0;
        $ids = [];
        $cartNums = [];
        $cartList = $this->dao->getUserPartakeList($where, 'id,cart_num,product_id');
        if ($cartList) {
            /** @var StoreProductServices $storeProductServices */
            $storeProductServices = app()->make(StoreProductServices::class);
            $productInfos = $storeProductServices->getColumn([['id', 'in', array_column($cartList, 'product_id')]], 'id,pid,type,relation_id', 'id');
            foreach ($cartList as $cart) {
                $productInfo = $productInfos[$cart['product_id']] ?? [];
                if (!$productInfo) continue;
                if (in_array($productInfo['type'], [0, 2]) || ($productInfo['type'] == 1 && $productInfo['relation_id'] == $store_id) || ($productInfo['type'] == 1 && $productInfo['pid'] > 0)) {
                    $ids[] = $cart['id'];
                    $cartNums[] = $cart['cart_num'];
                }
            }
            if ($numType) {
                $count = count($ids);
            } else {
                $count = array_sum($cartNums);
            }
        }
        return compact('count', 'ids');
    }

    /**获取购物车列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @param array $with
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getPartakeList(array $where, int $page = 0, int $limit = 0, array $with = [])
    {
        return $this->search($where)->when($page && $limit, function ($query) use ($page, $limit) {
            $query->page($page, $limit);
        })->when(count($with), function ($query) use ($with, $where) {
            $query->with($with);
        })->order('add_time DESC')->select()->toArray();
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
    public function handleCartList(int $uid, array $cartList, int $shipping_type = 1, int $store_id = 0)
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
            //用户等级是否开启
            if (sys_config('member_func_status', 1) && $userInfo) {
                $userInfo = $userInfo->toArray();
                /** @var SystemUserLevelServices $systemLevel */
                $systemLevel = app()->make(SystemUserLevelServices::class);
                $discount = $systemLevel->getDiscount($uid, (int)$userInfo['level'] ?? 0);
            }
        }
        if ($store_id) {//平台商品，在门店购买 验证门店库存
            /** @var StoreProductAttrValueServices $skuValueServices */
            $skuValueServices = app()->make(StoreProductAttrValueServices::class);
            /** @var StoreBranchProductServices $branchProductServics */
            $branchProductServics = app()->make(StoreBranchProductServices::class);
            foreach ($cartList as $cart) {
                $productInfo = $cart['productInfo'] ?? [];
                if (!$productInfo) continue;
                if ($productInfo['type'] == 1) {//平台、供应商商品验证门店库存
                    continue;
                }
                $productIds[] = $cart['product_id'];
                $suk = $skuValueServices->value(['unique' => $cart['product_attr_unique'], 'product_id' => $cart['product_id'], 'type' => 0], 'suk');
                $branchProductInfo = $branchProductServics->isValidStoreProduct((int)$cart['product_id'], $store_id);
                if (!$branchProductInfo) {
                    continue;
                }
                $attrValue = $skuValueServices->get(['suk' => $suk, 'product_id' => $branchProductInfo['id'], 'type' => 0]);
                if (!$attrValue) {
                    continue;
                }
                $allStock[$attrValue['unique']] = $attrValue['stock'];
                $attrUniquesArr[$cart['product_attr_unique']] = $attrValue['unique'];
            }
        } else {
            $productIds = array_unique(array_column($cartList, 'product_id'));
        }

        /** @var SystemStoreServices $storeServices */
        $storeServices = app()->make(SystemStoreServices::class);
        $storeInfo = $storeServices->getNearbyStore(['id' => $store_id], '', '', '', 1);
        $valid = $invalid = [];
        foreach ($cartList as &$item) {
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
            //门店独立商品
            $isBranchProduct = isset($productInfo['type']) && isset($productInfo['pid']) && $productInfo['type'] == 1 && !$productInfo['pid'];
            $product_store_id = $isBranchProduct ? $productInfo['relation_id'] : 0;
            if (isset($productInfo['attrInfo']['product_id']) && $item['product_attr_unique']) {
                $item['costPrice'] = $productInfo['attrInfo']['cost'] ?? 0;
                $item['trueStock'] = $item['branch_stock'] = $productInfo['attrInfo']['stock'] ?? 0;
                $item['branch_sales'] = $productInfo['attrInfo']['sales'] ?? 0;
                $item['truePrice'] = $productInfo['attrInfo']['price'] ?? 0;
                $item['sum_price'] = $productInfo['attrInfo']['price'] ?? 0;
                if (!$isBranchProduct) {
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
                if (!$isBranchProduct) {
                    [$truePrice, $vip_truePrice, $type] = $productServices->setLevelPrice($item['productInfo']['price'] ?? 0, $uid, $userInfo, $vipStatus, $discount, $item['productInfo']['vip_price'] ?? 0, $item['productInfo']['is_vip'] ?? 0, true);
                    $item['truePrice'] = $truePrice;
                    $item['vip_truePrice'] = $vip_truePrice;
                    $item['price_type'] = $type;
                }
            }
            $item['is_true_stock'] = $item['trueStock'] >= $item['cart_num'] ? true : false;
            $item['total_price'] = bcmul((string)$item['truePrice'], (string)$item['cart_num'], 2);
            $item['sum_price'] = bcmul((string)$item['sum_price'], (string)$item['cart_num'], 2);
            if (isset($item['status']) && $item['status'] == 0) {
                $item['is_valid'] = 0;
                $item['invalid_desc'] = '此商品已失效';
                $invalid[] = $item;
            } elseif (($item['productInfo']['type'] ?? 0) == 1 && ($item['productInfo']['pid'] ?? 0) == 0 && $storeInfo && ($item['productInfo']['relation_id'] ?? 0) != $storeInfo['id']) {
                $item['is_valid'] = 0;
                $item['invalid_desc'] = '此商品超出配送/自提范围';
                $invalid[] = $item;
            } elseif ((isset($item['productInfo']['delivery_type']) && !$item['productInfo']['delivery_type']) || in_array($item['productInfo']['product_type'], [1, 2, 3])) {
                $item['is_valid'] = 1;
                $valid[] = $item;
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
                        } elseif (in_array($productInfo['type'], [0, 2]) && $store_id && ($condition || (!in_array(2, $item['productInfo']['delivery_type']) && !in_array(3, $item['productInfo']['delivery_type'])))) {//平台商品 在门店购买 验证门店库存
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
                        if (isset($item['productInfo']['delivery_type']) && $item['productInfo']['delivery_type'] && !in_array(2, $item['productInfo']['delivery_type'])) {
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
                        } elseif (in_array($productInfo['type'], [0, 2]) && $store_id && $condition) {//平台、供应商商品 在门店购买 验证门店库存
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

    /**用户添加拼单商品
     * @param int $uid
     * @param int $productId
     * @param int $cart_num
     * @param string $product_attr_unique
     * @param int $collageId
     * @param int $storeId
     * @param int $type
     * @param int $isAdd
     * @return bool|\crmeb\basic\BaseModel|mixed|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function addUserPartakeProduct(int $uid, int $productId, int $cart_num = 1, string $product_attr_unique = '', int $collageId = 0, int $storeId = 0, int $type = 0, int $isAdd = 1)
    {
        if ($cart_num < 1) $cart_num = 1;

        /** @var StoreProductAttrValueServices $attrValueServices */
        $attrValueServices = app()->make(StoreProductAttrValueServices::class);

        if ($product_attr_unique == '') {
            $product_attr_unique = $attrValueServices->value(['product_id' => $productId, 'type' => 0], 'unique');
        }
        //该商品已选择库存
        $sum_cart_num = $this->dao->sum(['collate_code_id' => $collageId, 'product_id' => $productId, 'product_attr_unique' => $product_attr_unique, 'store_id' => $storeId, 'status'=>1],'cart_num');

        //检测库存限量
        /** @var StoreCartServices $cartServices */
        $cartServices = app()->make(StoreCartServices::class);
        [$attrInfo, $product_attr_unique, $bargainPriceMin, $cart_num, $productInfo] = $cartServices->checkProductStock(
            $uid,
            $productId,
            $cart_num,
            $storeId,
            $product_attr_unique,
            false,
            $type,
            0,
            0,
            $sum_cart_num
        );
        $product_type = $productInfo['product_type'];
        $cart = $this->dao->getOne(['uid' => $uid, 'collate_code_id' => $collageId, 'product_id' => $productId, 'product_attr_unique' => $product_attr_unique, 'store_id' => $storeId, 'status'=>1]);
        if ($cart) {
            if ($isAdd) {
                $cart->cart_num = $cart->cart_num + $cart_num;
            } else {
                $cart->cart_num = $cart->cart_num - $cart_num;
            }
            if ($cart->cart_num == 0) {
                $res = $this->dao->delete($cart->id);
            } else {
                $cart->add_time = time();
                $res = $cart->save();
            }
        } else {
            $data = [
                'uid' => $uid,
                'collate_code_id' => $collageId,
                'product_id' => $productId,
                'product_attr_unique' => $product_attr_unique,
                'product_type' => $product_type,
                'store_id' => $storeId,
                'cart_num' => $cart_num,
                'add_time' => time()
            ];
            $res = $this->dao->save($data);
        }
        return $res;
    }

    /**重组数组
     * @param array $array
     * @param int $uid
     * @param int $sponsor_uid
     * @return array
     */
    public function array_val_chunk(array $array, int $uid, int $sponsor_uid, int $status)
    {
        $result = [];
        foreach ($array as $key => &$value) {
            if (!$value['userInfo']) {
                $value['userInfo'] = [
                    'uid' => 0,
                    'nickname' => '该用户已注销'
                ];
            }
            $result [$value ['uid']]['userInfo'] = $value['userInfo'];
            unset($value['userInfo']);
            $result [$value ['uid']]['goods'][] = $value;
        }
        /** @var UserServices $userServices */
        $userServices = app()->make(UserServices::class);
        if (array_key_exists($uid, $result)) {
            $undata = $result[$uid];
        } else {
            $userInfo = $userServices->getUserInfo($uid);
            if (!$userInfo) {
                $userInfo = [
                    'uid' => 0,
                    'nickname' => '该用户已注销'
                ];
            }
            if ($status >= 2) {
                $undata = [];
            } else {
                $undata = [
                    'userInfo' => $userInfo,
                    'goods' => []
                ];
            }
        }
        if (array_key_exists($sponsor_uid, $result)) {
            $sponsordata = $result[$sponsor_uid];
        } else {
            $userInfo = $userServices->getUserInfo($sponsor_uid);
            if (!$userInfo) {
                $userInfo = [
                    'uid' => 0,
                    'nickname' => '该用户已注销'
                ];
            }
            $sponsordata = [
                'userInfo' => $userInfo,
                'goods' => []
            ];
        }
        foreach ($result as $key => $item) {
            if ($key == $uid || $key == $sponsor_uid) {
                unset($result[$key]);
            }
        }
        if ($uid == $sponsor_uid) {
            array_unshift($result, $undata);
        } else {
            if ($undata) {
                array_unshift($result, $undata, $sponsordata);
            } else {
                array_unshift($result, $sponsordata);
            }
        }
        foreach ($result as $key => $item) {
            $truePrices = array_column($item['goods'], 'sum_price');
            $sum = array_sum($truePrices);
            $result[$key]['sum_price'] = $sum;
            $result[$key]['sumPrice'] = number_format($sum, 2);
        }
        return $result;
    }

    /**
     * 获取所有人拼单商品
     * @param int $collage_id
     * @param int $uid
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getUserPartakeProduct(int $collage_id, int $uid)
    {
        /** @var UserCollageServices $collageServices */
        $collageServices = app()->make(UserCollageServices::class);
        $collage = $collageServices->get($collage_id);
        $where = ['collate_code_id' => $collage_id, 'status' => 1];
        [$cartList, $valid, $invalid] = $this->getUserCashierTablePartakeProduct($collage['uid'], $collage['store_id'], $where, '', ['userInfo', 'productInfo', 'attrInfo']);
        $cartList = $this->array_val_chunk($cartList, $uid, $collage['uid'], $collage['status']);
        return $cartList;
    }

    /**
     * 用户清空拼单
     * @param int $collage_id
     * @param int $uid
     * @return bool
     */
    public function emptyUserCollagePartake(int $collage_id, int $uid)
    {
        return $this->dao->del(['collate_code_id' => $collage_id, 'uid' => $uid]);
    }

    /**
     * 复制他人拼单商品
     * @param int $collage_id
     * @param int $c_uid
     * @param int $uid
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function duplicateUserCollagePartake(int $collage_id, int $c_uid, int $uid)
    {
        $data = $this->dao->getUserPartakeList(['uid' => $c_uid, 'collate_code_id' => $collage_id, 'status' => 1], 'product_id,product_type,store_id,product_attr_unique,cart_num');
        foreach ($data as $key => $item) {
            $res = $this->addUserPartakeProduct($uid, (int)$item['product_id'], $item['cart_num'], $item['product_attr_unique'], $collage_id, $item['store_id'], 9, 1);
            if(!$res) continue;
        }
        return true;
    }

    /**拼单商品写入购物车
     * @param int $collage_id
     * @param int $uid
     * @param int $type
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function allUserSettleAccountsCollage(int $collage_id, int $uid, int $type)
    {
        $data = $this->dao->getUserPartakeList(['collate_code_id' => $collage_id, 'status' => 1], 'id,product_id,product_type,store_id,product_attr_unique,cart_num,status,is_settle');
        if (count($data) <= 0) return false;
        /** @var StoreCartServices $cartServices */
        $cartServices = app()->make(StoreCartServices::class);
        $cartIds = [];
        foreach ($data as $key => $item) {
            [$key, $cart_num] = $cartServices->setCart($uid, (int)$item['product_id'], (int)$item['cart_num'], $item['product_attr_unique'], $type, true, $collage_id, 0);
            if ($key && !$item['is_settle']) {
                $this->dao->update($item['id'], ['is_settle' => 1]);
            }
            $cartIds[] = $key;
        }
        if (count($cartIds) < 0) return false;
        /** @var UserCollageServices $collageServices */
        $collageServices = app()->make(UserCollageServices::class);
        $res = $collageServices->userUpdate($collage_id, ['status' => 1]);
        if (!$res) return false;
        $cartIds = array_unique($cartIds);
        $cartIds = implode(',', $cartIds);
        return $cartIds;
    }

    /**根据商品id获取购物车数量
     * @param array $ids
     * @param int $uid
     * @param int $storeId
     * @param int $collate_code_id
     * @return mixed
     */
    public function productIdByCartNum(array $ids, int $uid, int $storeId = 0, int $collate_code_id = 0)
    {
        return $this->search(['product_id' => $ids, 'uid' => $uid, 'store_id' => $storeId, 'collate_code_id' => $collate_code_id])->group('product_attr_unique')->column('cart_num,product_id', 'product_attr_unique');
    }

    /**用户注销信息删除拼单商品
     * @param int $uid
     * @return bool
     */
    public function logOffUserCollagePartake(int $uid)
    {
        return $this->dao->del(['uid' => $uid, 'is_settle' => 0]);
    }

    /**获取桌码商品
     * @param int $tableId
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getUserTablePartakeProduct(int $tableId)
    {
        /** @var UserCollageServices $collageServices */
        $collageServices = app()->make(UserCollageServices::class);
        $table = $collageServices->get($tableId);
        if ($table['status'] == -1) {
            throw new ValidateException('桌码已取消');
        }
        $where = ['collate_code_id' => $tableId, 'status' => 1];
        [$cartList, $valid, $invalid] = $this->getUserCashierTablePartakeProduct($table['uid'], $table['store_id'], $where, '', ['userInfo', 'productInfo', 'attrInfo']);
        $result = [];
        foreach ($cartList as $key => &$value) {
            if (!$value['userInfo']) {
                $value['userInfo'] = [
                    'uid' => 0,
                    'nickname' => '该用户已注销'
                ];
            }
            $result [$value ['uid']]['userInfo'] = $value['userInfo'];
            $result [$value ['uid']]['order_time'] = date('H:i', $value['add_time']);
            unset($value['userInfo']);
            $result [$value ['uid']]['goods'][] = $value;
        }
        return $result;
    }

    /**订单商品信息
     * @param int $uid
     * @param int $store_id
     * @param array $where
     * @param string $field
     * @param array $with
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getUserCashierTablePartakeProduct(int $uid, int $store_id, array $where, string $field = '*', array $with = [])
    {
        $cartList = $this->dao->getUserPartakeProductList($where, $field, $with);
        return $this->handleCartList($uid, $cartList, -1, $store_id);
    }

    /**获取订单商品信息
     * @param int $uid
     * @param int $store_id
     * @param array $where
     * @param string $field
     * @param array $with
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getCashierTablePartakeProduct(array $where, string $field = '*', array $with = [])
    {
        $cartList = $this->dao->getUserPartakeProductList($where, $field, $with);
        if (!$cartList) return [];
        $data = $this->dataPartake($cartList, true);
        return $data;
    }

    /**桌码商品处理
     * @param int $uid
     * @param array $where
     * @param int $store_id
     * @return mixed
     */
    public function getTableCatePartakeList(int $uid, array $where, int $store_id)
    {
        $data = $this->getPartakeList($where, 0, 0, ['productInfo', 'attrInfo']);
        $data = $this->dataPartake($data, false);
        [$data, $valid, $invalid] = $this->handleCartList($uid, $data['cart'], -1, $store_id);
        return $valid;
    }

    /**数据处理
     * @param array $data
     * @param bool $is_price
     * @return array
     */
    public function dataPartake(array $data, bool $is_price)
    {
        $cart = [];
        $sum_price = 0;
        $cart_num = 0;
        foreach ($data as $key => $item) {
            if (isset($item['id'])) {
                $cart[$item['product_id'] . $item['product_attr_unique']]['id'] = $item['id'];
            }
            if (isset($item['uid'])) {
                $cart[$item['product_id'] . $item['product_attr_unique']]['uid'] = $item['uid'];
            }
            if (isset($item['collate_code_id'])) {
                $cart[$item['product_id'] . $item['product_attr_unique']]['collate_code_id'] = $item['collate_code_id'];
            }
            if (isset($item['product_id'])) {
                $cart[$item['product_id'] . $item['product_attr_unique']]['product_id'] = $item['product_id'];
            }
            if (isset($item['product_type'])) {
                $cart[$item['product_id'] . $item['product_attr_unique']]['product_type'] = $item['product_type'];
            }
            if (isset($item['product_attr_unique'])) {
                $cart[$item['product_id'] . $item['product_attr_unique']]['product_attr_unique'] = $item['product_attr_unique'];
            }
            if (isset($item['status'])) {
                $cart[$item['product_id'] . $item['product_attr_unique']]['status'] = $item['status'];
            }
            if (isset($item['is_print'])) {
                $cart[$item['product_id'] . $item['product_attr_unique']]['is_print'] = $item['is_print'];
            }
            if (isset($item['is_settle'])) {
                $cart[$item['product_id'] . $item['product_attr_unique']]['is_settle'] = $item['is_settle'];
            }
            if (isset($item['add_time'])) {
                $cart[$item['product_id'] . $item['product_attr_unique']]['add_time'] = $item['add_time'];
            }
            if (isset($item['productInfo'])) {
                $cart[$item['product_id'] . $item['product_attr_unique']]['productInfo'] = $item['productInfo'];
            }
            if (isset($item['attrInfo']) && isset($item['productInfo'])) {
                $cart[$item['product_id'] . $item['product_attr_unique']]['productInfo']['attrInfo'] = $item['attrInfo'];
                unset($item['attrInfo']);
            }
            if (isset($cart[$item['product_id'] . $item['product_attr_unique']]['cart_num'])) {
                $cart[$item['product_id'] . $item['product_attr_unique']]['cart_num'] += $item['cart_num'];
            } else {
                $cart[$item['product_id'] . $item['product_attr_unique']]['cart_num'] = $item['cart_num'];
            }
            $cart_num += $item['cart_num'];
            if ($is_price && isset($item['productInfo'])) {
                $sum_price = bcadd((string)$sum_price, bcmul((string)$item['cart_num'], (string)$item['productInfo']['price'], 2), 2);
            }
        }
        return compact('cart', 'sum_price', 'cart_num');
    }

    /**桌码商品写入购物车
     * @param int $tableId
     * @param int $uid
     * @param int $type
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function allUserSettleAccountsTableCode(int $tableId, int $uid, int $type)
    {
        $data = $this->dao->getUserPartakeList(['collate_code_id' => $tableId, 'status' => 1], 'id,product_id,product_type,store_id,product_attr_unique,cart_num,status,is_settle');
        if (count($data) <= 0) return false;
        /** @var StoreCartServices $cartServices */
        $cartServices = app()->make(StoreCartServices::class);
        $cartIds = [];
        $data = $this->dataPartake($data, false);
        foreach ($data['cart'] as $key => $item) {
            try {
                [$key, $cart_num] = $cartServices->setCart($uid, (int)$item['product_id'], $item['cart_num'], $item['product_attr_unique'], $type, true, $tableId, 0);
                if ($key && !$item['is_settle']) {
                    $this->dao->update($item['id'], ['is_settle' => 1]);
                }
                $cartIds[] = $key;
            } catch (\Exception $e) {
                continue;
            }
        }
        if (count($cartIds) < 0) return false;
        $cartIds = array_unique($cartIds);
        $cartIds = implode(',', $cartIds);
        return $cartIds;
    }

    /**获取最后一条记录
     * @param array $where
     * @return array|\crmeb\basic\BaseModel|mixed|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getMaxTime(array $where)
    {
        return $this->dao->getUserPartake($where);
    }

    /**获取用户信息
     * @param $where
     * @param $store_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function tableCodeUserAll($where, $store_id)
    {
        $uids = $this->dao->getUserPartakeList(['collate_code_id' => $where['table_id'], 'store_id' => $store_id], 'uid', ['userInfo']);
        foreach ($uids as $key => $item) {
            if (!$item['userInfo']) {
                unset($uids[$key]);
            }
        }
        $uids = array_unique($uids, SORT_REGULAR);
        return array_merge($uids);
    }

    /**获取打印订单的商品信息
     * @param array $table
     * @return array
     */
    public function getCartInfoPrintProduct(array $table)
    {
        $where = ['collate_code_id' => $table['id'], 'is_print' => 0, 'status' => 1];
        $data = $this->getCashierTablePartakeProduct($where, '', ['productInfo', 'attrInfo']);
        $product = [];
        if (!$data || !isset($data['cart'])) return $product;
        foreach ($data['cart'] as $item) {
            $value = $item;
            $value['productInfo']['store_name'] = $value['productInfo']['store_name'] ?? "";
            $value['productInfo']['store_name'] = substrUTf8($value['productInfo']['store_name'], 10, 'UTF-8', '');
            $product[] = $value;
        }
        return $product;
    }

    /**用户清空购物车
     * @param int $table_id
     * @return bool
     */
    public function emptyUserTablePartake(int $table_id)
    {
        return $this->dao->del(['collate_code_id' => $table_id]);
    }

    /**收银台购物车数量操作
     * @param array $where
     * @param int $store_id
     * @return bool|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function editTableCartProduct(array $where, int $store_id)
    {
        if ($where['cartNum'] < 1) {
            $cart_num = 1;
        } else {
            $cart_num = $where['cartNum'];
        }
        $cart = $this->dao->getOne(['collate_code_id' => $where['tableId'], 'product_id' => $where['productId'], 'product_attr_unique' => $where['uniqueId'], 'store_id' => $store_id]);
        if (!$cart) return false;
        if ($where['isAdd']) {
            $cart->cart_num = $cart->cart_num + $cart_num;
        } else {
            $cart->cart_num = $cart->cart_num - $cart_num;
        }
        if ($cart->cart_num == 0) {
            $res = $this->dao->delete($cart->id);
        } else {
            $res = $cart->save();
        }
        return $res;
    }

    /**用户删除拼单、桌码商品
     * @param int $collate_code_id
     * @param int $storeId
     * @param int $productId
     * @param string $uniqueId
     * @return bool
     */
    public function delUserCatePartake(int $collate_code_id, int $storeId, int $productId, string $uniqueId)
    {
        return $this->dao->del(['collate_code_id' => $collate_code_id, 'store_id' => $storeId, 'product_id' => $productId, 'product_attr_unique' => $uniqueId]);
    }
}
