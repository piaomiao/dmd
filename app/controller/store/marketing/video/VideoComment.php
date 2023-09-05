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
namespace app\controller\store\marketing\video;

use app\controller\store\AuthController;
use app\services\activity\video\VideoCommentServices;
use think\db\exception\DbException;
use think\facade\App;

/**
 *  视频评论控制器
 * Class VideoComment
 * @package app\controller\admin\v1\marketing\video
 */
class VideoComment extends AuthController
{
    /**
     * VideoComment constructor.
     * @param App $app
     * @param VideoCommentServices $service
     */
    public function __construct(App $app, VideoCommentServices $service)
    {
        parent::__construct($app);
        $this->services = $service;
    }

    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function index()
    {
        $where = $this->request->getMore([
            ['is_reply', ''],
            ['keyword', ''],
            ['data', '', '', 'time'],
            ['video_id', 0]
        ]);
		$where['type'] = 1;
		$where['relation_id'] = $this->storeId;
        $list = $this->services->sysPage($where);
        return $this->success($list);
    }

	/**
 	* 获取评论回复列表
	* @param $id
	* @return mixed
	* @throws DbException
	* @throws \think\db\exception\DataNotFoundException
	* @throws \think\db\exception\ModelNotFoundException
	 */
    public function getCommentReply($id)
    {
        if (!$id) {
            return $this->fail('缺少参数');
        }
        $time = $this->request->get('data', '');
        return $this->success($this->services->getCommentReplyList((int)$id, ['time' =>$time]));
    }

    /**
     * 管理员回复评论
     * @param $id
     * @return mixed
     */
    public function setReply($id)
    {
        [$content] = $this->request->postMore([
            ['content', '']
        ], true);
        $this->services->setReply($id, $content);
        return $this->success('回复成功!');
    }

    /**
     * 获取管理员评论
     * @param $id
     * @return mixed
     */
    public function getReply($id)
    {
        if (!$id) {
            return $this->fail('缺少参数');
        }
        $where = ['pid' => $id, 'uid' => 0];
        $commentInfo = $this->dao->get($where);
        if ($commentInfo) {
            return $this->success($commentInfo->toArray());
        } else {
            return $this->success(['content' => '']);
        }
    }

    /**
     * 创建自评表单
     * @return mixed
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function fictitiousComment()
    {
        [$video_id] = $this->request->postMore([
            ['video_id', 0],
        ], true);
        return $this->success($this->services->createForm((int)$video_id, (int)$this->storeId));
    }

    /**
     * 保存自评
     * @return mixed
     */
    public function saveFictitiousComment()
    {
        $data = $this->request->postMore([
			['video', ''],
            ['nickname', ''],
            ['avatar', ''],
            ['content', ''],
            ['video_id', 0],
            ['add_time', 0]
        ]);
        if (!$data['video_id']) {
            $data['video_id'] = $data['video']['video_id'] ?? '';
        }
		$video_id = (int)$data['video_id'];
		unset($data['video_id'], $data['video']);
		$data['ip'] = $this->request->ip();
        $this->services->saveComment(0, $video_id, 0, $data);
        return $this->success('添加成功!');
    }


	/**
     * 删除评论
     * @param $id
     * @return mixed
     */
    public function delete($id)
    {
        if (!$id) {
            return $this->fail('缺少参数');
        }
		$this->services->update($id, ['is_del' => 1]);
		$this->services->update(['pid' => $id], ['is_del' => 1]);

        return $this->success('删除成功');
    }

}
