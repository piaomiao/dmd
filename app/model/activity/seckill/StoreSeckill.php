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

namespace app\model\activity\seckill;

use app\model\activity\StoreActivity;
use app\model\product\product\StoreDescription;
use app\model\product\product\StoreProduct;
use app\model\product\sku\StoreProductAttrValue;
use crmeb\basic\BaseModel;
use crmeb\traits\ModelTrait;
use think\Model;

/**
 * 秒杀商品Model
 * Class StoreSeckill
 * @package app\model\activity\seckill
 */
class StoreSeckill extends BaseModel
{
    use ModelTrait;

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'store_seckill';

    /**
     * 配送信息
     * @param $value
     * @return false|string
     */
    protected function setDeliveryTypeAttr($value)
    {
        if ($value) {
            return is_array($value) ? implode(',', $value) : $value;
        }
        return '';
    }

    /**
     * 配送信息
     * @param $value
     * @param $data
     * @return mixed
     */
    protected function getDeliveryTypeAttr($value)
    {
        if ($value) {
            return is_string($value) ? explode(',', $value) : $value;
        }
        return [];
    }

    /**
     * 配送信息
     * @param $value
     * @param $data
     * @return mixed
     */
    protected function getCustomFormAttr($value)
    {
        if ($value) {
            return is_string($value) ? json_decode($value, true) : $value;
        }
        return [];
    }

    /**
     * 商品标签
     * @param $value
     * @return array|mixed
     */
    protected function getStoreLabelIdAttr($value)
    {
        if ($value) {
            return is_string($value) ? explode(',', $value) : $value;
        }
        return [];
    }

    /**
     * 商品保障服务
     * @param $value
     * @return array|mixed
     */
    protected function getEnsureIdAttr($value)
    {
        if ($value) {
            return is_string($value) ? explode(',', $value) : $value;
        }
        return [];
    }

    /**
     * 参数信息
     * @param $value
     * @return array|mixed
     */
    protected function getSpecsAttr($value)
    {
        if ($value) {
            return is_string($value) ? json_decode($value, true) : $value;
        }
        return [];
    }

	/**
	 * 添加时间获取器
	 * @param $value
	 * @return false|string
	 */
	protected function getAddTimeAttr($value)
	{
		if ($value) return date('Y-m-d H:i:s', (int)$value);
		return '';
	}

	/**
	 * 图片获取器
	 * @param $value
	 * @return array|mixed
	 */
	protected function getImagesAttr($value)
	{
		return json_decode($value, true) ?: [];
	}

	/**
	 * 适用门店信息
	 * @param $value
	 * @return string
	 */
	protected function setApplicableStoreIdAttr($value)
	{
		if ($value) {
			return is_array($value) ? implode(',', $value) : $value;
		}
		return '';
	}

	/**
	 * 适用门店信息
	 * @param $value
	 * @return array|false|string[]
	 */
	protected function getApplicableStoreIdAttr($value)
	{
		if ($value) {
			return is_string($value) ? array_map('intval', array_filter(explode(',', $value))) : $value;
		}
		return [];
	}

	/**
	 * 时间段ID
	 * @param $value
	 * @return string
	 */
	protected function setTimeIdAttr($value)
	{
		if ($value) {
			return is_array($value) ? implode(',', $value) : $value;
		}
		return '';
	}

	/**
	 * 时间段ID
	 * @param $value
	 * @return array|false|string[]
	 */
	protected function getTimeIdAttr($value)
	{
		if ($value) {
			return is_string($value) ? array_map('intval', array_filter(explode(',', $value))) : $value;
		}
		return [];
	}

    /**
     * 一对一关联
     * 商品关联商品商品详情
     * @return \think\model\relation\HasOne
     */
    public function descriptions()
    {
        return $this->hasOne(StoreDescription::class, 'product_id', 'id')->where('type', 1)->bind(['description']);
    }

