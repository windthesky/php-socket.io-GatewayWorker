<?php

$envPath = '.env';
if(!file_exists($envPath)) /** @noinspection PhpUnhandledExceptionInspection */
    throw new Exception("在根目录找不到".$envPath."文件");
global $_ENV;
$_ENV=parse_ini_file($envPath);
function env($name, $default=null) {
    global $_ENV;
    return $_ENV[$name] ?? $default;
}
//var_dump(env('UA'));
