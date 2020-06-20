<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2020 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: https://gitee.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/ThinkLibrary
// | github 代码仓库：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

namespace think\admin\service;

use think\admin\extend\HttpExtend;
use think\admin\Service;

/**
 * 楚才开放平台服务
 * Class OpenService
 * @package think\admin\service
 */
class OpenService extends Service
{
    /**
     * 接口账号
     * @var string
     */
    protected $appid;

    /**
     * 接口密钥
     * @var string
     */
    protected $appkey;

    /**
     * 消息服务初始化
     * @param string $appid
     * @param string $appkey
     * @return OpenService
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    protected function initialize($appid = '', $appkey = '')
    {
        $this->appid = $appid ?: sysconf('data.cuci_open_appid');
        $this->appkey = $appkey ?: sysconf('data.cuci_open_appkey');
        return $this;
    }

    /**
     * 接口数据请求
     * @param string $uri 接口地址
     * @param array $data 请求数据
     * @return array
     */
    public function doRequest(string $uri, array $data = [])
    {
        [$time, $nostr, $json] = [time(), uniqid(), json_encode($data)];
        $sign = md5($this->appid . '#' . $json . '#' . $time . '#' . $this->appkey . '#' . $nostr);
        $data = ['appid' => $this->appid, 'time' => $time, 'nostr' => $nostr, 'sign' => $sign, 'data' => $json];
        return json_decode(HttpExtend::post("https://open.cuci.cc/{$uri}", $data), true);
    }
}