    /**
     * 一对一关联
     * 商品关联商品商品详情
     * @return \think\model\relation\HasOne
     */
    public function product()
    {
        return $this->hasOne(StoreProduct::class, 'id', 'product_id')->where('is_show', 1)->where('is_del', 0)->field(['SUM(sales+ficti) as total', 'id'])->bind([
            'total' => 'total'
        ]);
    }

	/**
	 * sku一对多
	 * @return \think\model\relation\HasMany
	 */
	public function attrValue()
	{
		return $this->hasMany(StoreProductAttrValue::class, 'product_id', 'id')->where('type', 1);
	}

	/**
	 * 一对一关联
	 * 商品关联活动
	 * @return \think\model\relation\HasOne
	 */
	public function activity()
	{
		return $this->hasOne(StoreActivity::class, 'id', 'activity_id');
	}

	/**
	 * 一对一关联
	 * 商品关联活动
	 * @return \think\model\relation\HasOne
	 */
	public function activityName()
	{
		return $this->hasOne(StoreActivity::class, 'id', 'activity_id')->field(['id', 'name'])->bind([
			'activity_name' => 'name'
		]);
	}

	/**
	 * 活动ID搜索器
	 * @param Model $query
	 * @param $value
	 */
	public function searchActivityIdAttr($query, $value)
	{
		if ($value) {
			if (is_array($value)) {
				$query->whereIn('activity_id', $value);
			} else {
				$query->where('activity_id', $value);
			}
		}
	}

	/**
	 * 秒杀时间段
	 * @param $query
	 * @param $value
	 */
	public function searchTimeIdAttr($query, $value)
	{
		if ($value) $query->where('find_in_set(' . $value . ',`time_id`)');
	}

    /**
     * 秒杀商品名称搜索器
     * @param Model $query
     * @param $value
     * @param $data
     */
    public function searchStoreNameAttr($query, $value, $data)
    {
        if ($value) $query->where('title|id', 'like', '%' . $value . '%');
    }

    /**
     * 是否推荐搜索器
     * @param Model $query
     * @param $value
     * @param $data
     */
    public function searchIsHotAttr($query, $value, $data)
    {
        $query->where('is_hot', $value ?? 1);
    }

    /**
     * 状态搜索器
     * @param Model $query
     * @param $value
     * @param $data
     */
    public function searchIsShowAttr($query, $value, $data)
    {
        $query->where('is_show', $value ?? 1);
    }

    /**
     * 是否删除搜索器
     * @param Model $query
     * @param $value
     * @param $data
     */
    public function searchIsDelAttr($query, $value, $data)
    {
        $query->where('is_del', $value ?? 0);
    }

    /**
     * 状态搜索器
     * @param Model $query
     * @param $value
     * @param $data
     */
    public function searchStatusAttr($query, $value, $data)
    {
        if ($value != '') $query->where('status', $value);
    }

    /**
     * 商品ID搜索器
     * @param Model $query
     * @param $value
     * @param $data
     */
    public function searchProductIdAttr($query, $value, $data)
    {
        if ($value) {
            if (is_array($value)) {
                $query->whereIn('product_id', $value);
            } else {
                $query->where('product_id', $value);
            }
        }
    }

    /**
     * 活动有效时间搜索器
     * @param $query
     * @param $value
     */
    public function searchSeckillTimeAttr($query, $value)
    {
        if ($value == 1) {
            $time = time();
            $query->where('start_time', '<=', $time)->where('stop_time', '>=', $time - 86400);
        }
    }

	/**
	 * 系统表单搜索器
	 * @param Model $query
	 * @param $value
	 */
	public function searchSystemFormIdAttr($query, $value)
	{
		if (is_array($value)) {
			if ($value) $query->whereIn('system_form_id', $value);
		} else {
			if ($value !== '') $query->where('system_form_id', $value);
		}
	}

	/**
	 * 适用门店类型搜索器
	 * @param Model $query
	 * @param $value
	 */
	public function searchApplicableTypeAttr($query, $value)
	{
		if (is_array($value)) {
			if ($value) $query->whereIn('applicable_type', $value);
		} else {
			if ($value !== '') $query->where('applicable_type', $value);
		}
	}
}
