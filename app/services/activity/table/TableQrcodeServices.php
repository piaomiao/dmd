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

namespace app\services\activity\table;

use app\services\BaseServices;
use app\dao\activity\table\TableQrcodeDao;
use app\services\other\QrcodeServices;
use think\exception\ValidateException;

/**
 *
 * Class TableQrcodeServices
 * @package app\services\activity\table
 * @mixin TableQrcodeDao
 */
class TableQrcodeServices extends BaseServices
{

    /**
     * TableQrcodeServices constructor.
     * @param TableQrcodeDao $dao
     */
    public function __construct(TableQrcodeDao $dao)
    {
        $this->dao = $dao;
    }

    /**桌码列表
     * @param array $where
     * @param int $storeId
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function tableQrcodeyList(array $where, int $storeId)
    {
        [$page, $limit] = $this->getPageValue();
        $where['store_id'] = $storeId;
        $where['is_del'] = 0;
        $list = $this->dao->getList($where, $page, $limit, ['category']);
        foreach ($list as $key => &$item) {
            if (!$item['qrcode']) {
                $item['qrcode'] = $this->setQrcodey((int)$item['id'], (int)$item['store_id'], (int)$item['table_number']);
            }
            if (!$item['is_using']) {
                $item['qrcode'] = '';
            }
        }
        $count = $this->dao->count($where);
        return compact('list', 'count');
    }

    /**生成桌码
     * @param int $id
     * @param int $store_id
     * @param int $table_number
     * @return bool
     */
    public function setQrcodey(int $id, int $store_id, int $table_number)
    {
        /** @var QrcodeServices $qrcodeServices */
        $qrcodeServices = app()->make(QrcodeServices::class);
        $parame['store_id'] = $store_id;
        $parame['table_number'] = $table_number;
        $qrcode = $qrcodeServices->getRoutineQrcodePath($id, 0, 9, $parame);
        $this->dao->update($id, ['qrcode' => $qrcode]);
        return $qrcode;
    }

    /**获取桌码信息
     * @param int $id
     * @param array $with
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getQrcodeyInfo(int $id, array $with = [])
    {
        $Info = $this->dao->getTableCodeOne(['id' => $id, 'is_using' => 1], $with);
        if (!$Info) {
            throw new ValidateException('获取信息失败');
        }
        return $Info->toArray();
    }
}
