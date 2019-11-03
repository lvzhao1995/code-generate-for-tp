<?php

return [
    'success_code'    => 1, //成功返回的code值
    'error_code'      => 0, //失败返回的code值
    'base_controller' => 'Right', //控制器基类，为空则不继承任何类
    'sign_controller' => 'SignIn', //带登录验证的控制器基类,为空则使用base_controller的值
    'model_path'      => '', //模型相对路径，从base_path开始，为空则使用默认（多应用为common/model，但应用为model）
    'index_template'  => '', //列表页模板，为空则使用默认
    'add_template'    => '', //添加页模板，为空则使用默认
    'edit_template'   => '', //修改页模板，为空则使用默认
    /*
     * form表单字段模板，指定使用以下占位符
     * {{name}}{{label}}{{value}}{{attr}}
     */
    'form' => [
        'text'        => '<FormItem label="{{label}}"><Input v-model="formData.{{name}}" /></FormItem>',
        'number'      => '<FormItem label="{{label}}"><Input v-model="formData.{{name}}" type="number" /></FormItem>',
        'select'      => '<FormItem label="{{label}}"><Select v-model="formData.{{name}}"><Option v-for="item in {{name}}List" :value="item.value" :key="item.value">{{ item.label }}</Option></Select></FormItem>',
        'uploadImage' => '<FormItem label="{{label}}"><singleImage :uploadAction="path+\'/admin/tool/uploadImage\'" v-model="formData.{{name}}" :maxFileCounts="3"/></FormItem>',
        'ueditor'     => '<FormItem label="{{label}}"><editor ref="editor" v-model="formData.{{name}}" :uploadImgServer="path+\'/admin/tool/editorUpload\'" /></FormItem>',
        'date'        => '<FormItem label="{{label}}"><DatePicker v-model="formData.{{name}}" type="date"></DatePicker></FormItem>',
        'datetime'    => '<FormItem label="{{label}}"><DatePicker v-model="formData.{{name}}" type="datetime"></DatePicker></FormItem>',
        'textarea'    => '<FormItem label="{{label}}"><Input v-model="formData.{{name}}" type="textarea"/></FormItem>',
    ],
    /*
     * 搜索字段模板，指定使用以下占位符
     * {{name}}{{label}}{{value}}
     */
    'search' => [
        'text'     => '<FormItem label="{{label}}"><Input v-model="searchData.{{name}}" /></FormItem>',
        'number'   => '<FormItem label="{{label}}"><Input v-model="searchData.{{name}}" type="number" /></FormItem>',
        'select'   => '<FormItem label="{{label}}"><Select v-model="searchData.{{name}}" clearable><Option v-for="item in {{name}}List" :value="item.value" :key="item.value">{{ item.label }}</Option></Select></FormItem>',
        'date'     => '<FormItem label="{{label}}"><DatePicker v-model="searchData.{{name}}" type="daterange"></DatePicker></FormItem>',
        'datetime' => '<FormItem label="{{label}}"><DatePicker v-model="searchData.{{name}}" type="datetimerange"></DatePicker></FormItem>',
        'textarea' => '<FormItem label="{{label}}"><Input v-model="searchData.{{name}}" /></FormItem>',
    ],
];
