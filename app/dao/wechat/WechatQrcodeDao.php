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

namespace app\dao\wechat;

use think\model;
use app\dao\BaseDao;
use app\model\wechat\WechatQrcode;

/**
 *
 * Class UserWechatUserDao
 * @package app\dao\user
 */
class WechatQrcodeDao extends BaseDao
{
    /**
     * @return string
     */
    protected function setModel(): string
    {
        return WechatQrcode::class;
    }

    /**
     * 获取列表
     * @param $where
     * @param $page
     * @param $limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getList($where, $page = 0, $limit = 0)
    {
        return $this->search($where)->with(['user', 'record' => function ($query) {
            $query->where('is_follow', 1)->whereDay('add_time', 'yesterday')->field('qid,count(distinct uid) as number')->bind(['y_follow' => 'number']);
        }])->when($page && $limit, function ($query) use ($page, $limit) {
            $query->page($page, $limit);
        })->order('id desc')->select()->toArray();
    }

    /**
     * 更新次数
     * @param $id
     * @param $isFollow
     */
    public function upFollowAndScan($id, $isFollow)
    {
        $this->getModel()->where('id', $id)->inc('scan')->when($isFollow, function ($query) {
            $query->inc('follow');
        })->update();
    }

}
