<?php

namespace Generate\Traits\Admin;

use Exception;
use Generate\Common\Excel;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\db\Query;
use think\exception\DbException;
use think\exception\HttpResponseException;
use think\Model;
use think\Request;
use think\Validate;

/**
 * Trait curd
 * @property $countField
 * @property $modelName
 * @property $searchField
 * @property $pageLimit
 * @property $orderField
 * @property bool $cache
 * @property array $indexField
 * @property array $addField
 * @property array $editField
 * @property array $add_rule
 * @property array $edit_rule
 * @method mixed assign($name, $value = '')
 * @method mixed fetch($template = '', $vars = [], $replace = [], $config = [])
 * @mixin Common
 */
trait Curd
{
    /**
     * 列表页
     * @throws DbException
     */
    public function index(Request $request)
    {
        $special = [];
        $onlyArr = [];
        foreach ($this->searchField as $k => $v) {
            if (is_array($v)) {
                $key = key($v);
                $val = $v[$key];
                $onlyArr[] = $key;
                $special[$key] = $val;
            } else {
                $onlyArr[] = $v;
            }
        }
        $relationSearch = '';
        $whereData = $this->search($request->only($onlyArr), $special, $relationSearch);
        $pageSize = $request->param('pageSize') ?: $this->pageLimit;

        if (!empty($relationSearch)) {
            $model = model($this->modelName)->$relationSearch()->hasWhere([], null);
        } else {
            $model = model($this->modelName);
        }
        $sql = $model->field($this->indexField);
        $this->setWhere($whereData, $sql);
        if ($this->cache) {
            $sql->cache(true, 0, $this->modelName . '_cache_data');
        }

        $list = $this->indexQuery($sql)->order($this->orderField)->paginate($pageSize)->each(
            function ($item, $key) {
                return $this->pageEach($item, $key);
            }
        );
        $this->returnSuccess($list);
    }

    /**
     * 条件查询
     * @param $params
     * @param $special
     * @param $relationSearch
     * @return array
     */
    public function search($params, $special, &$relationSearch)
    {
        $whereData = [];
        foreach ($params as $k => $v) {
            if ('' !== $v) {
                $data = isset($special[$k]) ? $special[$k] : $k;
                $type = '';
                if (is_array($data)) {
                    $field = $data[0];
                    $type = $data[1];
                    if ('relation' == $type && false !== strpos($field, '.')) {
                        $name = explode('.', $field, 2);
                        $name[0] = strtolower($name[0]);
                        $relationSearch = $name[0];
                    }
                } else {
                    $field = $k;
                }
                $whereData[$k] = [
                    'val'   => $v,
                    'field' => $field,
                    'type'  => $type,
                ];
            }
        }
        return $whereData;
    }

    /**
     * 设置搜索条件
     * @param array $whereData
     * @param Model|Query $model
     */
    protected function setWhere($whereData, $model)
    {
        foreach ($whereData as $k => $v) {
            if ('pageSize' != $k && 'RelationSearch' != $k) {
                $field = $v['field'] ?: $k;
                switch ($v['type']) {
                    case 'select':
                        $model->where($field, $v['val']);
                        break;
                    case 'time_start':
                        $model->whereTime($field, '>=', $v['val'] . ' 00:00:00');
                        break;
                    case 'time_end':
                        $model->whereTime($field, '<=', $v['val'] . ' 23:59:59');
                        break;
                    case 'date_range':
                    case 'time_range':
                        if (!is_array($v['val']) || 2 != sizeof($v['val']) || empty($v['val'][0])) {
                            break;
                        }
                        if ('date_range' == $v['type']) {
                            $v['val'][1] = strtotime($v['val'][1]) + 86399;
                        }
                        $model->whereTime($field, 'between', $v['val']);
                        break;
                    default:
                        $model->where($field, 'like', "%{$v['val']}%");
                        break;
                }
            }
        }
    }

    /**
     * 新增数据页
     */
    public function add(Request $request)
    {
        if ($request->isPost()) {
            $params = $request->only($this->addField);
            $addData = $this->addData($params);
            $validate = new Validate($this->add_rule);
            $result = $validate->check($addData);
            if (!$result) {//验证不通过
                $this->returnFail($validate->getError());
            }
            $pk = '';
            $pkValue = '';
            //验证通过
            Db::startTrans();
            try {
                $model = model($this->modelName);
                $model->allowField(true)->save($addData);
                $pk = $model->getPk();
                $pkValue = $this->getPkValue($model, $pk);
                $this->addEnd($pkValue, $addData);
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
        $this->returnSuccess($this->addAssign([]));
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
     * 编辑数据页
     */
    public function edit(Request $request)
    {
        $model = model($this->modelName);
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
        if ($request->isPost()) {
            $params = $request->only($this->editField);
            $params = array_merge($params, $pkValue);
            $editData = $this->editData($params);
            $validate = new Validate($this->edit_rule);
            $result = $validate->check($editData);
            if (!$result) {//验证不通过
                $this->returnFail($validate->getError());
            }
            //验证通过
            Db::startTrans();
            try {
                $model->allowField(true)->save($editData, $pkValue);
                if (is_string($pk)) {
                    $pkValue = $pkValue[$pk];
                }
                $this->editEnd($pkValue, $editData);
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
        $data = $model->find($pkValue);
        $res = [];
        foreach ($pkValue as $key => $value) {
            $res[$key] = $value;
        }
        $res['data'] = $data;
        $this->returnSuccess($this->editAssign($res));
    }

    /**
     * 删除
     */
    public function delete(Request $request)
    {
        $model = model($this->modelName);
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
        $data = $model->find($pkValue);
        if (empty($data)) {
            $this->returnFail();
        }
        Db::startTrans();
        try {
            if (is_string($pk)) {
                $pkValue = $pkValue[$pk];
            }
            $this->deleteEnd($pkValue, $data);
            $data->delete();
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
     * 数据导出为excel
     * @throws Exception
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function export(Request $request)
    {
        if (!property_exists($this, 'exportColumn')) {
            throw new Exception('请在当前控制器中配置exportColumn属性');
        }
        if (!property_exists($this, 'exportName')) {
            throw new Exception('请在当前控制器中配置exportName属性');
        }
        $special = [];
        $onlyArr = [];
        foreach ($this->searchField as $k => $v) {
            if (is_array($v)) {
                $key = key($v);
                $val = $v[$key];
                $onlyArr[] = $key;
                $special[$key] = $val;
            } else {
                $onlyArr[] = $v;
            }
        }
        $relationSearch = '';
        $whereData = $this->search($request->only($onlyArr), $special, $relationSearch);

        $model = model($this->modelName);
        $sql = $model->field($this->indexField);
        $this->setWhere($whereData, $sql);

        $list = $this->indexQuery($sql)->order($this->orderField)->select();

        foreach ($list as $index => $item) {
            $this->pageEach($item, $index);
        }
        $title = '';
        if (property_exists($this, 'exportTitle')) {
            $title = $this->exportTitle;
        }
        Excel::export($this->exportColumn, $list->toArray(), $this->exportName, $title);
    }
}
