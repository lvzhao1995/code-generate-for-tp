<?php

use think\Cache;
use think\Config;
use think\Console;
use think\db\Query;
use think\Loader;
use think\Route;

Console::addDefaultCommands([
    'Generate\\Command\\Generate',
]);

if (defined('ROOT_PATH') && file_exists(ROOT_PATH . '/generate.lock')) {
    Route::rule([
        'generate/showTables' => '\\Generate\\Controller\\Generate@showTables',
        'generate/getModelData' => '\\Generate\\Controller\\Generate@getModelData',
        'generate/getTableFieldData' => '\\Generate\\Controller\\Generate@getTableFieldData',
        'generate/generate' => '\\Generate\\Controller\\Generate@generate',
        'generate/generateRelation' => '\\Generate\\Controller\\Generate@generateRelation',
        'generate' => '\\Generate\\Controller\\Generate@index',
    ]);
}

$callback = function ($_, Query $query) {
    $table = $query->getTable();
    $prefix = Config::get('database.prefix');
    $name = preg_replace('/^' . $prefix . '/', '', $table);
    $modelName = Loader::parseName($name, 1);
    Cache::clear($modelName . '_cache_data');
};
Query::event('after_insert', $callback);
Query::event('after_update', $callback);
Query::event('after_delete', $callback);
