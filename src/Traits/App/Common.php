<?php

namespace Generate\Traits\App;

use Generate\Traits\JsonReturn;
use think\Cache;
use think\Request;
use think\response\Json;
use think\Session;

trait Common
{
    use JsonReturn;

    /**
     * @return Json
     */
    public function notLogin()
    {
        $data = [
            'code' => -1,
            'status' => 'fail',
            'msg' => '没有登录'
        ];
        return json($data);
    }

    /**
     * 获取当前登录用户的id
     * @return mixed
     */
    public function getAuthId()
    {
        return $this->getAuth()['id'];
    }

    /**
     * 获取当前登录用的数据
     * @return mixed
     */
    public function getAuth()
    {
        $token = Request::instance()->param('token');
        if ($token) {//token登录
            $res = Cache::get($token);
        } else {//web登录
            $res = Session::get('data', 'auth');
        }
        return $res;
    }
}