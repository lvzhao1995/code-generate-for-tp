<?php

use think\Console;
use think\Route;

Console::addDefaultCommands([
    "Generate\\Command\\Generate",
]);

if (file_exists(ROOT_PATH . '/generate.lock')) {
    Route::rule([
        'generate/showTables' => '\\Generate\\Controller\\Generate@showTables',
        'generate/getModelData' => '\\Generate\\Controller\\Generate@getModelData',
        'generate/getTableFieldData' => '\\Generate\\Controller\\Generate@getTableFieldData',
        'generate/generate' => '\\Generate\\Controller\\Generate@generate',
        'generate/generateRelation' => '\\Generate\\Controller\\Generate@generateRelation',
        'generate' => '\\Generate\\Controller\\Generate@index',
    ]);
}