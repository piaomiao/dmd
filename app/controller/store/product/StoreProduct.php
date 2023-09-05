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
namespace app\controller\store\product;


use app\jobs\store\SynchStocksJob;
use app\services\product\branch\StoreBranchProductAttrValueServices;
use app\services\product\branch\StoreBranchProductServices;
use app\services\product\category\StoreProductCategoryServices;
use app\services\product\product\StoreProductServices;
use app\services\product\sku\StoreProductAttrValueServices;
use app\services\store\SystemStoreServices;
use app\services\user\label\UserLabelCateServices;
use app\services\user\label\UserLabelServices;
use crmeb\services\UploadService;
use think\facade\App;
use app\controller\store\AuthController;

/**
 * Class StoreProduct
 * @package app\controller\admin\v1\product
 */
class StoreProduct extends AuthController
{
    protected $services;
	protected $branchServices;

	/**
	* @param App $app
	* @param StoreProductServices $service
	* @param StoreBranchProductServices $branchServices
	 */
    public function __construct(App $app, StoreProductServices $service, StoreBranchProductServices $branchServices)
    {
        parent::__construct($app);
        $this->services = $service;
		$this->branchServices = $branchServices;
    }

    /**
     * 显示资源列表头部
     * @return mixed
     */
    public function type_header(StoreProductCategoryServices $storeProductCategoryServices)
    {
		$where = $this->request->getMore([
			['store_name', ''],
			['cate_id', ''],
			['type', 1, '', 'status'],
			['sales', 'normal'],
			['pid', ''],
		]);
		$cateId = $where['cate_id'];
		if ($cateId) {
			$cateId = is_string($cateId) ? [$cateId] : $cateId;
			$cateId = array_merge($cateId, $storeProductCategoryServices->cateIdByPid($cateId), $storeProductCategoryServices->getColumn(['pid' => $cateId], 'id'));
			$cateId = array_unique(array_diff($cateId, [0]));
		}
		$where['cate_id'] = $cateId;
        $list = $this->services->getHeader((int)$this->storeId, $where);
        return app('json')->success(compact('list'));
    }

    /**
     * 显示资源列表
     * @return mixed
     */
    public function index()
    {
        $where = $this->request->getMore([
            ['store_name', ''],
            ['cate_id', ''],
            ['type', 1, '', 'status'],
            ['sales', 'normal'],
            ['pid', ''],
        ]);
        $where['relation_id'] = $this->storeId;
		$where['type'] = 1;
        $data = $this->services->getList($where);
        return app('json')->success($data);
    }

	/**
     * 获取选择的商品列表
     * @return mixed
     */
    public function search_list()
    {
        $where = $this->request->getMore([
            ['cate_id', ''],
            ['store_name', ''],
            ['type', 1, '', 'status'],
            ['is_live', 0],
            ['is_new', ''],
            ['is_vip_product', ''],
            ['is_presale_product', ''],
            ['store_label_id', '']
        ]);
        $where['is_show'] = 1;
        $where['is_del'] = 0;
		$where['type'] = 1;
		$where['relation_id'] = $this->storeId;
        /** @var StoreProductCategoryServices $storeCategoryServices */
        $storeCategoryServices = app()->make(StoreProductCategoryServices::class);
        if ($where['cate_id'] !== '') {
            if ($storeCategoryServices->value(['id' => $where['cate_id']], 'pid')) {
                $where['sid'] = $where['cate_id'];
            } else {
                $where['cid'] = $where['cate_id'];
            }
        }
        unset($where['cate_id']);
        $list = $this->services->searchList($where);
        return $this->success($list);
    }

