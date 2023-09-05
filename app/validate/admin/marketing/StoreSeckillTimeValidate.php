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

class StoreSeckillTimeValidate extends Validate
{

	protected $rule = [
		'time|时间选择' => 'require',
		'status|状态'        => 'require|in:0,1',
		'pic|图片' => 'require',
	];

}
