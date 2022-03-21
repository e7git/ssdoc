<?php

// 获取配置
$config = require_once __DIR__ . '/../config.php';
if (empty($config['secret'])) {
    exit('密钥配置缺失');
}

// 校验参数
if (!$post = $_POST) {
    exit('empty POST');
}
if (empty($post['note'])) {
    exit('note is empty');
}
if (empty($post['module'])) {
    exit('module is empty');
}
if (empty($post['sign'])) {
    exit('sign is empty');
}

// 验证签名
if (empty($post['time']) || !is_numeric($post['time']) || abs(time() - $post['time']) > 6) {
    exit('invalid time ');
}
if ($post['sign'] !== md5(sprintf('%s-%s-%s', $post['time'], $config['secret'], $post['time']))) {
    exit('invalid sign');
}

// 获取目录
$dir = sprintf('%s/../md/', __DIR__);
if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
    exit('create dir fail');
}

// 写入文件
if (!file_put_contents(sprintf('%s%s.md', $dir, $post['module']), $post['note'])) {
    exit('write note fail');
}

exit('success');
