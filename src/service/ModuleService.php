<?php
declare (strict_types=1);
namespace think\admin\service;

use think\admin\extend\HttpExtend;
use think\admin\extend\Parsedown;
use think\admin\Library;
use think\admin\Service;
use think\admin\service\NodeService;

/**
 * 系统模块管理
 * Class ModuleService
 * @package think\admin\service
 */
class ModuleService extends Service
{
    /**
     * 代码根目录
     * @var string
     */
    protected $root;

    /**
     * 官方应用地址
     * @var string
     */
    protected $server;

    /**
     * 官方应用版本
     * @var string
     */
    protected $version;

    /**
     * 模块服务初始化
     */
    public function initialize()
    {
        $this->root = $this->app->getRootPath();
        $this->version = trim(Library::VERSION, 'v');
        $this->server = 'http://342y6324w6.qicp.vip:8088';
    }

    //远程get请求
    private function get($uri, $query = [])
    {
        $query['domain'] = $this->app->request->host(true);
        return json_decode(HttpExtend::get($this->server . $uri, $query), true);
    }

    private function post($uri, $query = [])
    {
        $query['domain'] = $this->app->request->host(true);
        return json_decode(HttpExtend::post($this->server . $uri, $query), true);
    }

    /**
     * 获取服务端地址
     * @return string
     */
    public function getServer(): string
    {
        return $this->server;
    }

    /**
     * 获取版本号信息
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * 获取模块变更
     * @return array
     */
    public function change(): array
    {
        $online = $this->online();
        if (isset($online['code']) && $online['code'] > 0) {
            $locals = $this->getModules();

            foreach ($online['data'] as $key => &$item) if (isset($locals[$key])) {
                $item['local'] = $locals[$key];
                if ($item['local']['version'] < $item['version']) {
                    $item['type_code'] = 2;
                    $item['type_desc'] = '需要更新';
                } else {
                    $item['type_code'] = 3;
                    $item['type_desc'] = '无需更新';
                }
            } else {
                $item['type_code'] = 1;
                $item['type_desc'] = '未安装';
            }
        }
        return $online;
    }


    /**
     * 获取线上模块数据
     * @return array
     */
    public function online(): array
    {
        $data = $this->app->cache->get('moduleOnlineData', []);
        if (!empty($data)) {
            return [
                'code' => 1,
                'data' => $data
            ];
        }
        $result = $this->get('/index/update/version');
        if (isset($result['code'])) {
            if ($result['code'] > 0 && isset($result['data']) && is_array($result['data'])) {
                $this->app->cache->set('moduleOnlineData', $result['data'], 30);
            }
            return $result;
        } else {
            return [
                'code' => 0,
                'info' => '请求错误!',
                'data' => null
            ];
        }
    }

    /**
     * 安装或更新模块
     * @param string $name 模块名称
     * @return array
     */
    public function install(string $name): array
    {
        $this->app->cache->set('moduleOnlineData', []);
        $data = $this->generateDifference($name, [
            'database' => 'database/migrations',
            'src' => 'app/' . $name,
            'static' => 'public/static/' . $name
        ]);
        if (empty($data[0])) return [0, isset($data[1]) ? $data[1] : '没有需要安装的文件', []];
        $lines = [];
        foreach ($data[1] as $file) {
            [$state, $mode, $name] = $this->updateFileByDownload($file);
            if ($state) {
                if ($mode === 'add') $lines[] = "add {$name} successed";
                if ($mode === 'mod') $lines[] = "modify {$name} successed";
                if ($mode === 'del') $lines[] = "deleted {$name} successed";
            } else {
                if ($mode === 'add') $lines[] = "add {$name} failed";
                if ($mode === 'mod') $lines[] = "modify {$name} failed";
                if ($mode === 'del') $lines[] = "deleted {$name} failed";
            }
        }
        return [1, '模块安装成功', $lines];
    }

    /**
     * 获取系统模块信息
     * @param array $data
     * @return array
     */
    public function getModules(array $data = []): array
    {
        $service = NodeService::instance();
        foreach ($service->getModules() as $name) {
            $vars = $this->_getModuleVersion($name);
            if (is_array($vars) && isset($vars['version']) && preg_match('|^\d{4}\.\d{2}\.\d{2}\.\d{2}$|', $vars['version'])) {
                $data[$name] = $vars;
                /*$data[$name] = array_merge($vars, ['change' => []]);
                foreach ($service->scanDirectory($this->_getModuleInfoPath($name) . 'database' . DIRECTORY_SEPARATOR . 'change', [], 'md') as $file) {
                    $data[$name]['change'][pathinfo($file, PATHINFO_FILENAME)] = Parsedown::instance()->parse(file_get_contents($file));
                }
                */
            }
        }
        return $data;
    }

