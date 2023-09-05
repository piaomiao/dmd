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
declare (strict_types=1);

namespace app\services\cashier;

use app\Request;
use app\services\BaseServices;
use app\dao\store\SystemStoreStaffDao;
use app\services\system\SystemMenusServices;
use app\services\system\SystemRoleServices;
use app\services\store\SystemStoreServices;
use crmeb\exceptions\AdminException;
use crmeb\exceptions\AuthException;
use crmeb\services\CacheService;
use crmeb\traits\ServicesTrait;
use crmeb\utils\ApiErrorCode;
use crmeb\utils\JwtAuth;
use Firebase\JWT\ExpiredException;
use think\exception\ValidateException;
use think\facade\Cache;


/**
 *
 * Class LoginServices
 * @package app\services\user
 * @mixin SystemStoreStaffDao
 */
class LoginServices extends BaseServices
{

    use ServicesTrait;

    /**
     * 当前门店权限缓存前缀
     */
    const STORE_CASHIER_RULES_LEVEL = 'store_cashier_rules_level_';

    /**
     * LoginServices constructor.
     * @param SystemStoreStaffDao $dao
     */
    public function __construct(SystemStoreStaffDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取登陆前的login等信息
     * @return array
     */
    public function getLoginInfo()
    {
        return [
            'slide' => sys_data('admin_login_slide') ?? [],
            'logo_square' => sys_config('site_logo_square'),//透明
            'logo_rectangle' => sys_config('site_logo'),//方形
            'login_logo' => sys_config('login_logo'),//登陆
            'site_name' => sys_config('site_name'),
            'site_url' => sys_config('site_url'),
        ];
    }

    /**
     * H5账号登陆
     * @param Request $request
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function login($account, $password, $type)
    {
        $storeStaffInfo = $this->dao->getOne(['account|phone' => $account, 'is_del' => 0]);
		$key = 'cashier_login_captcha_' . $account;
        if (!$storeStaffInfo) {
			Cache::inc($key);
            throw new AdminException('账号不存在!');
        }
        if ($password) {//平台还可以登录
            if (!$storeStaffInfo->status) {
				Cache::inc($key);
                throw new AdminException('您已被禁止登录!');
            }
            if (!password_verify($password, $storeStaffInfo->pwd)) {
				Cache::inc($key);
                throw new AdminException('账号或密码错误，请重新输入');
            }
        }
        return $this->getLoginResult((int)$storeStaffInfo['id'], $type, $storeStaffInfo);
    }

    /**
	 * 企业微信扫码登录
     * @param array $workUserInfo
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function workScanLogin(array $workUserInfo)
    {
        if (0 !== $workUserInfo['errcode']) {
            throw new ValidateException($workUserInfo['errmsg']);
        }
        if (empty($workUserInfo['mobile'])) {
            throw new ValidateException('改成员请先关联手机号在进行登录');
        }
        $storeStaffInfo = $this->dao->getOne(['phone' => $workUserInfo['mobile'], 'is_del' => 0]);
        if (!$storeStaffInfo) {
            throw new AdminException('账号不存在!');
        }

        return $this->getLoginResult((int)$storeStaffInfo['id'], 'cashier', $storeStaffInfo);
    }


    /**
     * 获取登录店员信息
     * @param int $id
     * @param string $type
     * @param array $storeStaffInfo
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getLoginResult(int $id, string $type, $storeStaffInfo = [])
    {
        if (!$storeStaffInfo) {
            $storeStaffInfo = $this->dao->get(['id' => $id, 'is_del' => 0]);
        }
        if (!$storeStaffInfo->is_cashier) {
            throw new AdminException('您没有收银员的身份无法登录');
        }
        if (!$storeStaffInfo) {
            throw new AdminException('账号不存在!');
        }
        $storeStaffInfo->last_time = time();
        $storeStaffInfo->last_ip = app('request')->ip();
        $storeStaffInfo->login_count++;
        $storeStaffInfo->save();

        $tokenInfo = $this->createToken($storeStaffInfo->id, $type, $storeStaffInfo->pwd);
        /** @var SystemMenusServices $services */
        $services = app()->make(SystemMenusServices::class);
        [$menus, $uniqueAuth] = $services->getMenusList($storeStaffInfo->roles, (int)($storeStaffInfo['level'] ?? 0), 3);
        /** @var SystemStoreServices $storeServices */
        $storeServices = app()->make(SystemStoreServices::class);
        $store = $storeServices->get((int)$storeStaffInfo['store_id'], ['id', 'image']);
        return [
            'token' => $tokenInfo['token'],
            'expires_time' => $tokenInfo['params']['exp'],
            'menus' => $menus,
            'unique_auth' => $uniqueAuth,
            'user_info' => [
                'id' => $storeStaffInfo->getData('id'),
                'account' => $storeStaffInfo->getData('account'),
                'avatar' => $storeStaffInfo->getData('avatar'),
            ],
            'logo' => $store && isset($store['image']) && $store['image'] ? $store['image'] : sys_config('site_logo'),
            'logo_square' => $store && isset($store['image']) && $store['image'] ? $store['image'] : sys_config('site_logo_square'),
            'version' => get_crmeb_version(),
            'newOrderAudioLink' => get_file_link(sys_config('new_order_audio_link', '')),
			'prefix' => config('admin.cashier_prefix')
        ];
    }


