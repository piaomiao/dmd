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
namespace app\validate\store;

use think\Validate;

class StoreAdminValidate extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名'    =>    ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'account' => ['require', 'length:4,64'],
        'conf_pwd' => ['require', 'length:4,64'],
        'pwd' => ['require', 'length:4,64'],
        'staff_name' => 'require',
        'roles' => ['require', 'array'],
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名'    =>    '错误信息'
     *
     * @var array
     */
    protected $message = [
        'account.require' => '请填写门店管理员账号',
		'account.length' => '门店管理员账号长度4-64位字符',
        'conf_pwd.require' => '请输入确认密码',
		'conf_pwd.length' => '确认密码长度4-64位字符',
        'pwd.require' => '请输入密码',
		'pwd.length' => '密码长度4-64位字符',
        'staff_name.require' => '请输门店管理员姓名',
        'roles.require' => '请选择管理员身份',
        'roles.array' => '身份必须为数组',
    ];

    protected $scene = [
        'get' => ['account', 'pwd'],
        'update' => ['account', 'roles'],
    ];


}