    /**
     * 获取分类cascader格式数据
     * @param $type
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function cascader_list(StoreProductCategoryServices $services)
    {
        return app('json')->success($services->cascaderList(1, (int)$this->storeId));
    }

    /**
     * 获取商品详细信息
     * @param int $id
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function read($id = 0)
    {
        return app('json')->success($this->services->getInfo((int)$id));
    }

	/**
 	* 保存新建或编辑
	* @param SystemStoreServices $storeServices
	* @param $id
	* @return mixed
	* @throws \think\db\exception\DataNotFoundException
	* @throws \think\db\exception\DbException
	* @throws \think\db\exception\ModelNotFoundException
	 */
    public function save(SystemStoreServices $storeServices, $id)
    {
        $data = $this->request->postMore([
            ['product_type', 0],//商品类型
            ['supplier_id', 0],//供应商ID
            ['cate_id', []],
            ['store_name', ''],
            ['store_info', ''],
            ['keyword', ''],
            ['unit_name', '件'],
            ['recommend_image', ''],
            ['slider_image', []],
            ['is_sub', []],//佣金是单独还是默认
            ['sort', 0],
            ['sales', 0],
            ['ficti', 100],
            ['give_integral', 0],
            ['is_show', 0],
            ['is_hot', 0],
            ['is_benefit', 0],
            ['is_best', 0],
            ['is_new', 0],
            ['mer_use', 0],
            ['is_postage', 0],
            ['is_good', 0],
            ['description', ''],
            ['spec_type', 0],
            ['video_open', 0],
            ['video_link', ''],
            ['items', []],
            ['attrs', []],
            ['recommend', []],//商品推荐
            ['activity', []],
            ['coupon_ids', []],
            ['label_id', []],
            ['command_word', ''],
            ['tao_words', ''],
            ['type', 0, '', 'is_copy'],
            ['delivery_type', []],//物流设置
            ['freight', 1],//运费设置
            ['postage', 0],//邮费
            ['temp_id', 0],//运费模版
            ['recommend_list', []],
            ['brand_id', []],
            ['soure_link', ''],
            ['bar_code', ''],
            ['code', ''],
            ['is_support_refund', 1],//是否支持退款
            ['is_presale_product', 0],//预售商品开关
            ['presale_time', []],//预售时间
            ['presale_day', 0],//预售发货日
            ['is_vip_product', 0],//是否付费会员商品
            ['auto_on_time', 0],//自动上架时间
            ['auto_off_time', 0],//自动下架时间
            ['custom_form', []],//自定义表单
			['system_form_id', 0],//系统表单ID
            ['store_label_id', []],//商品标签
            ['ensure_id', []],//商品保障服务区
            ['specs', []],//商品参数
            ['specs_id', 0],//商品参数ID
            ['is_limit', 0],//是否限购
            ['limit_type', 0],//限购类型
            ['limit_num', 0]//限购数量
        ]);
		//门店商品编辑 需要再次审核
		$storeId = (int)$this->storeId;
		$storeInfo = [];
		if ($storeId) {
			$storeInfo = $storeServices->getStoreInfo($storeId);
		}
		if (!$storeInfo) {
			return $this->fail('门店不存在或者已下架');
		}
		if (!$storeInfo['product_status']) {
			return $this->fail('暂不支持添加商品，请联系平台管理员');
		}
		$data['is_verify'] = 0;
		//门店开启免审
		if (isset($storeInfo['product_verify_status']) && $storeInfo['product_verify_status']) {
			$data['is_verify'] = 1;
		}
        $this->services->save((int)$id, $data, 1, (int)$this->storeId);
        return $this->success($id ? '保存商品信息成功' : '添加商品成功!');
    }

    /**
     * 获取编辑详情
     * @param int $id
     * @return mixed
     */
    public function edit($id = 0)
    {
        [$id] = $this->request->getMore([
            [['id', 'd'], 0],
        ], true);
        $store_id = $this->storeId;
        return app('json')->success($this->services->getStoreBranchInfo((int)$id, (int)$store_id));
    }

    /**
     * 保存编辑
     * @param int $id
     * @param StoreBranchProductServices $services
     * @return mixed
     */
    public function update($id = 0, StoreBranchProductAttrValueServices $services)
    {
        $data = $this->request->postMore([
            ['attrs', []],
            ['label_id', []],
            ['is_show', 1]
        ]);
        $storeId = $this->storeId;
        $services->updataAll((int)$id, (array)$data, (int)$storeId);
        return app('json')->success('保存商品信息成功');
    }

    /**
     * 门店同步库存
     * @return mixed
     */
    public function synchStocks()
    {
        [$ids] = $this->request->postMore([
            ['ids', []]
        ], true);
        if (!count($ids)) return $this->fail('请选择商品');
        $storeId = $this->storeId;
        //拆分大数组
        $idsArr = array_chunk($ids, 5);
        foreach ($idsArr as $syncIds) {
            //加入同步
            SynchStocksJob::dispatch([$syncIds, $storeId]);
        }
        return app('json')->success('库存同步已加入队列执行，请稍后查看');
    }

