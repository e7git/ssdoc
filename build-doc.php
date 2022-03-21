<?php

class BuildDoc
{

    // 成功返回的格式
    private static $succ = '{"code":0,"msg":"succ","data":%s}';
    // 路径配置
    private static $config = [
        'crm-pc' => [
            'title' => 'xxxx接口文档',
            'description' => 'xxxx接口文档',
            'test_api' => 'https://xxxx.xxxx.xxx/',
            'dir' => [
                'application/modules/Admin',
                'application/modules/Api',
                'application/modules/Core',
                'application/modules/Crm',
                'application/modules/Customer',
                'application/modules/Direct',
                'application/modules/User',
            ]
        ],
    ];
    private static $text = [];

    /**
     * 是否客户端运行
     * @return bool
     */
    public static function isCli(): bool
    {
        return preg_match('/cli/i', php_sapi_name()) ? true : false;
    }

    /**
     * 更新文档
     * @param type $params
     * @return string
     */
    public static function updateDoc($params): string
    {
        if (!self::isCli()) {
            return '非法执行';
        }

        $wait = [];
        if (!isset($params[1])) {
            $wait = self::$config;
        } else {
            unset($params[0]);
            foreach ($params as $key) {
                $wait[$key] = self::$config[$key] ?? null;
            }
        }

        if (!$wait) {
            return '没有待执行的文档配置';
        }

        $sourceDir = sprintf('%ssource', self::DOC_DIR);
        if (!is_dir($sourceDir)) {
            mkdir($sourceDir, 0777);
        }
        if (!$sourceDir = realpath($sourceDir)) {
            return 'source文件夹缺失';
        }
        foreach ($wait as $filename => $config) {
            self::run($sourceDir, $filename, $config);
            echo '[OK] ', $filename, PHP_EOL;
        }

        return '执行完毕！';
    }

    /**
     * 遍历PHP文件并执行回调
     * @param string $dir
     * @param callable $func
     */
    public static function walkController(string $dir, callable $func)
    {
        if (!is_dir($dir)) {
            return;
        }
        $dirs = opendir($dir);
        while (($filename = readdir($dirs)) !== false) {
            if ('.' === $filename || '..' === $filename) {
                continue;
            }
            $controller = sprintf('%s/%s', $dir, $filename);
            if (is_dir($controller)) {
                self::walkController($controller, $func);
            } elseif ('.php' === substr($controller, -4)) {
                $func($controller);
            }
        }
        closedir($dirs);
    }

