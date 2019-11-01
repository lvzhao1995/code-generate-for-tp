<?php

namespace Generate;

use Generate\Command\Generate;
use think\db\Query;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Db;
use think\Route;

class Service extends \think\Service
{
    public function boot(Route $route)
    {
        $this->commands([
            Generate::class,
        ]);
        $rootPath = root_path();
        if (!empty($rootPath) && file_exists($rootPath . '/generate.lock')) {
            $route->rule('generate/showTables', '\\Generate\\Controller\\Generate@showTables');
            $route->rule('generate/getTableFieldData', '\\Generate\\Controller\\Generate@getTableFieldData');
            $route->rule('generate/getModelData', '\\Generate\\Controller\\Generate@getModelData');
            $route->rule('generate/generate', '\\Generate\\Controller\\Generate@generate');
            $route->rule('generate/generateRelation', '\\Generate\\Controller\\Generate@generateRelation');
            $route->rule('generate', '\\Generate\\Controller\\Generate@index');
        }

        //设置查询事件
        $callback = function (Query $query) {
            $table = $query->getTable();
            $prefix = Config::get('database.prefix');
            $name = preg_replace('/^' . $prefix . '/', '', $table);
            $modelName = parse_name($name, 1);
            Cache::tag($modelName . '_cache_data')->clear();
        };
        Db::event('after_insert', $callback);
        Db::event('after_update', $callback);
        Db::event('after_delete', $callback);
    }
}
