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

namespace app\validate\kefu;


use think\Validate;

class LoginValidate extends Validate
{
    protected $regex = ['account' => '/^[a-zA-Z0-9]{4,30}$/'];
    /**
     * @var string[]
     */
    protected $rule = [
        'account' => ['require', 'account', 'length:4,64'],
        'password' => ['require', 'length:4,64']
    ];

    /**
     * @var string[]
     */
    protected $message = [
        'account.require' => '请填写账号',
        'account.account' => '请填写正确的账号',
		'account.length' => '账号长度4-64位字符',
        'password.require' => '请填写密码',
		'pwd.length' => '密码长度4-64位字符',
    ];
}