    /**
     * 更新文档
     * @param string $sourceDir
     * @param string $filename
     * @param array $config
     * @return string
     */
    public static function run(string $sourceDir, string $filename, array $config): void
    {
        $docFilename = sprintf('%s/%s.md', $sourceDir, $filename);
        $title = $config['title'] ?? '未命名';
        file_put_contents($docFilename, sprintf("%s\n\n> 最后更新：%s\n\n> 测试地址：%s\n\n## 文档说明\n```\n%s\n```\n\n"
                        , $title
                        , date('Y-m-d H:i:s')
                        , $config['test_api'] ?? '未设置'
                        , $config['description'] ?? $config['title'] ?? '未命名'
        ));

        self::$text = [];
        foreach ($config['dir'] ?? [] as $dirPart) {
            self::walkController($dirPart, function (string $file) use ($docFilename, $config) {
                if (!is_file($file)) {
                    return;
                }
                $handle = fopen($file, 'r');
                $doc = [];
                $open = false;
                while (!feof($handle)) {
                    $line = str_replace(array("\r", "\n", "\r\n"), ' ', fgets($handle));
                    while (strpos($line, '  ') > -1) {
                        $line = str_replace('  ', ' ', $line);
                    }
                    $line = trim($line, ' ');

                    if (strpos($line, '/**') > -1) {
                        $open = true;
                        continue;
                    }
                    if (strpos($line, '*/') > -1) {
                        $open = false;
                        if (isset($doc['url'])) {
                            $group = $doc['group'] ?? '未分组';
                            if (!isset(self::$text[$group])) {
                                self::$text[$group] = ['index' => count(self::$text) + 1, 'children' => []];
                                self::$text[$group]['content'] = sprintf("## %d. %s\n\n", self::$text[$group]['index'], $group);
                            }

                            $child = sprintf("### %d.%d. %s\n\n%s\n\n```\n%s\n```\n\n**请求参数：**\n\n参数\t|必传\t|示例\t|描述\n:---\t|:---\t|:---\t|:---\n"
                                    , self::$text[$group]['index']
                                    , count(self::$text[$group]['children']) + 1
                                    , $doc['title']
                                    , isset($doc['description']) ? ('> ' . $doc['description']) : ''
                                    , $doc['url']
                            );

                            foreach ($doc['param'] ?? [] as $param) {
                                $child .= sprintf("%s\t|%s\t|%s\t|%s\n"
                                        , $param['field']
                                        , $param['optional'] ? 'Y' : 'N'
                                        , $param['example']
                                        , $param['description']
                                );
                            }

                            $child .= "\n**请求响应：**\n\n字段\t|类型\t|描述\n:---\t|:---\t|:---\n";

                            unset($json, $map);
                            $json = [];
                            $map = [];
                            foreach ($doc['resp'] ?? [] as $resp) {
                                $child .= sprintf("%s\t|%s\t|%s\n"
                                        , $resp['field']
                                        , $resp['type']
                                        , $resp['description']
                                );
                                $arr = explode('.', $resp['field']);
                                $last = array_pop($arr);
                                $parent = implode('.', $arr);
                                switch ($resp['type']) {
                                    case 'array':
                                        if ('' === $parent) {
                                            $json[$resp['field']] = [[]];
                                            $map[$resp['field']] = &$json[$resp['field']][0];
                                        } elseif (!isset($map[$parent])) {
                                            echo sprintf('数组层级错误！ url==%s', $file, $doc['url']), PHP_EOL;
                                        } else {
                                            $map[$parent][$last] = [[]];
                                            $map[$resp['field']] = &$map[$parent][$last][0];
                                        }
                                        break;
                                    case 'object':
                                        if ('' === $parent) {
                                            $json[$resp['field']] = [];
                                            $map[$resp['field']] = &$json[$resp['field']];
                                        } elseif (!isset($map[$parent])) {
                                            echo sprintf('数组层级错误！ url==%s', $file, $doc['url']), PHP_EOL;
                                        } else {
                                            $map[$parent][$last] = [];
                                            $map[$resp['field']] = &$map[$parent][$last];
                                        }
                                        break;
                                    default :
                                        if ('' === $parent) {
                                            $json[$resp['field']] = $resp['example'];
                                        } elseif (!isset($map[$parent])) {
                                            echo sprintf('数组层级错误！ url==%s', $file, $doc['url']), PHP_EOL;
                                        } else {
                                            $map[$parent][$last] = $resp['example'];
                                        }
                                        break;
                                }
                            }
                            $child .= sprintf("\n```json\n# %s 返回示例\n%s\n```\n\n"
                                    , $doc['type']
                                    , json_encode(json_decode(sprintf(self::$succ, ($json ? json_encode($json) : '{}'))), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
                            );

                            self::$text[$group]['children'][] = $child;
                        }
                        $doc = [];
                    }
                    if (!$open) {
                        continue;
                    }
                    $arr = explode(' ', $line);
                    if (('* @post' === substr($line, 0, 7) || '* @get' === substr($line, 0, 7)) && !empty($arr[2])) {
                        $doc['type'] = '* @post' === substr($line, 0, 7) ? 'POST' : 'GET';
                        $doc['url'] = $arr[2];
                        continue;
                    }

                    $example = [];
                    for ($i = 4; $i < 10; $i++) {
                        if (!isset($arr[$i])) {
                            break;
                        }
                        $example[] = $arr[$i];
                    }
                    $example = implode(',', $example);

                    if (!empty($doc['url']) && '* @param' === substr($line, 0, 8)) {
                        if ('' === $example) {
                            echo sprintf('参数格式错误！没有对应示例值, file=%s, url==%s', $file, $doc['url']), PHP_EOL;
                        }
                        $doc['param'][] = [
                            'optional' => '#' !== substr($example, -1),
                            'field' => $arr[2],
                            'example' => rtrim($example, '#'),
                            'description' => $arr[3],
                        ];
                        continue;
                    }
                    if (!empty($doc['url']) && '* @resp' === substr($line, 0, 7)) {
                        if ('' === $example) {
                            echo sprintf('参数格式错误！没有对应示例值, file=%s, url==%s', $file, $doc['url']), PHP_EOL;
                        }
                        $type = 'string';
                        if ('[]' === $example) {
                            $type = 'array';
                        } elseif ('{}' === $example) {
                            $type = 'object';
                        } else {
                            $exampleArr = explode('|', $example);
                            if (isset($exampleArr[1])) {
                                $type = $exampleArr[1];
                                $example = $exampleArr[0];
                            }
                        }
                        $doc['resp'][] = [
                            'field' => $arr[2],
                            'example' => $example,
                            'description' => $arr[3],
                            'type' => $type,
                        ];
                        continue;
                    }
                    if (isset($arr[1]) && '@group' === $arr[1]) {
                        $doc['group'] = $arr[2] ?? null;
                        continue;
                    }
                    if (!isset($doc['title']) && isset($arr[1])) {
                        $doc['title'] = $arr[1];
                        continue;
                    }
                    if (!isset($doc['description']) && isset($arr[1])) {
                        $doc['description'] = $arr[1];
                        continue;
                    }
                }
                fclose($handle);
            });
        }
        foreach (self::$text as $value) {
            file_put_contents($docFilename, $value['content'], FILE_APPEND);
            file_put_contents($docFilename, $value['children'], FILE_APPEND);
        }

        $html = file_get_contents(__DIR__ . '/tmpl.html');
        $html = str_replace('{title}', $title, $html);
        $html = str_replace('{content}', file_get_contents($docFilename), $html);

        file_put_contents(sprintf('%s/%s.html', self::DOC_DIR, $filename), $html);
    }

}

echo BuildDoc::updateDoc($argv), PHP_EOL;
