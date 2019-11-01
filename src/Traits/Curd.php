<?php

namespace Generate\Traits;

use Exception;
use think\db\exception\DbException;
use think\db\Query;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\facade\Db;
use think\Model;
use think\Request;
use think\response\Json;
use think\Validate;

/**
 * Trait curd
 * @property $countField
 * @property string $model
 * @property array $searchField
 * @property int $pageLimit
 * @property bool $cache
 * @property array $indexField
 * @property array $detailField
 * @property array $addField
 * @property array $editField
 * @property array $deleteField
 * @property string $validate
 * @property array $allow
 * @property string $with
 * @property string $order
 */
trait Curd
{
    use JsonReturn;

    /**
     * @param Request $request
     * @return Json|void
     * @throws DbException
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
                    $this->get($request);
                }
                break;
            case 'POST':
                if (in_array('post', $this->allow)) {
                    $this->post($request);
                }
                break;
            case 'PUT':
                if (in_array('put', $this->allow)) {
                    $this->put($request);
                }
                break;
            case 'DELETE':
                if (in_array('delete', $this->allow)) {
                    $this->delete($request);
                }
                break;
        }
    }

    /**
     * 列表和详情
     * @param Request $request
     * @throws DbException
     */
    protected function get(Request $request): void
    {
        /* @var Model $model */
        $model = new $this->model;

        $sql = $model->with($this->with);
        if ($this->cache) {
            $sql = $sql->cache(true, 0, $model->getName() . '_cache_data');
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

            $pageSize = $request->param('pageSize') ?: $this->pageLimit;

            /* @var Query $sql */
            $sql = $sql->field($this->indexField);
            $sql = $this->search($sql);

            $list = $this->indexQuery($sql)->order($this->order)->paginate($pageSize)->each(function ($item, $key) {
                return $this->pageEach($item, $key);
            });
            $res = $this->indexAssign($list);
        }
        $this->returnSuccess($res);
    }

    /**
     * 详情查询sql捕获
     * @param Query|Model $sql
     * @return Query
     */
    protected function detailQuery($sql)
    {
        return $sql;
    }

    /**
     * 输出到详情的数据捕获
     * @param $data
     * @return mixed
     */
    protected function detailAssign($data)
    {
        return $data;
    }

    /**
     * 条件查询
     * @param Query $sql
     * @return Query
     */
    protected function search($sql)
    {
        $params = \think\facade\Request::param();
        foreach ($this->searchField as $key => $value) {
            if (is_string($key) && isset($params[$key]) && $params[$key] !== '') {
                if (is_array($value)) {
                    switch ($value[1]) {
                        case 'time_start':
                            $sql->whereTime($value[0], '>=', $params[$key]);
                            break;
                        case 'time_end':
                            $sql->whereTime($value[0], '<=', $params[$key] . ' 23:59:59');
                            break;
                        case 'select':
                        case 'exact':
                            $sql->where($value[0], $params[$key]);
                            break;
                    }
                } else {
                    $sql->where($value, 'like', $params[$key]);
                }
            } elseif (isset($params[$value]) && $params[$value] !== '') {
                $sql->where($value, 'like', $params[$value]);
            }
        }
        return $sql;
    }

    /**
     * 列表查询sql捕获
     * @param Query|Model $sql
     * @return Query
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
     * 输出到列表的数据捕获
     * @param $data
     * @return mixed
     */
    protected function indexAssign($data)
    {
        return $data;
    }

