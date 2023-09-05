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
namespace app\validate\admin\merchant;

use think\Validate;

class SystemStoreValidate extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名'    =>    ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'name' => 'require',
        'introduction' => 'require',
        'phone' => ['require','mobile'],
        'address' => 'require',
        'detailed_address' => 'require',
        'longitude' => 'require',
        'latitude' => 'require',
        'day_time' => 'require',
		'store_account' => ['require', 'length:4,64'],
		'true_password' => ['require', 'length:4,64'],
		'store_password' => ['require', 'length:4,64'],

    ];
    /**
     * 定义错误信息
     * 格式：'字段名.规则名'    =>    '错误信息'
     *
     * @var array
     */
    protected $message = [
        'name.require' => '请填写门店名称',
        'introduction.require' => '请填写门店简介',
        'phone.require' => '请填写门店电话',
        'phone.mobile' => '手机号格式不正确',
        'address.require' => '请选择地址',
        'detailed_address.require' => '请填写详细地址',
        'longitude.require' => '请选择经纬度',
        'latitude.require' => '请选择经纬度',
        'day_time.require' => '请选择营业时间',
		'store_account.require' => '请填写门店管理员账号',
		'store_account.length' => '门店管理员账号长度4-64位字符',
		'true_password.require' => '请填写门店管理员确认密码',
		'true_password.length' => '门店管理员确认密码长度4-64位字符',
		'store_password.require' => '请填写门店管理员密码',
		'store_password.length' => '门店管理员密码长度4-64位字符',
    ];

    protected $scene = [
		'reset' => ['store_account', 'true_password', 'store_password'],
        'save' => ['name', 'phone', 'address', 'detailed_address', 'latlng', 'day_time', 'store_account', 'store_password'],
		'update' => ['name', 'phone', 'address', 'detailed_address', 'latlng', 'day_time'],
    ];
}
