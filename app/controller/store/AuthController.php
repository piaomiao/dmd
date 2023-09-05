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
namespace app\controller\store;


use crmeb\basic\BaseController;

/**
 * 基类 所有控制器继承的类
 * Class AuthController
 * @package app\controller\admin
 * @method success($msg = 'ok', array $data = [])
 * @method fail($msg = 'error', array $data = [])
 */
class AuthController extends BaseController
{
    /**
     * 门店整体类型1：平台2：门店
     * @var int
     */
    protected $type = 2;
    /**
     * 当前登录门店信息
     * @var
     */
    protected $storeInfo;

    /**
     * 当前登门店ID
     * @var
     */
    protected $storeId;

    /**
     * 当前登录门店店员ID
     * @var
     */
    protected $storeStaffId;
    /**
     * 当前登录门店店员信息
     * @var
     */
    protected $storeStaffInfo;

    /**
     * 当前管理员权限
     * @var array
     */
    protected $auth = [];


    /**
     * 初始化
     */
    protected function initialize()
    {
        $this->storeId = $this->request->hasMacro('storeId') ? $this->request->storeId() : 0;
        $this->storeStaffId = $this->request->hasMacro('storeStaffId') ? $this->request->storeStaffId() : 0;
        $this->storeStaffInfo = $this->request->hasMacro('storeStaffInfo') ? $this->request->storeStaffInfo() : [];
        $this->auth = $this->storeStaffInfo['rule'] ?? [];
    }

}
