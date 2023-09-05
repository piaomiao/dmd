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
namespace app\model\activity\collage;

use crmeb\basic\BaseModel;
use crmeb\traits\ModelTrait;
use app\model\order\StoreOrder;
use app\model\activity\table\TableQrcode;

/**
 *  拼单Model
 * Class UserCollage
 * @package app\model\collage
 */
class UserCollageCode extends BaseModel
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
    protected $name = 'user_collage_code';

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

    /**一对一关联
     * 关联订单信息
     * @return \think\model\relation\HasOne
     */
    public function orderId()
    {
        return $this->hasOne(StoreOrder::class, 'id', 'oid')->field(['id','order_id','pay_type','total_price','pay_price','paid','refund_status','staff_id', 'is_del', 'is_system_del']);
    }

    /**一对一关联
     * 关联桌码信息
     * @return \think\model\relation\HasOne
     */
    public function qrcode()
    {
        return $this->hasOne(TableQrcode::class, 'id', 'qrcode_id')->field(['id','cate_id','table_number','is_using','is_del']);
    }
}
