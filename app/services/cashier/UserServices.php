<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------
namespace app\services\cashier;


use app\services\BaseServices;
use app\services\user\UserWechatuserServices;

/**
 * 收银台用户Services
 */
class UserServices extends BaseServices
{
    protected $userType = [
        'wechat' => '公众号',
        'routine' => '小程序',
        'h5' => 'H5',
        'pc' => 'PC',
        'app' => 'APP'
    ];

    /**
     * 收银台选择用户列表
     * @param array $where
     * @return array
     */
    public function getUserList(array $where)
    {
        /** @var UserWechatuserServices $services */
        $services = app()->make(UserWechatuserServices::class);
        [$list, $count] = $services->getWhereUserList($where, 'u.uid,avatar,u.nickname,phone,u.user_type,now_money,integral');
        foreach ($list as &$item) {
            $item['user_type'] = $this->userType[$item['user_type']] ?? '其他';
        }
        return compact('list', 'count');
    }
}
