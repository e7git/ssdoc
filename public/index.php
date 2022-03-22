<?php

// 启动 Session
session_start();

// 获取配置
$config = require_once __DIR__ . '/../config.php';
if (empty($config['users']) || !is_array($config['users'])) {
    exit('用户配置缺失');
}
$application = $config['application'] ?? '';
$company = $config['company'] ?? '';
$year = date('Y');

// 获取session
$user = [];
$nickArea = '';
if (!empty($_SESSION['user']) && !empty($_SESSION['expire']) && is_numeric($_SESSION['expire']) && $_SESSION['expire'] > time()) {
    $user = json_decode($_SESSION['user'], true);
    $nickArea = '<span class="nickname">欢迎你，' . ($user['nick'] ?? '') . '<button onclick="window.location.href=\'/?exit=1\'" class="btn btn-danger btn-mini exit-login" type="button">退出登录</button></span>';
}

// 退出登录
if (!empty($_GET['exit'])) {
    session_destroy();
    header('Location:/');
    die;
}


// 页面
$foot = <<<EOF
                <div class="panel-footer foot">
                    Copyright © {$year} {$company}
                </div>
            </div>
        </div>
    </body>
</html>
        
EOF;

echo <<<EOF
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8" />
        <title>{$application}</title>
        <link href="css/highlight-11.5.0-rebecca.min.css" rel="stylesheet" />
        <link href="css/zui-1.10.0.css" rel="stylesheet" />
        <script src="js/jquery-2.0.0.min.js"></script>
        <script src="js/zui-1.10.0.min.js"></script>
        <script src="js/highlight-11.5.0.min.js"></script>
        <script src="js/highlight-11.5.0-languages-json.min.js"></script>
        <script src="js/marked-4.0.2.min.js"></script>
        <link href="css/index.css" rel="stylesheet" />
        <script src="js/index.js"></script>
    </head>
    <body>
        <div class="panel panel-primary no-border">
            <div class="panel-heading head"><span class="logo" onclick="window.location.href='/'">{$application}</span>{$nickArea}</div>
            <div class="panel-body padding-top">
EOF;

// 发起登录
$post = $_POST;
if (!empty($post['user']) && !empty($post['pass'])) {
    $user = $config['users'][$post['user']] ?? [];
    if (!!$user && $user['pass'] == $post['pass']) {
        $_SESSION['expire'] = time() + min(86400, max(600, intval($config['session_expire'] ?? 0)));
        $_SESSION['user'] = json_encode($user);
    } else {
        $_SESSION['error'] = '用户名或密码不正确';
    }
    header('Location:/');
    die;
}

// 未登录时
if (!$user) {
    if (!empty($session['error'])) {
        echo <<<EOF
<script>
    $(function () {
        new $.zui.Messager("提示消息：{$session['error']}", {type: "danger"}).show();
    });
</script>
EOF;
    }

    echo <<<EOF
<form action="/" method="post">
    <div class="login">
        <h2 class="login-title">{$application}</h2>
        <div class="input-group">
            <span class="input-group-addon">用户</span>
            <input type="text" class="form-control" placeholder="请输入用户名" name="user" />
        </div>
        <div class="input-group">
            <span class="input-group-addon">密码</span ><input type="password" class="form-control" placeholder="请输入密码" name="pass" />
        </div>
        <div>
            <button class="btn btn-block btn-primary" type="submit">
                登入系统
            </button>
        </div>
    </div>
</form>
EOF;
    echo $foot;
    die;
}

$module = $user['module'] ?? [];
$dir = sprintf('%s/../md/', __DIR__);

// 获取文档
if (!empty($_GET['module'])) {
    if ('*' !== ($module[0] ?? null) && !in_array($_GET['module'], $module)) {
        echo '当前模块无访问权限';
        die;
    }
    $file = sprintf('%s/%s.md', $dir, $_GET['module']);
    if (!is_file($file)) {
        echo '文件不存在';
        die;
    }
    $content = file_get_contents($file);
    echo <<<EOF
<div class="main">
    <div id="catalog"></div>
    <div id="content"></div>
    <div id="markdown"></div>
    <div id="hide">{$content}</div>
</div>
EOF;
    echo $foot;
    die;
}


// 获取列表
$files = [];
if (is_dir($dir)) {
    $filenames = scandir($dir);
    foreach ($filenames as $filename) {
        $arr = explode('.', $filename);
        $prefix = array_pop($arr);
        $filename = implode('.', $arr);
        if ('md' !== $prefix || ('*' !== ($module[0] ?? null) && !in_array($filename, $module))) {
            continue;
        }
        $files[$filename] = sprintf('%s/%s.md', $dir, $filename);
    }
}
if ($files) {
    echo '<ul class="list-group">';
    $i = 1;
    foreach ($files as $index => $file) {
        $handle = fopen($file, 'r');
        echo sprintf('<li class="list-group-item"><a href="/?module=%s">%d、 %s</a></li>', $index, $i, substr(fgets($handle), 2)), PHP_EOL;
        fclose($handle);
        ++$i;
    }
    echo '</ul>';
} else {
    echo '无访问权限';
}

echo $foot;
die;
