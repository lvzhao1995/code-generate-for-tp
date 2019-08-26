<?php

namespace Generate\Traits\Admin;

use think\db\Query;
use think\Model;

interface CurdInterface
{
    /**
     * 列表查询sql捕获
     * @param Query|Model $sql 当前查询sql语句
     * @return Query
     */
    public function indexQuery($sql);

    /**
     * 输出到列表视图的数据捕获
     * @param $data @desc当前输出到列表视图的数据
     * @return mixed
     */
    public function indexAssign($data);

    /**
     * 输出到新增视图的数据捕获
     * @param $data @desc当前输出到新增视图的数据
     * @return mixed
     */
    public function addAssign($data);

    /**
     * 新增数据插入数据库前数据捕获（注意：在数据验证之前）
     * @param $data
     * @return mixed
     */
    public function addData($data);

    /**
     * 成功添加数据后的数据捕获
     * @param mixed $pk 添加后的主键值，多主键传入数组
     * @param array $data 接受的参数，包含追加的
     * @return mixed
     */
    public function addEnd($pk, $data);

    /**
     * 输出到编辑视图的数据捕获
     * @param $data @desc当前输出到编辑视图的数据
     * @return mixed
     */
    public function editAssign($data);

    /**
     * 编辑数据插入数据库前数据捕获（注意：在数据验证之前）
     * @param $data
     * @return mixed
     */
    public function editData($data);

    /**
     * 成功编辑数据后的数据捕获
     * @param mixed $pk 编辑数据的主键值，多主键传入数组
     * @param array $data 接受的参数，包含追加的
     * @return mixed
     */
    public function editEnd($pk, $data);

    /**
     * 成功删除数据后的数据捕获
     * @param mixed $pk 要删除数据的主键值，多主键传入数组
     * @return mixed
     */
    public function deleteEnd($pk);

    /**
     * 分页数据捕获，用于追加数据
     * @param $item
     * @param $key
     * @return mixed
     */
    public function pageEach($item, $key);
}