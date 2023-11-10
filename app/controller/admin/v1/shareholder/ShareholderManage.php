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
namespace app\controller\admin\v1\shareholder;


use app\controller\admin\AuthController;
use app\jobs\user\UserFriendsJob;
use app\jobs\user\UserSpreadJob;
use app\services\shareholder\ShareholderLevelServices;
use app\services\shareholder\ShareholderManageServices;
use app\services\other\AgreementServices;
use app\services\store\SystemStoreShareServices;
use app\services\user\UserServices;
use crmeb\exceptions\AdminException;
use think\facade\App;
use think\facade\Db;

/**
 * 股东商管理控制器
 * Class ShareholderManage
 * @package app\controller\admin\v1\shareholder
 */
class ShareholderManage extends AuthController
{
    /**
     * ShareholderManage constructor.
     * @param App $app
     * @param ShareholderManageServices $services
     */
    public function __construct(App $app, ShareholderManageServices $services)
    {
        parent::__construct($app);
        $this->services = $services;
    }

    /**
     * 股东管理列表
     * @return mixed
     */
    public function index()
    {
        $where = $this->request->getMore([
            ['nickname', ''],
            ['data', ''],
        ]);
        return $this->success($this->services->shareholderSystemPage($where));
    }

    /**
     * 股东头部统计
     * @return mixed
     */
    public function get_badge()
    {
        $where = $this->request->getMore([
            ['data', ''],
            ['nickname', ''],
        ]);
        return $this->success(['res' => $this->services->getSpreadBadge($where)]);
    }

    /**
     * 推广人列表
     * @return mixed
     */
    public function get_stair_list()
    {
        $where = $this->request->getMore([
            ['uid', 0],
            ['data', ''],
            ['nickname', ''],
            ['type', '']
        ]);
        return $this->success($this->services->getStairList($where));
    }

    /**
     * 推广人列表头部统计
     * @return mixed
     */
    public function get_stair_badge()
    {
        $where = $this->request->getMore([
            ['uid', ''],
            ['data', ''],
            ['nickname', ''],
            ['type', ''],
        ]);
        return $this->success(['res' => $this->services->getSairBadge($where)]);
    }

    /**
     * 股份变更记录
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function logs($id)
    {
        if (!$id) $this->fail('缺少参数');
        return $this->success($this->services->getShareLogs($id));
    }



    /**
     * 解除单个用户的推广权限
     * @param int $uid
     * */
    public function delete($id)
    {
        if (!$id) $this->fail('缺少参数');
        Db::name("system_store_share")->where('id', $id)->update(['is_del'=>1, 'status' => 0]);
        return $this->success('删除成功');
    }

    /**
     * 修改上级推广人
     * @param UserServices $services
     * @return mixed
     */
    public function editSpread(UserServices $services)
    {
        [$uid, $spreadUid] = $this->request->postMore([
            [['uid', 'd'], 0],
            [['spread_uid', 'd'], 0],
        ], true);
        if (!$uid || !$spreadUid) {
            return $this->fail('缺少参数');
        }
        if ($uid == $spreadUid) {
            return $this->fail('上级推广人不能为自己');
        }
        $userInfo = $services->get($uid);
        if (!$userInfo) {
            return $this->fail('用户不存在');
        }
        if (!$services->count(['uid' => $spreadUid])) {
            return $this->fail('上级用户不存在');
        }
        if ($userInfo->spread_uid == $spreadUid) {
            return $this->fail('当前推广人已经是所选人');
        }
        $spreadInfo = $services->get($spreadUid);
        if ($spreadInfo->spread_uid == $uid) {
            return $this->fail('上级推广人不能为自己下级');
        }
        $userInfo->spread_uid = $spreadUid;
        $userInfo->spread_time = time();
        $userInfo->save();
        //记录推广绑定关系
        UserSpreadJob::dispatch([(int)$uid, (int)$spreadUid]);
        //记录好友关系
        UserFriendsJob::dispatch([(int)$uid, (int)$spreadUid]);
        return $this->success('修改成功');
    }


    /**
     * 获取赠送股东等级表单
     * @param $uid
     * @return mixed
     */
    public function getUpdateForm(SystemStoreShareServices $services,$id)
    {
        if (!$id) $this->fail('缺少参数');
        return $this->success($services->updateStoreShareForm((int)$id));
    }


    public function save(SystemStoreShareServices $services, $id = 0)
    {
        
		if ($id) {
            $data = $this->request->postMore([
                ['number', 0],
                ['status', 1],
            ]);
            if (!$data['number']) {
                return $this->fail('请输入股份数量');
            }
            $shareInfo = $services->get($id);
            // return $shareInfo->user;
            if (!$shareInfo) {
                throw new AdminException('门店股东不存在!');
            }
            $data['update_time'] = time();
			$services->update($id, $data);
            Db::name("system_store_share_log")->insert([
                'share_id' => $id,
                'after' => $data['number'],
                'add_time' => time(),
                'msg' => '管理员修改股份为' .$data['number'],
                'order_id' => 0,
            ]);
		} else {
            $data = $this->request->postMore([
                ['number', 0],
                ['status', 1],
                ['image', []],
                ['image2', []],
            ]);
            if (!$data['number'] || $data['number']!=intval($data['number'])) {
                return $this->fail('请输入股份数量');
            }
            if (empty($data['image']['uid'])) {
                return $this->fail('请选择股份绑定用户');
            }
            if (empty($data['image2']['store_id'])) {
                return $this->fail('请选择股份绑定门店');
            }
            $data['uid'] = $data['image']['uid'];
            $data['store_id'] = $data['image2']['store_id'];
            unset($data['image'], $data['image2']);
            $a = Db::name("system_store_share")->where(['uid' => $data['uid'], 'store_id' => $data['store_id']])->find();
            if($a) {
                return $this->fail('用户已经是股东');
            }
			$data['add_time'] = time();
            $data['update_time'] = time();
			$services->save($data);
            $id = Db::getLastInsetId();
            Db::name("system_store_share_log")->insert([
                'share_id' => $id,
                'after' => $data['number'],
                'add_time' => time(),
                'msg' => '管理员设置为股东，初始股份为' .$data['number'],
                'order_id' => 0,
            ]);
		}
        
        return $this->success('保存股东成功!');
    }

    /**
     * 赠送股东等级
     * @param ShareholderLevelServices $services
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function giveShareholderLevel(ShareholderLevelServices $services)
    {
        [$uid, $id] = $this->request->postMore([
            [['uid', 'd'], 0],
            [['id', 'd'], 0],
        ], true);
        if (!$uid || !$id) {
            return $this->fail('缺少参数');
        }
        return $this->success($services->givelevel((int)$uid, (int)$id) ? '赠送成功' : '赠送失败');
    }

    /**
     * 保存股东说明
     * @param $id
     * @param AgreementServices $agreementServices
     * @return mixed
     */
    public function setShareholderAgreement($id, AgreementServices $agreementServices)
    {
        $data = $this->request->postMore([
            ['type', 2],
            ['title', ""],
            ['content', ''],
            ['status', ''],
        ]);
		$data['title'] = $data['title'] ?: '股东说明';
        return $this->success($agreementServices->saveAgreement($data, $id));
    }

    /**
     * 获取股东说明
     * @param AgreementServices $agreementServices
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getShareholderAgreement(AgreementServices $agreementServices)
    {
        $list = $agreementServices->getAgreementBytype(3);
        if($list && isset($list['title']))  $list['title'] = $list['title'] ?: '股东说明';
        return $this->success($list);
    }
}