    /**
     * 获取文件信息列表
     * @param array $rules 文件规则
     * @param array $ignore 忽略规则
     * @param array $data 扫描结果列表
     * @return array
     * @deprecated 已废弃
     */
    public function getChanges(array $rules, array $ignore = [], array $data = []): array
    {
        // 扫描规则文件
        foreach ($rules as $key => $rule) {
            $path = $this->root . strtr(trim($rule, '\\/'), '\\', '/');
            $data = array_merge($data, $this->_scanLocalFileHashList($path));
        }
        // 清除忽略文件
        foreach ($data as $key => $item) foreach ($ignore as $ign) {
            if (stripos($item['name'], $ign) === 0) unset($data[$key]);
        }
        // 返回文件数据
        return ['rules' => $rules, 'ignore' => $ignore, 'list' => $data];
    }

    /**
     * 获取文件信息列表
     * @param string $name
     * @param array $rules 文件规则
     * @param array $ignore 忽略规则
     * @param array $data 扫描结果列表
     * @return array
     */
    public function getChangeList(string $name, array $rules, array $ignore = [], array $data = []): array
    {
        $list = [];
        // 扫描规则文件
        foreach ($rules as $key => $rule) {
            $path = $this->root . strtr(trim($rule, '\\/'), '\\', '/');
            $list[$key] = $this->_scanLocalFileHashList($path);
        }
        // 清除忽略文件
        foreach ($data as $key => $item) foreach ($ignore as $ign) {
            if (stripos($item['name'], $ign) === 0) unset($data[$key]);
        }
        // 返回文件数据
        return ['rules' => $rules, 'ignore' => $ignore, 'list' => $list];
    }

    /**
     * 检查文件是否可下载
     * @param string $name 文件名称
     * @return boolean
     */
    public function checkAllowDownload(string $name): bool
    {
        // 禁止目录级别上跳
        if (stripos($name, '..') !== false) {
            return false;
        }
        // 禁止下载数据库配置文件
        if (stripos(strtr($name, '\\', '/'), 'config/database') !== false) {
            return false;
        }
        // 检查允许下载的文件规则
        foreach ($this->_getAllowDownloadRule() as $rule) {
            if (stripos($name, $rule) === 0) return true;
        }
        // 不在允许下载的文件规则
        return false;
    }

    /**
     * 获取文件差异数据
     * @param $name //名称标识
     * @param array $rules 查询规则
     * @param array $ignore 忽略规则
     * @param array $result 差异数据
     * @return array
     */
    public function generateDifference($name, array $rules = [], array $ignore = [], array $result = []): array
    {
        $online = $this->post('/index/update/node', [
            'name' => $name,
            'rules' => json_encode($rules),
            'ignore' => json_encode($ignore),
        ]);
        if (empty($online['code'])) return [0, $online['info']];
        $change = $this->getChangeList($name, $online['data']['rules'] ?? [], $online['data']['ignore'] ?? []);

        foreach ($this->_grenerateDifferenceContrast($name, $online['data']['list'], $change['list']) as $file) {
            if (in_array($file['type'], ['add', 'del', 'mod'])) foreach ($rules as $rule) {
                if (stripos($file['local'], $rule) === 0) $result[] = $file;
            }
        }
        return [1, $result];
    }

    /**
     * 尝试下载并更新文件
     * @param array $file 文件信息
     * @return array
     */
    public function updateFileByDownload(array $file): array
    {
        if (in_array($file['type'], ['add', 'mod'])) {
            if ($this->_downloadUpdateFile($file['local'], encode($file['serve']))) {
                return [true, $file['type'], $file['local']];
            } else {
                return [false, $file['type'], $file['local']];
            }
        } elseif (in_array($file['type'], ['del'])) {
            $real = $this->root . $file['local'];
            if (is_file($real) && unlink($real)) {
                $this->_removeEmptyDirectory(dirname($real));
                return [true, $file['type'], $file['local']];
            } else {
                return [false, $file['type'], $file['local']];
            }
        }
    }

    /**
     * 获取允许下载的规则
     * @return array
     */
    private function _getAllowDownloadRule(): array
    {
        $data = $this->app->cache->get('moduleAllowDownloadRule', []);
        if (is_array($data) && count($data) > 0) return $data;
        $data = ['think', 'config', 'public/static', 'public/router.php', 'public/index.php', 'database/migrations', 'app'];
        foreach (array_keys($this->getModules()) as $name) $data[] = 'app/' . $name;
        $this->app->cache->set('moduleAllowDownloadRule', $data, 30);
        return $data;
    }

