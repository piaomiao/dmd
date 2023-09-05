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

namespace app\model\system\form;


use app\model\user\User;
use crmeb\basic\BaseModel;
use crmeb\traits\ModelTrait;
use think\Model;


class SystemFormData extends BaseModel
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
    protected $name = 'system_form_data';

    protected $updateTime = false;


    /**
     * 添加时间获取器
     * @param $value
     * @return false|string
     */
    public function getAddTimeAttr($value)
    {
        return date('Y-m-d H:i:s', $value);
    }

	/**
	 * 收集数据信息
	 * @param $value
	 * @return array|mixed
	 */
	protected function getValueAttr($value)
	{
		if ($value) {
			return is_string($value) ? json_decode($value, true) : $value;
		}
		return [];
	}

	/**
	 * 关联user
	 * @return \think\model\relation\HasOne
	 */
	public function user()
	{
		return $this->hasOne(User::class, 'uid', 'uid')->field('uid,nickname,avatar,phone')->bind(['nickname', 'avatar', 'phone']);
	}

	/**
	 * 关联系统表单
	 * @return \think\model\relation\HasOne
	 */
	public function systemForm()
	{
		return $this->hasOne(SystemForm::class, 'id', 'system_form_id')->field('id,name')->bind(['system_form_name' => 'name']);
	}

	/**
	 * UID搜索器
	 * @param Model $query
	 * @param $value
	 */
	public function searchUidAttr($query, $value)
	{
		if (is_array($value)) {
			if ($value) $query->whereIn('uid', $value);
		} else {
			if ($value !== '') $query->where('uid', $value);
		}
	}

	/**
	 * system_form_id搜索器
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
     * 类型搜索器
     * @param Model $query
     * @param $value
     */
    public function searchTypeAttr($query, $value)
    {
        if ($value != '') {
            if (is_array($value)) {
                $query->whereIn('type', $value);
            } else {
                $query->where('type', $value);
            }
        }
    }

	/**
	 * 关联门店ID、供应商ID搜索器
	 * @param Model $query
	 * @param $value
	 */
	public function searchRelationIdAttr($query, $value)
	{
		if (is_array($value)) {
			if ($value) $query->whereIn('relation_id', $value);
		} else {
			if ($value !== '') $query->where('relation_id', $value);
		}
	}

    /**
     * 是否删除搜索器
     * @param Model $query
     * @param $value
     */
    public function searchIsDelAttr($query, $value)
    {
        if ($value !== '') $query->where('is_del', $value);
    }
}
