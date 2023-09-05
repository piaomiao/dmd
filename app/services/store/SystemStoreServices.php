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

use app\dao\store\SystemStoreDao;
use app\services\BaseServices;
use app\services\order\store\BranchOrderServices;
use app\services\order\StoreDeliveryOrderServices;
use app\services\order\StoreOrderCartInfoServices;
use app\services\order\StoreOrderServices;
use app\services\product\branch\StoreBranchProductServices;
use app\services\product\sku\StoreProductAttrValueServices;
use app\services\store\finance\StoreFinanceFlowServices;
use app\services\system\SystemRoleServices;
use crmeb\exceptions\AdminException;
use crmeb\services\erp\Erp;
use crmeb\services\FormBuilder;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Log;

/**
 * 门店
 * Class SystemStoreServices
 * @package app\services\system\store
 * @mixin SystemStoreDao
 */
class SystemStoreServices extends BaseServices
{
    /**
     * 创建form表单
     * @var Form
     */
    protected $builder;

    /**
     * 构造方法
     * SystemStoreServices constructor.
     * @param SystemStoreDao $dao
     * @param FormBuilder $builder
     */
    public function __construct(SystemStoreDao $dao, FormBuilder $builder)
    {
        $this->dao = $dao;
        $this->builder = $builder;
    }

