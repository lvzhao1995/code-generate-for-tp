<?php

namespace Generate\Common;

use ArrayAccess;
use Closure;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Excel
{
    /**
     * @param array $columns 表头
     * 可接受['key'=>'列名']格式，
     * 或['key'=>['width'=>80,'title'=>'列名','callback'=>function(Cell $cell,$row,$index){}]]格式，各项都可选
     * @param array $data 数据
     * @param string|Closure $fileName 文件名
     * @param string|Closure $title 表标题（第一行合并单元格显示）,可传入闭包，接收Cell对象
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public static function export(array $columns, array $data, $fileName, $title = '')
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $row = 1;
        $columnIndex = 1;

        //设置标题
        if (!empty($title)) {
            $sheet->mergeCellsByColumnAndRow(1, $row, count($columns), $row);
            if ($title instanceof Closure) {
                call_user_func_array($title, [$sheet->getCellByColumnAndRow(1, $row)]);
            } else {
                $sheet->setCellValueByColumnAndRow(1, $row, $title);
            }
            ++$row;
        }

        //设置列
        foreach ($columns as $columnName) {
            if (is_array($columnName) || $columnName instanceof ArrayAccess) {
                if (!empty($columnName['width'])) {
                    //列宽度
                    $sheet->getColumnDimensionByColumn($columnIndex)
                        ->setWidth($columnName['width']);
                }
                //列名
                $sheet->setCellValueByColumnAndRow(
                    $columnIndex++,
                    $row,
                    isset($columnName['title']) ? $columnName['title'] : ''
                );
            } else {
                $sheet->setCellValueByColumnAndRow($columnIndex++, $row, $columnName);
            }
        }

        //设置内容
        foreach ($data as $item) {
            ++$row;
            $columnIndex = 1;
            foreach ($columns as $columnKey => $columnSetting) {
                if (isset($columnSetting['callback']) && $columnSetting['callback'] instanceof Closure) {
                    $cell = $sheet->getCellByColumnAndRow($columnIndex, $row);
                    call_user_func_array($columnSetting['callback'], [$cell, $item, $columnIndex]);
                } else {
                    $sheet->setCellValueByColumnAndRow(
                        $columnIndex,
                        $row,
                        isset($item[$columnKey]) ? $item[$columnKey] : ''
                    );
                }
                ++$columnIndex;
            }
        }

        //触发下载
        if ($fileName instanceof Closure) {
            $file = call_user_func_array($fileName, []);
        } else {
            $file = $fileName;
        }
        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $file . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }
}
