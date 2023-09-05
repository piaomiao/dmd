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

namespace app\model\activity;

use app\model\activity\seckill\StoreSeckill;
use crmeb\basic\BaseModel;
use crmeb\traits\ModelTrait;
use think\Model;

/**
 * 活动model
 * Class StoreActivity
 * @package app\model\activity
 */
class StoreActivity extends BaseModel
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
    protected $name = 'store_activity';

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
	 * @return \think\model\relation\HasMany
	 */
	public function seckill()
	{
		return $this->hasMany(StoreSeckill::class, 'activity_id', 'id')->where('is_show', 1)->where('is_del', 0);
	}


    /**
     * 秒杀商品名称搜索器
     * @param Model $query
     * @param $value
     * @param $data
     */
    public function searchNameAttr($query, $value, $data)
    {
        if ($value) $query->where('title|id', 'like', '%' . $value . '%');
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
	 * 是否删除
	 * @param $query
	 * @param $value
	 * @return void
	 */
	public function searchIsDelAttr($query, $value)
	{
		if ($value !== '') $query->where('is_del', $value);
	}


}
