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
namespace app\controller\api\v2\shareholder;

use app\Request;
use app\services\other\AgreementServices;
use app\services\user\UserBrokerageServices;
use app\services\user\UserServices;

class ShareholderController
{
    /**
     * 获取用户推广用户列表
     * @param Request $request
     * @param UserServices $userServices
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function shareholderUserList(Request $request, UserServices $userServices)
    {
        [$type] = $request->getMore([
            ['type', 0]
        ], true);
        $uid = $request->uid();
        return app('json')->successful($userServices->shareholderUserList($uid, $type));
    }

    /**
     * 获取用户推广获得收益，佣金轮播，分销规则
     * @param Request $request
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function shareholderInfo(Request $request)
    {
        /** @var AgreementServices $agreementService */
        $agreementService = app()->make(AgreementServices::class);
        /** @var UserBrokerageServices $userBrokerageServices */
        $data['agreement'] = $agreementService->getAgreementBytype(3)['content'] ?? '';

        return app('json')->successful($data);
    }
}