    /**
     * 重置密码
     * @param $account
     * @param $password
     */
    public function reset($account, $password)
    {
        $user = $this->dao->getOne(['account|phone' => $account]);
        if (!$user) {
            throw new ValidateException('用户不存在');
        }
        if (!$this->dao->update($user['uid'], ['pwd' => md5((string)$password)], 'uid')) {
            throw new ValidateException('修改密码失败');
        }
        return true;
    }


    /**
     * 获取Admin授权信息
     * @param string $token
     * @return array
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function parseToken(string $token): array
    {
        /** @var CacheService $cacheService */
        $cacheService = app()->make(CacheService::class);

        if (!$token || $token === 'undefined') {
            throw new AuthException(ApiErrorCode::ERR_LOGIN);
        }
        //检测token是否过期
        $md5Token = md5($token);
        if (!$cacheService->hasToken($md5Token) || !($cacheToken = $cacheService->getTokenBucket($md5Token))) {
            throw new AuthException(ApiErrorCode::ERR_LOGIN);
        }
        //是否超出有效次数
        if (isset($cacheToken['invalidNum']) && $cacheToken['invalidNum'] >= 3) {
            if (!request()->isCli()) {
                $cacheService->clearToken($md5Token);
            }
            throw new AuthException(ApiErrorCode::ERR_LOGIN_INVALID);
        }

        /** @var JwtAuth $jwtAuth */
        $jwtAuth = app()->make(JwtAuth::class);
        //设置解析token
        [$id, $type, $auth] = $jwtAuth->parseToken($token);
        //验证token
        try {
            $jwtAuth->verifyToken();
            $cacheService->setTokenBucket($md5Token, $cacheToken, $cacheToken['exp']);
        } catch (ExpiredException $e) {
            $cacheToken['invalidNum'] = isset($cacheToken['invalidNum']) ? $cacheToken['invalidNum']++ : 1;
            $cacheService->setTokenBucket($md5Token, $cacheToken, $cacheToken['exp']);
        } catch (\Throwable $e) {
            if (!request()->isCli()) {
                $cacheService->clearToken($md5Token);
            }
            throw new AuthException(ApiErrorCode::ERR_LOGIN_INVALID);
        }
        //获取管理员信息
        $storeStaffInfo = $this->dao->get($id);
        if (!$storeStaffInfo || !$storeStaffInfo->id || $storeStaffInfo->is_del) {
            if (!request()->isCli()) {
                $cacheService->clearToken($md5Token);
            }
            throw new AuthException(ApiErrorCode::ERR_LOGIN_STATUS);
        }

        if ($auth !== md5($storeStaffInfo['pwd'])) {
            throw new AuthException(ApiErrorCode::ERR_LOGIN_INVALID);
        }

        $storeStaffInfo->type = $type;
        return $storeStaffInfo->hidden(['pwd', 'is_del', 'status'])->toArray();
    }

    /**
     * 后台验证权限
     * @param Request $request
     */
    public function verifiAuth(Request $request)
    {
        $rule = str_replace('cashierapi/', '', trim(strtolower($request->rule()->getRule())));
        if (in_array($rule, ['cashier/logout', 'menuslist'])) {
            return true;
        }
		$method = trim(strtolower($request->method()));
		/** @var SystemRoleServices $roleServices */
        $roleServices = app()->make(SystemRoleServices::class);
        $auth = $roleServices->getAllRoles(2, 3, self::STORE_CASHIER_RULES_LEVEL);
        //验证访问接口是否存在
		if ($auth && !in_array($method . '@@' . $rule, array_map(function ($item) {
			return trim(strtolower($item['methods'])). '@@'. trim(strtolower(str_replace(' ', '', $item['api_url'])));
		}, $auth))) {
			return true;
		}
        $auth = $roleServices->getRolesByAuth($request->cashierInfo()['roles'], 2, 3, self::STORE_CASHIER_RULES_LEVEL);
        //验证访问接口是否有权限
        if ($auth && empty(array_filter($auth, function ($item) use ($rule, $method) {
            if (trim(strtolower($item['api_url'])) === $rule && $method === trim(strtolower($item['methods'])))
                return true;
        }))) {
            throw new AuthException(ApiErrorCode::ERR_AUTH);
        }
    }


}