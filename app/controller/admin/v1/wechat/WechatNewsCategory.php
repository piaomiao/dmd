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
namespace app\controller\admin\v1\wechat;

use app\controller\admin\AuthController;
use app\services\article\ArticleServices;
use app\services\user\UserBatchProcessServices;
use app\services\wechat\WechatNewsCategoryServices;
use think\facade\App;

/**
 * 图文信息
 * Class WechatNewsCategory
 * @package app\controller\admin\v1\application\wechat
 *
 */
class WechatNewsCategory extends AuthController
{
    /**
     * 构造方法
     * Menus constructor.
     * @param App $app
     * @param WechatNewsCategoryServices $services
     */
    public function __construct(App $app, WechatNewsCategoryServices $services)
    {
        parent::__construct($app);
        $this->services = $services;
    }

    /**
     * 图文消息列表
     * @return mixed
     */
    public function index()
    {
        $where = $this->request->getMore([
            ['page', 1],
            ['limit', 20],
            ['cate_name', '']
        ]);
        $list = $this->services->getAll($where);
        return $this->success($list);
    }

    /**
     * 图文详情
     * @param $id
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function read($id)
    {
        $info = $this->services->get($id);
        /** @var ArticleServices $services */
        $services = app()->make(ArticleServices::class);
        $new = $services->articlesList($info['new_id']);
        if ($new) $new = $new->toArray();
        $info['new'] = $new;
        return $this->success(compact('info'));
    }

    /**
     * 删除图文
     * @param $id
     * @return mixed
     */
    public function delete($id)
    {
        if (!$this->services->delete($id))
            return $this->fail('删除失败,请稍候再试!');
        else
            return $this->success('删除成功!');
    }

    /**
     * 新增或编辑保存
     * @return mixed
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function save()
    {
		$data = $this->request->postMore([
			['id', 0]
		]);
		$data['list'] = $this->request->param('list',[]);
        try {
            $id = [];
            $countList = count($data['list']);
            if (!$countList) return $this->fail('请添加图文');
            /** @var ArticleServices $services */
            $services = app()->make(ArticleServices::class);
            foreach ($data['list'] as $k => $v) {
                if ($v['title'] == '') return $this->fail('标题不能为空');
                if ($v['author'] == '') return $this->fail('作者不能为空');
                if ($v['content'] == '') return $this->fail('正文不能为空');
                if ($v['synopsis'] == '') return $this->fail('摘要不能为空');
                $v['status'] = 1;
                $v['add_time'] = time();
                if ($v['id']) {
                    $idC = $v['id'];
                    $services->save($v);
                    unset($v['id']);
                    $data['list'][$k]['id'] = $idC;
                    $id[] = $idC;
                } else {
                    $res = $services->save($v);
                    unset($v['id']);
                    $id[] = $res['id'];
                    $data['list'][$k]['id'] = $res['id'];
                }
            }
            $countId = count($id);
            if ($countId != $countList) {
                if ($data['id']) return $this->fail('修改失败');
                else return $this->fail('添加失败');
            } else {
                $newsCategory['cate_name'] = $data['list'][0]['title'];
                $newsCategory['new_id'] = implode(',', $id);
                $newsCategory['sort'] = 0;
                $newsCategory['add_time'] = time();
                $newsCategory['status'] = 1;
                if ($data['id']) {
                    $this->services->update($data['id'], $newsCategory, 'id');
                    return $this->success('修改成功');
                } else {
                    $this->services->save($newsCategory);
                    return $this->success('添加成功');
                }
            }
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }

    /**
     * 发送消息
     * @param int $id
     * @param string $wechat
     * $wechat  不为空  发消息  /  空 群发消息
     */
	public function push()
	{
		[$id, $uids, $all, $where] = $this->request->postMore([
			['id', 0],
			['user_ids', ''],
			['all', 0],
			['where', ""],
		], true);
		if (!$id) return $this->fail('参数错误');
		if (!$uids && $all == 0) return $this->fail('请选择发送用户');
		$uids = is_string($uids) ? explode(',', $uids) : $uids;
		/** @var UserBatchProcessServices $userServices */
		$userServices = app()->make(UserBatchProcessServices::class);
		$userServices->batchProcess(99, $uids, ['id' => $id], !!$all, (array)$where);
		return app('json')->success('已加入消息队列,请稍后查看');
	}

    /**
     * 发送消息图文列表
     * @return mixed
     */
    public function send_news()
    {
        $where = $this->request->getMore([
            ['cate_name', ''],
            ['page', 1],
            ['limit', 10]
        ]);
        return $this->success($this->services->list($where));
    }

}
