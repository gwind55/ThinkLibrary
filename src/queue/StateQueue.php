<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2019 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://demo.thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/ThinkAdmin
// | github 代码仓库：https://github.com/zoujingli/ThinkAdmin
// +----------------------------------------------------------------------

namespace think\admin\queue;

use think\admin\Process;
use think\console\Command;
use think\console\Input;
use think\console\Output;

/**
 * 查看异步任务监听的主进程状态
 * Class StateQueue
 * @package think\admin\queue
 */
class StateQueue extends Command
{
    /**
     * 指令属性配置
     */
    protected function configure()
    {
        $this->setName('xtask:state')->setDescription('[控制]查看异步任务监听主进程状态');
    }

    /**
     * 指令执行状态
     * @param Input $input
     * @param Output $output
     */
    protected function execute(Input $input, Output $output)
    {
        $command = Process::think('xtask:listen');
        if (count($result = Process::query($command)) > 0) {
            $output->info("异步任务监听主进程{$result[0]['pid']}正在运行...");
        } else {
            $output->error("异步任务监听主进程没有运行哦!");
        }
    }
}