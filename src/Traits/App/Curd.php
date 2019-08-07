<?php

namespace Generate\Traits\App;

use think\exception\DbException;
use think\Request;
use think\response\Json;

/**
 * Trait Curd
 * @package Generate\Traits\App
 * @property string $model
 * @property string $with
 * @property string $cache
 * @property string $order
 * @property array $allow
 * @property array $indexField
 * @property array $detailField
 * @property array $addField
 * @property array $editField
 * @property int|null $limit
 * @method array|string|true validate($data, $validate, $message = [], $batch = false, $callback = null)
 * @mixin Common
 */
trait Curd
{
    /**
     * @param Request $request
     * @return Json|void
     * @throws DbException
     */
    public function index(Request $request)
    {
        /**
         * 遵循RESTful API
         * get 查
         * post 增
         * put 改
         * delete 删
         */
        switch ($request->method()) {
            case 'GET':
                if (in_array('get', $this->allow)) {
                    return $this->get($request);
                }
                break;
            case 'POST':
                if (in_array('post', $this->allow)) {
                    return $this->post($request);
                }
                break;
            case 'PUT':
                if (in_array('put', $this->allow)) {
                    return $this->put($request);
                }
                break;
            case 'DELETE':
                if (in_array('delete', $this->allow)) {
                    return $this->delete($request);
                }
                break;
        }
    }

    /**
     * 查
     * @param Request $request
     * @return Json|void
     * @throws DbException
     */
    protected function get($request)
    {
        if ($request->isGet()) {
            $id = $request->param('id');
            $pageSize = $request->param('pageSize');
            if (empty($pageSize)) {
                $pageSize = $this->limit;
            }
            $sql = model($this->model)->with($this->with)->order($this->order);
            if ($this->cache) {
                $sql = $sql->cache(true, 0, $this->model . '_cache_data');
            }
            if ($id) {
                $res = $sql->field($this->detailField)->find($id);
                if (empty($res)) {
                    $this->returnFail('数据不存在');
                }
            } else {
                $res = $sql->field($this->indexField)->paginate($pageSize);
            }
            $this->returnSuccess($res);
        }
    }

    /**
     * 增
     * @param Request $request
     * @return Json|void
     */
    protected function post(Request $request)
    {
        if ($request->isPost()) {
            $params = $request->only($this->addField);
            $params_status = $this->validate($params, "{$this->model}.store");
            if (true !== $params_status) {
                // 验证失败 输出错误信息
                $this->returnFail($params_status);
            }
            $model = model($this->model);
            $res = $model->allowField(true)->save($params);
            $data = [];
            $pk = $model->getPk();
            if ($res && !is_null($pk)) {
                if (is_array($pk)) {
                    foreach ($pk as $v) {
                        $data[$v] = $model->{$v};
                    }
                } else {
                    $data[$pk] = $model->{$pk};
                }
            }
            $this->returnRes($res, '创建失败', $data);
        }
    }

    /**
     * 改
     * @param Request $request
     * @return Json|void
     */
    protected function put(Request $request)
    {
        if ($request->isPut()) {
            $id = $request->param('id');
            if (!$id) {
                $this->returnFail('参数有误，缺少id');
            }
            $params = $request->only($this->editField);
            $params['id'] = $id;
            $params_status = $this->validate($params, "{$this->model}.update");
            if (true !== $params_status) {
                // 验证失败 输出错误信息
                $this->returnFail($params_status);
            }
            $res = model($this->model)->allowField(true)->save($params, ['id' => $params['id']]);
            $this->returnRes($res, '编辑失败');
        }
    }

    /**
     * 删
     * @param Request $request
     * @return Json|void
     * @throws DbException
     */
    protected function delete(Request $request)
    {
        if ($request->isDelete()) {
            $params = $request->param();
            $params_status = $this->validate($params, "{$this->model}.delete");
            if (true !== $params_status) {
                // 验证失败 输出错误信息
                $this->returnFail($params_status);
            }
            $data = model($this->model)->get($params['id']);
            if (empty($data)) {
                $this->returnFail('数据不存在');
            }
            $res = $data->delete();
            $this->returnRes($res, '删除失败');
        }
    }
}