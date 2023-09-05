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

namespace app\dao\work;


use app\dao\BaseDao;
use app\model\work\WorkMemberOther;

/**
 * 企业微信成员其他详细属性
 * Class WorkMemberOtherDao
 * @package app\dao\work
 */
class WorkMemberOtherDao extends BaseDao
{

    /**
     * @return string
     */
    protected function setModel(): string
    {
        return WorkMemberOther::class;
    }
}
