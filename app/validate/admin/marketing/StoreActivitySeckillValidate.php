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
namespace app\validate\admin\marketing;

use think\Validate;

class StoreActivitySeckillValidate extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名'    =>    ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'name|活动名称' => 'require',
        'section_data|活动日期' => 'require',
        'num|活动限购' => 'require|gt:0',
        'once_num|活动单次限购' => 'require|gt:0',
        'time_id|活动场次' => 'require',
        'seckill_ids|活动商品' => 'require',
    ];

}
