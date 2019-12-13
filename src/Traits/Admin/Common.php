<?php

namespace Generate\Traits\Admin;

use Generate\Traits\JsonReturn;
use think\db\Query;
use think\Model;

trait Common
{
    use JsonReturn;

    /**
     * 列表查询sql捕获
     * @param Query|Model $sql
     * @return Query
     */
    public function indexQuery($sql)
    {
        return $sql;
    }

    /**
     * 分页数据捕获，用于追加数据
     * @param $item
     * @param $key
     * @return mixed
     */
    public function pageEach($item, $key)
    {
        return $item;
    }

    /**
     * 输出到列表视图的数据捕获
     * @param $data
     * @return mixed
     */
    public function indexAssign($data)
    {
        $data['lists'] = [
        ];
        return $data;
    }

    /**
     * 输出到新增视图的数据捕获
     * @param $data
     * @return mixed
     */
    public function addAssign($data)
    {
        $data['lists'] = [
        ];
        return $data;
    }

    /**
     * 新增数据插入数据库前数据捕获（注意：在数据验证之前）
     * @param $data
     * @return mixed
     */
    public function addData($data)
    {
        return $data;
    }

    /**
     * 输出到编辑视图的数据捕获
     * @param $data
     * @return mixed
     */
    public function editAssign($data)
    {
        return $data;
    }

    /**
     * 编辑数据插入数据库前数据捕获（注意：在数据验证之前）
     * @param $data
     * @return mixed
     */
    public function editData($data)
    {
        return $data;
    }

    /**
     * 成功添加数据后的数据捕获
     * 通过$this->returnFail($message);将错误信息返回到前端，并且回滚数据
     * @param int|array $pk 添加后的主键值，多主键传入数组
     * @param mixed $data 接受的参数，包含追加的
     * @return mixed|void
     */
    public function addEnd($pk, $data)
    {
    }

    /**
     * 成功编辑数据后的数据捕获
     * 通过$this->returnFail($message);将错误信息返回到前端，并且回滚数据
     * @param array|int $pk 编辑数据的主键值，多主键传入数组
     * @param mixed $data 接受的参数，包含追加的
     */
    public function editEnd($pk, $data)
    {
    }

    /**
     * 成功删除数据后的数据捕获
     * 通过$this->returnFail($message);将错误信息返回到前端，并且回滚数据
     * @param int|array $pk 要删除数据的主键值，多主键则传入数组
     * @param array|Model $data
     * @return mixed|void
     */
    public function deleteEnd($pk, $data)
    {
    }
}
