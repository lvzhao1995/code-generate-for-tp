<?php

namespace Generate\Traits\Model;

use think\Model;

/**
 * @mixin Model
 */
trait Cache
{
    protected function initialize()
    {
        parent::initialize();
        $event_arr = ['afterWrite', 'afterDelete'];
        foreach ($event_arr as $k => $v) {
            self::{$v}(function () {
                \think\Cache::clear($this->name . '_cache_data');
            });
        }
    }
}