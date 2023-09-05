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

namespace app\services\message;

use app\dao\message\SystemMessageDao;
use app\services\BaseServices;
use think\exception\ValidateException;

/**
 * 站内信
 * Class SystemMessageServices
 * @package app\services\message
 * @mixin SystemMessageDao
 */
class SystemMessageServices extends BaseServices
{

    /**
     * SystemMessageServices constructor.
     * @param SystemMessageDao $dao
     */
    public function __construct(SystemMessageDao $dao)
    {
        $this->dao = $dao;
    }

	/**
	 * 移动端消息列表
	 * @param $uid
	 * @return array
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\DbException
	 * @throws \think\db\exception\ModelNotFoundException
	 */
    public function getMessageSystemList($uid)
    {
        [$page, $limit] = $this->getPageValue();
        $where['is_del'] = 0;
        $where['uid'] = $uid;
        $list = $this->dao->getMessageList($where, '*', $page, $limit);
        $count = $this->dao->getCount($where);
        if (!$list) return ['list' => [], 'count' => 0];
        foreach ($list as &$item) {
            $item['add_time'] = time_tran($item['add_time']);
        }
        $message = $this->dao->count(['uid' => $uid, 'look' => 0, 'is_del' => 0]);
        return ['list' => $list, 'count' => $count, 'service_num' => $message];
    }

	/**
	 * 消息详情
	 * @param int $id
	 * @param int $uid
	 * @return array
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\DbException
	 * @throws \think\db\exception\ModelNotFoundException
	 */
    public function getInfo(int $id, int $uid = 0)
    {
        $info = $this->dao->getOne(['id' => $id]);
        if (!$info || $info['is_del'] == 1) {
            throw new ValidateException('数据不存在');
        }
		if ($uid && $info['uid'] != $uid) {
			throw new ValidateException('数据不存在');
		}
        $info = $info->toArray();
        if ($info['look'] == 0) {
            $this->update($info['id'], ['look' => 1]);
        }
        $info['add_time'] = time_tran($info['add_time']);
        return $info;
    }

    /**
     * 站内信发放
     * @param int $uid
     * @param array $noticeInfo
     * @return \crmeb\basic\BaseModel|\think\Model
     */
    public function systemSend(int $uid, array $noticeInfo)
    {
        $data = [];
        $data['mark'] = $noticeInfo['mark'];
        $data['uid'] = $uid;
        $data['title'] = $noticeInfo['title'];
        $data['content'] = $noticeInfo['content'];
        $data['type'] = 1;
        $data['add_time'] = time();
        return $this->dao->save($data);
    }

	/**
	 * 一键已读
	 * @param int $uid
	 * @return bool
	 */
	public function setAllRead(int $uid)
	{
		if (!$this->dao->count(['uid' => $uid, 'is_del' => 0, 'look' => 0])) {
			throw new ValidateException('暂无未读消息');
		}
		$this->dao->update(['uid' => $uid], ['look' => 1]);
		return true;
	}

	/**
	 * 删除消息
	 * @param int $uid
	 * @param array $ids
	 * @return bool
	 */
	public function setBatchDel(int $uid, array $ids = [])
	{
		if (!$this->dao->count(['uid' => $uid, 'is_del' => 0])) {
			throw new ValidateException('暂无消息');
		}
		$where = ['uid' => $uid];
		if ($ids) {//单个删除验证数据
			if (count($ids) == 1) $this->getInfo((int)$ids[0], $uid);
			$where['id'] = $ids;
		}
		$this->dao->update($where, ['is_del' => 1]);
		return true;
	}


}
