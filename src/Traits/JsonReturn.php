<?php


namespace Generate\Traits;

use think\Config;
use think\exception\HttpResponseException;

trait JsonReturn
{
    /**
     * 通用返回，程序内部判断应该返回的状态
     * @param $flag
     * @param $failMessage
     * @param array $res
     * @param null|integer $code
     */
    public function returnRes($flag, $failMessage, $res = [], $code = null)
    {
        if ($flag || is_array($flag)) {
            $this->returnSuccess($res, $code);
        } else {
            $this->returnFail($failMessage, $code);
        }
    }

    /**
     * @param array $res
     * @param null|integer $code
     */
    public function returnSuccess($res = [], $code = null)
    {
        if (is_null($code)) {
            $code = Config::get('curd.success_code');
        }
        $data = [
            'code' => $code,
            'status' => 'success',
            'data' => $res,
        ];
        $data['data'] = $res;
        throw new HttpResponseException(\json($data));
    }

    /**
     * @param string $failMessage
     * @param null|integer $code
     */
    public function returnFail($failMessage = '操作失败', $code = null)
    {
        if (is_null($code)) {
            $code = Config::get('curd.error_code');
        }
        $data = [
            'code' => $code,
            'status' => 'fail',
            'msg' => $failMessage
        ];
        throw new HttpResponseException(\json($data));
    }
}