    /**
     * 获取单个门店信息
     * @param int $id
     * @return array|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getStoreInfo(int $id)
    {
        $storeInfo = $this->dao->getOne(['id' => $id, 'is_del' => 0]);
        if (!$storeInfo) {
            throw new ValidateException('获取门店信息失败');
        }
        $storeInfo['day_time'] = $storeInfo['day_time'] ? explode('-', $storeInfo['day_time']) : [];
        return $storeInfo->toArray();
    }

    /**
     * 附近门店
     * @param array $where
     * @param string $latitude
     * @param string $longitude
     * @param string $ip
     * @param int $limit
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getNearbyStore(array $where, string $latitude, string $longitude, string $ip = '', int $limit = 0)
    {
        $where = array_merge($where, ['type' => 0]);
        if ($limit) {
            $page = 1;
        } else {
            [$page, $limit] = $this->getPageValue();
        }
        //默认附近门店
        $store_type = $where['store_type'] ?? 1;
        $uid = $where['uid'] ?? 0;
        unset($where['store_type'], $where['uid']);
        if ($store_type != 1) {//常用门店
            if ($uid) {
                /** @var StoreUserServices $storeUserServices */
                $storeUserServices = app()->make(StoreUserServices::class);
                $ids = $storeUserServices->getColumn(['uid' => $uid], 'store_id');
                if (!$ids) {
                    return [];
                }
                $where['ids'] = $ids;
            } else {//没登录，无常用门店
                return [];
            }
        }
        $storeList = [];
        if (isset($where['id']) && $where['id']) {
            $storeList = $this->dao->getStoreList($where, ['*'], $page, $limit, [], $latitude, $longitude, $latitude && $longitude ? 1 : 0);
        } elseif ($latitude && $longitude) {
            $storeList = $this->dao->getStoreList($where, ['*'], $page, $limit, [], $latitude, $longitude, 1);
        } elseif ($ip) {
            $addressArr = $this->addressHandle($this->convertIp($ip));
            $city = $addressArr['city'] ?? '';
            if ($city) {
                $storeList = $this->dao->getStoreByAddressInfo($city, $where, '*', $page, $limit);
            }
            $province = $addressArr['province'] ?? '';
            if (!$storeList && $province) {
                $storeList = $this->dao->getStoreByAddressInfo($province, $where, '*', $page, $limit);
            }
        }
        //上面条件都没获取到门店
        if (!$storeList) {
            $storeList = $this->dao->getStoreList($where, ['*'], $page, $limit);
        }
        if ($storeList) {
            foreach ($storeList as &$item) {
                $item['range'] = 0;
                if (isset($item['distance'])) {
                    $item['range'] = bcdiv($item['distance'], '1000', 1);
                } else {
                    $item['range'] = 0;
                }
                if (isset($item['is_show']) && $item['is_show'] == 1) {
                    $item['status_name'] = '营业中';
                } else {
                    $item['status_name'] = '已停业';
                }
            }
        }
        return $limit == 1 ? ($storeList[0] ?? []) : $storeList;
    }

	/**
	 * 获取门店
	 * @param array $where
	 * @param array $field
	 * @param string $latitude
	 * @param string $longitude
	 * @param int $product_id
	 * @param array $with
	 * @param int $type 0:普通1：秒杀
	 * @return array
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\DbException
	 * @throws \think\db\exception\ModelNotFoundException
	 */
    public function getStoreList(array $where, array $field = ['*'], string $latitude = '', string $longitude = '', int $product_id = 0, array $with = [], int $type = 0)
    {
        [$page, $limit] = $this->getPageValue();
        $order = 0;
        if (isset($where['order_id']) && $where['order_id']) {
            /** @var StoreOrderServices $storeOrderServices */
            $storeOrderServices = app()->make(StoreOrderServices::class);
            $user_location = $storeOrderServices->value(['id' => $where['order_id']], 'user_location');
            [$longitude, $latitude] = explode(' ', $user_location);
        }
        if ($longitude && $latitude) {
            $order = 1;
        }
        //该商品上架的门店
        if ($product_id) {
            /** @var StoreBranchProductServices $productServices */
            $productServices = app()->make(StoreBranchProductServices::class);
			$ids = $productServices->getApplicableStoreIds($product_id, $type);
            if ($ids) $where['ids'] = $ids;
        }
        $oid = (int)($where['order_id'] ?? 0);
        unset($where['order_id']);
        $storeList = $this->dao->getStoreList($where, $field, $page, $limit, $with, $latitude, $longitude, $order);
        $storeIds = [];
        if ($oid) {
            [$storeIds, $cartInfo] = $this->checkOrderProductShare($oid, $storeList, 2);
        }
        $list = [];
        $prefix = config('admin.store_prefix');
        foreach ($storeList as &$item) {
            if (isset($item['distance'])) {
                $item['range'] = bcdiv($item['distance'], '1000', 1);
            } else {
                $item['range'] = 0;
            }
            if ($item['is_show'] == 1) {
                $item['status_name'] = '营业中';
            } else {
                $item['status_name'] = '已停业';
            }
            $item['prefix'] = $prefix;
            if ($oid) {
                if (in_array($item['id'], $storeIds)) {
                    $list[] = $item;
                }
            } else {
                $list[] = $item;
            }
        }
        if ($oid) {
            $count = count($list);
        } else {
            $count = $this->dao->count($where);
        }
        return compact('list', 'count');
    }

    /**
     * 获取提货点头部统计信息
     * @return mixed
     */
    public function getStoreData()
    {
        $data['show'] = [
            'name' => '显示中的提货点',
            'num' => $this->dao->count(['type' => 0]),
        ];
        $data['hide'] = [
            'name' => '隐藏中的提货点',
            'num' => $this->dao->count(['type' => 1]),
        ];
        $data['recycle'] = [
            'name' => '回收站的提货点',
            'num' => $this->dao->count(['type' => 2])
        ];
        return $data;
    }

    /**
     * 门店重置账号密码表单
     * @param int $id
     * @return array
     * @throws \FormBuilder\Exception\FormBuilderException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function storeAdminAccountForm(int $id)
    {
        $storeInfo = $this->getStoreInfo($id);
        /** @var SystemStoreStaffServices $staffServices */
        $staffServices = app()->make(SystemStoreStaffServices::class);
        $staffInfo = $staffServices->getOne(['store_id' => $storeInfo['id'], 'level' => 0, 'is_admin' => 1, 'is_manager' => 1, 'is_del' => 0]);
        $field[] = $this->builder->hidden('staff_id', $staffInfo['id'] ?? 0);
        $field[] = $this->builder->input('account', '登录账号', $staffInfo['account'] ?? '')->col(24)->required('请输入账号');
        $field[] = $this->builder->input('password', '登录密码')->type('password')->col(24)->required('请输入密码');
        $field[] = $this->builder->input('true_password', '确认密码')->type('password')->col(24)->required('请再次确认密码');
        return create_form('门店重置账号密码', $field, $this->url('/store/store/reset_admin/' . $id));
    }

    /**
     * 获取erp门店列表
     * @return array|mixed
     * @throws \Exception
     */
    public function erpShopList()
    {
        [$page, $limit] = $this->getPageValue();
        if (!sys_config('erp_open')) {
            return [];
        }
        try {
            /** @var Erp $erpService */
            $erpService = app()->make(Erp::class);
            $res = Cache::tag('erp_shop')->remember('list_' . $page . '_' . $limit, function () use ($page, $limit, $erpService) {
                return $erpService->serviceDriver('Comment')->getShopList($page, $limit);
            }, 60);
        } catch (\Throwable $e) {
            Log::error([
                'message' => '读取ERP门店信息失败',
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }

        return $res['data']['datas'] ?? [];
    }

    /**
     * 保存或修改门店
     * @param int $id
     * @param array $data
     * @param array $staff_data
     * @return mixed
     */
    public function saveStore(int $id, array $data, array $staff_data = [])
    {
        return $this->transaction(function () use ($id, $data, $staff_data) {
            if ($id) {
                $is_new = 0;
                if ($this->dao->update($id, $data)) {
                    return [$id, $is_new];
                } else {
                    throw new AdminException('修改失败或者您没有修改什么！');
                }
            } else {
                $is_new = 1;
                $data['add_time'] = time();
                if ($res = $this->dao->save($data)) {
                    if ($staff_data) {
                        $staffServices = app()->make(SystemStoreStaffServices::class);
                        if ($staffServices->count(['phone' => $staff_data['phone'], 'is_del' => 0])) {
                            throw new AdminException('该手机号已经存在');
                        }
                        if ($staffServices->count(['account' => $staff_data['account'], 'is_del' => 0])) {
                            throw new AdminException('管理员账号已存在');
                        }
                        $staff_data['level'] = 0;
                        $staff_data['store_id'] = $res->id;
                        $staff_data['is_admin'] = 1;
                        $staff_data['is_store'] = 1;
                        $staff_data['verify_status'] = 1;
                        $staff_data['is_manager'] = 1;
                        $staff_data['is_cashier'] = 1;
                        $staff_data['add_time'] = time();
                        $staff_data['pwd'] = $this->passwordHash($staff_data['pwd']);
                        if (!$staffServices->save($staff_data)) {
                            throw new AdminException('创建门店管理员失败！');
                        }
                        $data = [
                            ['role_name' => '店员', 'type' => 2, 'store_id' => $res->id, 'status' => 1, 'level' => 1, 'rules' => '1048,1049,1097,1098,1099,1100,1050,1051,1101,1102,1103,1273,1274,1275,1276,1081,1104,1105,1106,1052,1054,1086,1129,1132,1133,1134,1135,1136,1137,1138,1139,1140,1141,1142,1143,1144,1145,1146,1147,1148,1149,1150,1151,1152,1153,1154,1155,1156,1157,1158,1159,1160,1161,1162,1163,1130,1166,1167,1168,1169,1131,1170,1171,1172,1173,1174,1175,1176,1242,1088,1122,1123,1124,1125,1126,1127,1164,1165,1053,1107,1108,1109,1110,1111,1112,1113,1114,1115,1116,1117,1118,1119,1120,1280'],
                            ['role_name' => '管理员', 'type' => 2, 'store_id' => $res->id, 'status' => 1, 'level' => 1, 'rules' => '1048,1049,1097,1098,1099,1100,1050,1051,1101,1102,1103,1273,1274,1275,1276,1081,1104,1105,1106,1052,1054,1086,1129,1132,1133,1134,1135,1136,1137,1138,1139,1140,1141,1142,1143,1144,1145,1146,1147,1148,1149,1150,1151,1152,1153,1154,1155,1156,1157,1158,1159,1160,1161,1162,1163,1130,1166,1167,1168,1169,1131,1170,1171,1172,1173,1174,1175,1176,1242,1088,1122,1123,1124,1125,1126,1127,1164,1165,1053,1107,1108,1109,1110,1111,1112,1113,1114,1115,1116,1117,1118,1119,1120,1280,1055,1056,1177,1178,1179,1180,1181,1182,1183,1184,1185,1186,1187,1188,1189,1190,1191,1192,1277,1057,1193,1194,1195,1196,1197,1249,1250,1251,1252,1253,1254,1058,1059,1060,1198,1199,1200,1243,1255,1256,1257,1258,1259,1260,1061,1201,1241,1062,1063,1215,1218,1219,1220,1244,1261,1262,1263,1264,1265,1064,1216,1217,1202,1065,1066,1203,1214,1067,1204,1212,1213,1235,1068,1205,1206,1069,1207,1208,1070,1089,1071,1209,1210,1211,1072,1073,1082,1083,1084,1085,1228,1229,1230,1231,1232,1233,1234,1236,1245,1246,1247,1248,1221,1222,1223,1224,1225,1226,1227,1266,1267,1268,1269,1270,1271,1272,1237,1238,1239,1240']
                        ];
                        /** @var SystemRoleServices $systemRoleServices */
                        $systemRoleServices = app()->make(SystemRoleServices::class);
                        $systemRoleServices->saveAll($data);
                    }
                    return [(int)$res->id, $is_new];
                } else {
                    throw new AdminException('保存失败！');
                }
            }
        });
    }

    /**
     * 获取门店缓存
     * @param int $id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2022/11/21
     */
    public function getStoreDisposeCache(int $id, string $felid = '')
    {
        $storeInfo = $this->dao->cacheRemember($id, function () use ($id) {
            $storeInfo = $this->dao->get($id);
            return $storeInfo ? $storeInfo->toArray() : null;
        });

        if ($felid) {
            return $storeInfo[$felid] ?? null;
        }

        if ($storeInfo) {
            $storeInfo['latlng'] = $storeInfo['latitude'] . ',' . $storeInfo['longitude'];
            $storeInfo['dataVal'] = $storeInfo['valid_time'] ? explode(' - ', $storeInfo['valid_time']) : [];
            $storeInfo['timeVal'] = $storeInfo['day_time'] ? explode(' - ', $storeInfo['day_time']) : [];
            $storeInfo['address2'] = $storeInfo['address'] ? explode(',', $storeInfo['address']) : [];
            return $storeInfo;
        }
        return [];
    }

    /**
     * 后台获取提货点详情
     * @param int $id
     * @param string $felid
     * @return array|false|mixed|string|string[]|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getStoreDispose(int $id, string $felid = '')
    {
        if ($felid) {
            return $this->dao->value(['id' => $id], $felid);
        } else {
            $storeInfo = $this->dao->get($id);
            if ($storeInfo) {
                $storeInfo['latlng'] = $storeInfo['latitude'] . ',' . $storeInfo['longitude'];
                $storeInfo['dataVal'] = $storeInfo['valid_time'] ? explode(' - ', $storeInfo['valid_time']) : [];
                $storeInfo['timeVal'] = $storeInfo['day_time'] ? explode(' - ', $storeInfo['day_time']) : [];
                $storeInfo['address2'] = $storeInfo['address'] ? explode(',', $storeInfo['address']) : [];
                return $storeInfo;
            }
            return false;
        }
    }

    /**
     * 获取门店不分页
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getStore()
    {
        return $this->dao->getStore(['type' => 0]);
    }

    /**
     * 获得导出店员列表
     * @param array $where
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getExportData(array $where)
    {
        return $this->dao->getStoreList($where, ['*']);
    }

    /**
     * 平台门店运营统计
     * @param array $time
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function storeChart(array $time)
    {
        $list = $this->dao->getStoreList(['is_del' => 0, 'is_show' => 1], ['id', 'name', 'image']);
        /** @var StoreUserServices $storeUserServices */
        $storeUserServices = app()->make(StoreUserServices::class);
        /** @var BranchOrderServices $orderServices */
        $orderServices = app()->make(BranchOrderServices::class);
        /** @var StoreFinanceFlowServices $storeFinancFlowServices */
        $storeFinancFlowServices = app()->make(StoreFinanceFlowServices::class);
        $where = ['time' => $time];
        $order_where = ['paid' => 1, 'pid' => 0, 'is_del' => 0, 'is_system_del' => 0, 'refund_status' => [0, 3]];
        foreach ($list as &$item) {
            $store_where = ['store_id' => $item['id']];
            $item['store_price'] = (float)bcsub((string)$storeFinancFlowServices->sum($where + $store_where + ['trade_type' => 1, 'no_type' => 1, 'pm' => 1, 'is_del' => 0], 'number', true), (string)$storeFinancFlowServices->sum($where + $store_where + ['trade_type' => 1, 'no_type' => 1, 'pm' => 0, 'is_del' => 0], 'number', true), 2);
            $item['store_product_count'] = $orderServices->sum($where + $store_where + $order_where, 'total_num', true);
            $item['store_order_price'] = $orderServices->sum($where + $store_where + $order_where, 'pay_price', true);
            $item['store_user_count'] = $storeUserServices->count($where + $store_where);
        }
        return $list;
    }

    /**
     * 检测订单商品门店是否支持配送
     * @param int $oid
     * @param array $storeList
     * @param int $getType
     * @return array
     */
    public function checkOrderProductShare(int $oid, array $storeList, int $getType = 1)
    {
        if (!$oid || !$storeList) {
            return [[], []];
        }
        /** @var StoreOrderServices $storeOrderServices */
        $storeOrderServices = app()->make(StoreOrderServices::class);
        $orderInfo = $storeOrderServices->get($oid, ['id', 'order_id', 'uid', 'store_id', 'supplier_id']);
        /** @var StoreOrderCartInfoServices $storeOrderCartInfoServices */
        $storeOrderCartInfoServices = app()->make(StoreOrderCartInfoServices::class);
        $cart_info = $storeOrderCartInfoServices->getSplitCartList($oid, 'cart_info');
        if (!$cart_info) {
            return [[], []];
        }

        /** @var StoreProductAttrValueServices $skuValueServices */
        $skuValueServices = app()->make(StoreProductAttrValueServices::class);
        $platProductIds = [];
        $platStoreProductIds = [];
        $storeProductIds = [];
        foreach ($cart_info as $cart) {
            $productInfo = $cart['productInfo'] ?? [];
            if (isset($productInfo['store_delivery']) && !$productInfo['store_delivery']) {//有商品不支持门店配送
                return [[], $cart_info];
            }
            switch ($productInfo['type'] ?? 0) {
                case 0://平台
                case 2://供应商
                    $platProductIds[] = $cart['product_id'];
                    break;
                case 1://门店
                    if ($productInfo['pid']) {//门店自有商品
                        $storeProductIds[] = $cart['product_id'];
                    } else {
                        $platStoreProductIds[] = $cart['product_id'];
                    }
                    break;

            }
        }
        /** @var StoreBranchProductServices $branchProductServics */
        $branchProductServics = app()->make(StoreBranchProductServices::class);
        //转换成平台商品
        if ($platStoreProductIds) {
            $ids = $branchProductServics->getStoreProductIds($platStoreProductIds);
            $platProductIds = array_merge($platProductIds, $ids);
        }
        $productCount = count($platProductIds);
        $result = [];
        foreach ($storeList as $store) {
            if ($storeProductIds && $store['id'] != $orderInfo['store_id']) {
                continue;
            }
            $is_show = $productCount == $branchProductServics->count(['pid' => $platProductIds, 'is_show' => 1, 'is_del' => 0, 'type' => 1, 'relation_id' => $store['id']]);//商品没下架 && 库存足够
            if (!$is_show) {
                continue;
            }
            $stock = true;
            foreach ($cart_info as $cart) {
				$productInfo = $cart['productInfo'] ?? [];
				if (!$productInfo) {
					$stock = false;
					break;
				}
				$applicable_store_ids = [];
				//验证商品适用门店
				if (isset($productInfo['applicable_store_id'])) $applicable_store_ids = is_array($productInfo['applicable_store_id']) ? $productInfo['applicable_store_id'] : explode(',', $productInfo['applicable_store_id']);
				if (!isset($productInfo['applicable_type']) || $productInfo['applicable_type'] == 0 || ($productInfo['applicable_type'] == 2 && !in_array($store['id'], $applicable_store_ids))) {
					$stock = false;
					break;
				}
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
                        $suk = $skuValueServices->value(['unique' => $cart['product_attr_unique'], 'product_id' => $cart['activity_id'], 'type' => $cart['type']], 'suk');
                        break;
                }
                $branchProductInfo = $branchProductServics->isValidStoreProduct((int)$cart['product_id'], $store['id']);
                if (!$branchProductInfo) {
                    $stock = false;
                    break;
                }
                $attrValue = $skuValueServices->get(['suk' => $suk, 'product_id' => $branchProductInfo['id'], 'type' => 0]);
                if (!$attrValue) {
                    $stock = false;
                    break;
                }
                if ($cart['cart_num'] > $attrValue['stock']) {
                    $stock = false;
                    break;
                }
            }
            if ($stock) {
                if ($getType == 1) {//只取一条门店数据
                    $result[] = $store['id'];
                    break;
                } else {
                    $result[] = $store['id'];
                }
            }
        }
        return [$result, $cart_info];
    }

    /**
     * 计算距离
     * @param string $user_lat
     * @param string $user_lng
     * @param string $store_lat
     * @param $store_lng
     * @return string
     */
    public function distance(string $user_lat, string $user_lng, string $store_lat, $store_lng)
    {
        try {
            return (round(6378137 * 2 * asin(sqrt(pow(sin((($store_lat * pi()) / 180 - ($user_lat * pi()) / 180) / 2), 2) + cos(($user_lat * pi()) / 180) * cos(($store_lat * pi()) / 180) * pow(sin((($store_lng * pi()) / 180 - ($user_lng * pi()) / 180) / 2), 2)))));
        } catch (\Throwable $e) {
            return '0';
        }
    }

    /**
     * 验证门店配送范围
     * @param int $store_id
     * @param array $addressInfo
     * @param string $latitude
     * @param string $longitude
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function checkStoreDeliveryScope(int $store_id, array $addressInfo = [], string $latitude = '', string $longitude = '')
    {
        //没登录 ｜｜ 无添加地址 默认不验证
        if (!$store_id || (!$addressInfo && (!$latitude || !$longitude))) {
            return true;
        }
        //门店是否开启
        if (!sys_config('store_func_status', 1)) {
            return false;
        }
        $store_delivery_scope = (int)sys_config('store_delivery_scope', 0);
        if (!$store_delivery_scope) {//配送范围不验证
            return true;
        }
        $storeInfo = $this->getStoreDisposeCache($store_id);
        if (!$storeInfo) {
            return false;
        }
        if ($addressInfo) {
            $user_lat = $addressInfo['latitude'] ?? '';
            $user_lng = $addressInfo['longitude'] ?? '';
            if (!$user_lat || !$user_lng) {
                try {
                    $user_address = $addressInfo['province'] . $addressInfo['city'] . $addressInfo['district'] . $addressInfo['street'] . $addressInfo['detail'];
					/** @var StoreDeliveryOrderServices $deliveryServices */
					$deliveryServices = app()->make(StoreDeliveryOrderServices::class);
                    $addres = $deliveryServices->lbs_address($addressInfo['city'], $user_address);
                    $user_lat = $addres['location']['lat'] ?? '';
                    $user_lng = $addres['location']['lng'] ?? '';
                } catch (\Exception $e) {
					return false;
                }
            }
        } else {
            $user_lat = $latitude;
            $user_lng = $longitude;
        }

        $distance = $this->distance($user_lat, $user_lng, $storeInfo['latitude'], $storeInfo['longitude']);

        return $distance <= $storeInfo['valid_range'];
    }
}
