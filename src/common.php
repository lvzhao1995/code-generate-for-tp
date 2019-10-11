<?php

use think\Console;
use think\facade\Route;

if (!class_exists('\\think\\Console')) {
    return;
}

Console::addDefaultCommands([
    'Generate\\Command\\Generate',
]);

if (defined('ROOT_PATH') && file_exists(ROOT_PATH . '/generate.lock')) {
    Route::rules([
        'generate/showTables' => '\\Generate\\Controller\\Generate@showTables',
        'generate/getModelData' => '\\Generate\\Controller\\Generate@getModelData',
        'generate/getTableFieldData' => '\\Generate\\Controller\\Generate@getTableFieldData',
        'generate/generate' => '\\Generate\\Controller\\Generate@generate',
        'generate/generateRelation' => '\\Generate\\Controller\\Generate@generateRelation',
        'generate' => '\\Generate\\Controller\\Generate@index',
    ]);
}