    /**
     * 获取模块版本信息
     * @param string $identifier
     * @return bool|array|null
     */
    private function _getModuleVersion(string $identifier)
    {
        $_manifest = $this->getManifest($identifier);
        if (!empty($_manifest) && isset($_manifest['application']['identifier']) && isset($_manifest['application']['version'])) {
            return [
                'name' => $_manifest['application']['name'],
                'identifier' => $_manifest['application']['identifier'],
                'version' => $_manifest['application']['version'],
                'package' => isset($_manifest['application']['package']) ? $_manifest['application']['package'] : '',
                'author' => isset($_manifest['application']['author']) ? $_manifest['application']['author'] : '',
                'content' => isset($_manifest['application']['description']) ? $_manifest['application']['description'] : ''
            ];
        }
        return null;
    }

    public function getManifest(string $name)
    {
        $filename = $this->_getModuleInfoPath($name) . 'manifest.php';
        if (file_exists($filename) && is_file($filename) && is_readable($filename)) {
            return include($filename);
        }
        return null;
    }

    /**
     * 下载更新文件内容
     * @param string $local
     * @param string $encode
     * @return boolean|integer
     */
    private function _downloadUpdateFile(string $local, string $encode)
    {
        $result = $this->get('/index/update/get?encode=' . $encode);
        if (empty($result['code'])) return false;
        $filename = $this->root . $local;
        file_exists(dirname($filename)) || mkdir(dirname($filename), 0755, true);
        return file_put_contents($filename, base64_decode($result['data']['content']));
    }

    /**
     * 清理空目录
     * @param string $path
     */
    private function _removeEmptyDirectory(string $path)
    {
        if (is_dir($path) && count(scandir($path)) === 2 && rmdir($path)) {
            $this->_removeEmptyDirectory(dirname($path));
        }
    }

    /**
     * 获取模块信息路径
     * @param string $name 模块名称
     * @return string
     */
    private function _getModuleInfoPath(string $name): string
    {
        return $this->app->getBasePath() . $name . DIRECTORY_SEPARATOR;
    }

    /**
     * 根据线上线下生成操作数组
     * @param string $name
     * @param array $serve 线上文件数据
     * @param array $local 本地文件数据
     * @param array $diffy 计算结果数据
     * @return array
     */
    private function _grenerateDifferenceContrast(string $name, array $serve = [], array $local = [], array $diffy = []): array
    {
        $diffy = [];
        $local_path = [
            'src' => 'app/' . $name,
            'database' => 'database/migrations',
            'static' => 'public/static/' . $name
        ];
        foreach ($serve as $key => $val) {
            $_serve = array_combine(array_column($val, 'name'), $val);
            $_local = array_combine(array_column($local[$key], 'name'), $local[$key]);
            foreach ($_serve as $name => $_v) {
                if (isset($_local[$name])) {
                    $_l = $_local[$name];
                    if ($_v['hash'] !== $_l['hash']) {
                        $diffy[$name] = [
                            'type' => 'mod',
                            'local' => isset($_l['file']) ? $_l['file'] : null,
                            'serve' => isset($_v['file']) ? $_v['file'] : null
                        ];
                    }
                } else {
                    $diffy[$name] = [
                        'type' => 'add',
                        'local' => $local_path[$key] . $name,
                        'serve' => isset($_v['file']) ? $_v['file'] : null
                    ];
                }
            }
            foreach ($_local as $name => $_l) if (!isset($_serve[$name])) {
                $diffy[$name] = [
                    'type' => 'del',
                    'local' => isset($_l['file']) ? $_l['file'] : null,
                    'serve' => null
                ];
            }
        }

        ksort($diffy);
        return array_values($diffy);
    }

    /**
     * 获取目录文件列表
     * @param mixed $path 扫描目录
     * @param array $data 扫描结果
     * @return array
     */
    private function _scanLocalFileHashList(string $path, array $data = []): array
    {
        foreach (NodeService::instance()->scanDirectory($path, [], null) as $file) {
            if ($this->checkAllowDownload($_file = substr($file, strlen($this->root)))) {
                $data[] = [
                    'name' => substr($file, strlen($path)),
                    'hash' => md5(preg_replace('/\s+/', '', file_get_contents($file))),
                    'file' => $_file
                ];
            }
        }
        return $data;
    }
}