    /**
     * 新增数据页
     * @param Request $request
     */
    protected function post(Request $request): void
    {
        $params = $request->only($this->addField);
        $addData = $this->addData($params);
        $pk = '';
        $pkValue = '';

        try {
            //验证数据
            /* @var Validate $validate */
            $validate = new $this->validate;
            $validate->scene('add')->check($addData);

            Db::startTrans();
            /* @var Model $model */
            $model = new $this->model;
            $model->save($addData);
            $pk = $model->getPk();
            $pkValue = $this->getPkValue($model, $pk);
            $this->addEnd($pkValue, $addData);
        } catch (ValidateException $e) {
            Db::rollback();
            $this->returnFail($e->getError());
        } catch (HttpResponseException $e) {
            Db::rollback();
            throw $e;
        } catch (Exception $e) {
            Db::rollback();
            $this->returnFail($e->getMessage());
        }
        Db::commit();
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
     * @param Model $model
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
     * 通过$this->returnFail($message);将错误信息返回到前端，并且回滚数据
     * @param int|array $pk 添加后的主键值，多主键传入数组
     * @param mixed $data 接受的参数，包含追加的
     * @return mixed|void
     */
    protected function addEnd($pk, $data)
    {
    }

    /**
     * 编辑数据页
     * @param Request $request
     */
    protected function put(Request $request)
    {
        /* @var Model $model */
        $model = new $this->model;
        $pk = $model->getPk();
        $pkValue = $request->only($pk);

        $pkArr = $pk;
        if (is_string($pkArr)) {
            $pkArr = [$pkArr];
        }
        foreach ($pkArr as $key) {
            if (empty($pkValue[$key])) {
                $this->returnFail('参数有误，缺少' . $key);
            }
        }
        $params = $request->only($this->editField);
        $params = array_merge($params, $pkValue);
        $editData = $this->editData($params);
        try {
            /* @var Validate $validate */
            $validate = new $this->validate;
            $validate->scene('edit')->check($editData);
            Db::startTrans();
            //验证通过
            call_user_func([$this->model, 'update'], $editData, $pkValue);
            if (is_string($pk)) {
                $pkValue = $pkValue[$pk];
            }
            $this->editEnd($pkValue, $editData);
        } catch (ValidateException $e) {
            Db::rollback();
            $this->returnFail($e->getError());
        } catch (HttpResponseException $e) {
            Db::rollback();
            throw $e;
        } catch (Exception $e) {
            Db::rollback();
            $this->returnFail($e->getMessage());
        }
        Db::commit();
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
     * 通过$this->returnFail($message);将错误信息返回到前端，并且回滚数据
     * @param array|int $pk 编辑数据的主键值，多主键传入数组
     * @param mixed $data 接受的参数，包含追加的
     */
    protected function editEnd($pk, $data)
    {
    }

    /**
     * 删除
     * @param Request $request
     */
    protected function delete(Request $request)
    {
        /* @var Model $model */
        $model = new $this->model;
        $pk = $model->getPk();
        $pkValue = $request->only($pk);

        $pkArr = $pk;
        if (is_string($pkArr)) {
            $pkArr = [$pkArr];
        }
        foreach ($pkArr as $key) {
            if (empty($pkValue[$key])) {
                $this->returnFail('参数有误，缺少' . $key);
            }
        }

        $params = $request->only($this->deleteField);
        $params = array_merge($params, $pkValue);

        try {
            /* @var Validate $validate */
            $validate = new $this->validate;
            $validate->scene('delete')->check($params);

            Db::startTrans();
            $data = $model->find($pkValue);
            if (empty($data)) {
                $this->returnFail();
            }

            $data->delete();
            if (is_string($pk)) {
                $pkValue = $pkValue[$pk];
            }
            $this->deleteEnd($pkValue, $data);
        } catch (ValidateException $e) {
            Db::rollback();
            $this->returnFail($e->getError());
        } catch (HttpResponseException $e) {
            Db::rollback();
            throw $e;
        } catch (Exception $e) {
            Db::rollback();
            $this->returnFail($e->getMessage());
        }
        Db::commit();
        $this->returnSuccess();
    }

    /**
     * 成功删除数据后的数据捕获
     * 通过$this->returnFail($message);将错误信息返回到前端，并且回滚数据
     * @param int|array $pk 要删除数据的主键值，多主键则传入数组
     * @param Model|array $data 要删除的数据
     * @return mixed|void
     */
    protected function deleteEnd($pk, $data)
    {
    }
}