    /**
     * 获取关联用户标签列表
     * @param UserLabelServices $service
     * @return mixed
     */
    public function getUserLabel(UserLabelCateServices $userLabelCateServices, UserLabelServices $service)
    {
        $cate = $userLabelCateServices->getLabelCateAll((int)$this->type, (int)$this->storeId);
        $data = [];
        $label = [];
        if ($cate) {
            foreach ($cate as $value) {
                $data[] = [
                    'id' => $value['id'] ?? 0,
                    'value' => $value['id'] ?? 0,
                    'label_cate' => 0,
                    'label_name' => $value['name'] ?? '',
                    'label' => $value['name'] ?? '',
                    'store_id' => $value['store_id'] ?? 0,
                    'type' => $value['type'] ?? 1,
                ];
            }
            $label = $service->getColumn(['type' => $this->type, 'store_id' => $this->storeId], '*');
            if ($label) {
                foreach ($label as &$item) {
                    $item['label'] = $item['label_name'];
                    $item['value'] = $item['id'];
                }
            }
        }
        return app('json')->success($service->get_tree_children($data, $label));
    }

    /**
     * 修改状态
     * @param string $is_show
     * @param string $id
     * @return mixed
     */
    public function set_show($is_show = '', $id = '', StoreBranchProductServices $services)
    {
        if (!$id) return $this->fail('缺少商品ID');
        $services->setShow($this->storeId, $id, $is_show);
        return $this->success($is_show == 1 ? '上架成功' : '下架成功');
    }

	/**
     * 获取规格模板
     * @return mixed
     */
    public function get_rule()
    {
        $list = $this->services->getRule();
        return $this->success($list);
    }
	/**
     * 获取商品详细信息
     * @param int $id
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function get_product_info($id = 0)
    {
        return $this->success($this->services->getInfo((int)$id));
    }


	/**
     * 获取运费模板列表
     * @return mixed
     */
    public function get_template()
    {
        return $this->success($this->services->getTemp());
    }

    /**
     * 获取视频上传token
     * @return mixed
     * @throws \Exception
     */
    public function getTempKeys()
    {
        $upload = UploadService::init();
        $re = $upload->getTempKeys();
        return $re ? $this->success($re) : $this->fail($upload->getError());
    }

    /**
     * 获取商品所有规格数据
     * @param StoreBranchProductAttrValueServices $services
     * @param $id
     * @return mixed
     */
    public function getAttrs(StoreBranchProductAttrValueServices $services, $id)
    {
        if (!$id) {
            return $this->fail('缺少商品ID');
        }
        return $this->success($services->getStoreProductAttr((int)$id, (int)$this->storeId));
    }

	/**
     * 删除指定资源
     *
     * @param int $id
     * @return \think\Response
     */
    public function delete($id)
    {
        //删除商品检测是否有参与活动
        $this->services->checkActivity($id);
        $res = $this->services->del($id);
        event('product.delete', [$id]);
        return $this->success($res);
    }

	/**
     * 生成规格列表
     * @param int $id
     * @param int $type
     * @return mixed
     */
    public function is_format_attr($id = 0, $type = 0)
    {
        $data = $this->request->postMore([
            ['attrs', []],
            ['items', []],
            ['product_type', 0]
        ]);
        if ($id > 0 && $type == 1) $this->services->checkActivity($id);
        $info = $this->services->getAttr($data, $id, $type);
        return $this->success(compact('info'));
    }

	/**
     * 快速修改商品规格库存
     * @param StoreProductAttrValueServices $services
     * @param $id
     * @return mixed
     */
    public function saveProductAttrsStock(StoreProductAttrValueServices $services, $id)
    {
        if (!$id) {
            return $this->fail('缺少商品ID');
        }
        [$attrs] = $this->request->getMore([
            ['attrs', []]
        ], true);
        if (!$attrs) {
            return $this->fail('请重新修改规格库存');
        }
        foreach ($attrs as $attr) {
            if (!isset($attr['unique']) || !isset($attr['pm']) || !isset($attr['stock'])) {
                return $this->fail('请重新修改规格库存');
            }
        }
        return $this->success(['stock' => $services->saveProductAttrsStock((int)$id, $attrs)]);
    }
}
