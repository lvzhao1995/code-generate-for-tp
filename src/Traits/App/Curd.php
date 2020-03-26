<?php

namespace Generate\Traits\App;

use Exception;
use think\Db;
use think\db\Query;
use think\exception\DbException;
use think\exception\HttpResponseException;
use think\Model;
use think\Request;
use think\response\Json;

/**
 * Trait Curd
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
     * @return Json|void
     * @throws DbExceptions
     */
    public function index(Request $request)
    {
        /*
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
        $model = model($this->model);

        $sql = $model->with($this->with)->order($this->order);
        if ($this->cache) {
            $sql = $sql->cache(true, 0, $this->model . '_cache_data');
        }

        //获取主键值
        $pk = $model->getPk();
        $pkValue = $request->only($pk);
        if (is_string($pk)) {
            $pk = [$pk];
        }
        //根据主键信息决定是否返回详情
        $isDetail = true;
        foreach ($pk as $key) {
            if (empty($pkValue[$key])) {
                $isDetail = false;
                break;
            }
        }
        if ($isDetail) {
            //详情
            $res = $this->detailQuery($sql)->field($this->detailField)->find($pkValue);
            if (empty($res)) {
                $this->returnFail('数据不存在');
            }
            $res = $this->detailAssign($res);
        } else {
            //列表
            //获取分页参数
            $pageSize = $request->param('pageSize');
            if (empty($pageSize)) {
                $pageSize = $this->limit;
            }
            $res = $this->indexQuery($sql)->field($this->indexField)->paginate($pageSize)
                ->each(function ($item, $key) {
                    return $this->pageEach($item, $key);
                });
            $res = $this->indexAssign($res);
        }
        $this->returnSuccess($res);
    }

    /**
     * 详情查询捕获
     * @param Query|Model $sql 当前查询对象
     * @return Query|Model
     */
    protected function detailQuery($sql)
    {
        return $sql;
    }

    /**
     * 输出到详情的数据捕获
     * @param $data @desc当前输出到列表视图的数据
     * @return mixed
     */
    protected function detailAssign($data)
    {
        return $data;
    }

    /**
     * 列表查询sql捕获
     * @param Query|Model $sql 当前查询对象
     * @return Query|Model
     */
    protected function indexQuery($sql)
    {
        return $sql;
    }

    /**
     * 分页数据捕获，用于追加数据
     * @param $item
     * @param $key
     * @return mixed
     */
    protected function pageEach($item, $key)
    {
        return $item;
    }

    /**
     * 输出到列表视图的数据捕获
     * @param $data @desc当前输出到列表视图的数据
     * @return mixed
     */
    protected function indexAssign($data)
    {
        return $data;
    }

    /**
     * 增
     * @return Json|void
     */
    protected function post(Request $request)
    {
        $params = $request->only($this->addField);
        $params = $this->addData($params);
        $params_status = $this->validate($params, "{$this->model}.store");
        if (true !== $params_status) {
            // 验证失败 输出错误信息
            $this->returnFail($params_status);
        }
        $pk = '';
        $pkValue = '';
        Db::startTrans();
        try {
            $model = model($this->model);
            $model->allowField(true)->save($params);
            $pk = $model->getPk();
            $pkValue = $this->getPkValue($model, $pk);
            $this->addEnd($pkValue, $params);
            Db::commit();
        } catch (HttpResponseException $e) {
            Db::rollback();
            throw $e;
        } catch (Exception $e) {
            Db::rollback();
            $this->returnFail($e->getMessage());
        }
        if (!is_array($pkValue)) {
            $pkValue = [$pk => $pkValue];
        }
        $this->returnSuccess($pkValue);
    }

    /**
     * 新增数据插入数据库前数据捕获（注意：在数据验证之前）
     * @param $data
     * @return mixed
     */
    protected function addData($data)
    {
        return $data;
    }

    /**
     * 获取模型的主键值
     * @param mixed $pk
     * @return array|mixed
     */
    private function getPkValue(Model $model, $pk = null)
    {
        if (is_null($pk)) {
            $pk = $model->getPk();
        }
        if (is_array($pk)) {
            $pkValue = [];
            foreach ($pk as $key) {
                $pkValue[$key] = $model->{$key};
            }
        } else {
            $pkValue = $model->{$pk};
        }
        return $pkValue;
    }

    /**
     * 成功添加数据后的数据捕获
     * @param mixed $pk 添加后的主键值，多主键传入数组
     * @param array $data 接受的参数，包含追加的
     */
    protected function addEnd($pk, $data)
    {
    }

    /**
     * 改
     * @return Json|void
     */
    protected function put(Request $request)
    {
        $model = model($this->model);
        //获取主键
        $pk = $model->getPk();
        $pkValue = $request->only($pk);
        $pkArr = $pk;
        if (is_string($pkArr)) {
            $pkArr = [$pkArr];
        }
        foreach ($pkArr as $key) {
            if (empty($pkValue[$key])) {
                //判断主键是否完整
                $this->returnFail('参数有误，缺少' . $key);
            }
        }
        $params = $request->only($this->editField);
        $params = array_merge($params, $pkValue);
        $params = $this->editData($params);
        $params_status = $this->validate($params, "{$this->model}.update");
        if (true !== $params_status) {
            // 验证失败 输出错误信息
            $this->returnFail($params_status);
        }
        Db::startTrans();
        try {
            $model->allowField(true)->save($params, $pkValue);
            if (is_string($pk)) {
                $pkValue = $pkValue[$pk];
            }
            $this->editEnd($pkValue, $params);
            Db::commit();
        } catch (HttpResponseException $e) {
            Db::rollback();
            throw $e;
        } catch (Exception $e) {
            Db::rollback();
            $this->returnFail($e->getMessage());
        }
        $this->returnSuccess();
    }

    /**
     * 编辑数据插入数据库前数据捕获（注意：在数据验证之前）
     * @param $data
     * @return mixed
     */
    protected function editData($data)
    {
        return $data;
    }

    /**
     * 成功编辑数据后的数据捕获
     * @param mixed $pk 编辑数据的主键值，多主键传入数组
     * @param array $data 接受的参数，包含追加的
     */
    protected function editEnd($pk, $data)
    {
    }

    /**
     * 删
     * @return Json|void
     */
    protected function delete(Request $request)
    {
        $model = model($this->model);
        //获取主键
        $pk = $model->getPk();
        $pkValue = $request->only($pk);
        $pkArr = $pk;
        if (is_string($pkArr)) {
            $pkArr = [$pkArr];
        }
        foreach ($pkArr as $key) {
            if (empty($pkValue[$key])) {
                //判断主键是否完整
                $this->returnFail('参数有误，缺少' . $key);
            }
        }

        $params = $request->param();
        $params_status = $this->validate($params, "{$this->model}.delete");
        if (true !== $params_status) {
            // 验证失败 输出错误信息
            $this->returnFail($params_status);
        }
        $data = model($this->model)->get($pkValue);
        if (empty($data)) {
            $this->returnFail('数据不存在');
        }
        Db::startTrans();
        try {
            if (is_string($pk)) {
                $pkValue = $pkValue[$pk];
            }
            $this->deleteEnd($pkValue, $data);
            $data->delete();
            Db::commit();
        } catch (HttpResponseException $e) {
            Db::rollback();
            throw $e;
        } catch (Exception $e) {
            Db::rollback();
            $this->returnFail($e->getMessage());
        }
        $this->returnSuccess();
    }

    /**
     * 成功删除数据后的数据捕获
     * @param mixed $pk 要删除数据的主键值，多主键传入数组
     * @param mixed $data 被删除的数据
     */
    protected function deleteEnd($pk, $data)
    {
    }
}
