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
namespace app\model\activity\table;

use crmeb\basic\BaseModel;
use crmeb\traits\ModelTrait;
use app\model\other\Category;
use app\model\store\SystemStore;
use app\model\activity\collage\UserCollageCode;

/**
 *  拼单Model
 * Class TableSeats
 * @package app\model\activity\table
 */
class TableQrcode extends BaseModel
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
    protected $name = 'table_qrcode';

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
     * 添加时间获取器
     * @param $value
     * @return false|string
     */
    protected function getOrderTimeAttr($value)
    {
        if ($value) return date('m-d H:i', (int)$value);
        return '';
    }

    /**一对一关联
     * 获取分类
     * @return \think\model\relation\HasOne
     */
    public function category()
    {
        return $this->hasOne(Category::class, 'id', 'cate_id')->where(['group' => 6, 'type' => 2])->field(['id', 'name']);
    }

    /**一对一关联
     * 获取门店名称
     * @return \think\model\relation\HasOne
     */
    public function storeName()
    {
        return $this->hasOne(SystemStore::class, 'id', 'store_id')->field(['id','name as storeName']);
    }

    /**一对一关联
     * 获取桌码记录信息
     * @return \think\model\relation\HasOne
     */
    public function collateCode()
    {
        return $this->hasOne(UserCollageCode::class, 'qrcode_id', 'id')->field(['qrcode_id','serial_number','number_diners']);
    }
}
