<?php

namespace Generate\Controller;


use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use think\Config;
use think\Controller;
use think\Db;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\Loader;
use think\Request;

class Generate extends Controller
{
    protected $config = [];

    public function _initialize()
    {
        parent::_initialize();
        if (!Config::get('app_debug')) {
            throw new HttpException(404, 'module not exists:Generate');
        }
        Config::set('default_return_type', 'json');
        if (file_exists(ROOT_PATH . '/env.php')) {
            $this->config = include_once ROOT_PATH . '/env.php';
        }
    }

    public function index()
    {
        return view(__DIR__ . '/index.html');
    }

    /**
     * 展示所有的表
     */
    public function showTables()
    {
        $database = Config::get('database.database');
        $prefix = Config::get('database.prefix');
        $data = Db::query('show tables');
        $res = [];
        if (!empty($data)) {
            foreach ($data as $k => $v) {
                $res[$k]['value'] = str_replace($prefix, '', $v['Tables_in_' . $database]);
                $res[$k]['label'] = str_replace($prefix, '', $v['Tables_in_' . $database]);
            }
        }
        $this->res($res, '没有数据表，请添加数据表后刷新重试');
    }

    /**
     * 统一返回
     * @param $data
     * @param string $errorTips
     */
    private function res($data, $errorTips = '')
    {
        if (!$data || empty($data)) {
            $res = [
                'code' => 0,
                'msg' => $errorTips ?: '空数据'
            ];
        } else {
            $res = [
                'code' => 1,
                'data' => $data
            ];
        }
        throw new HttpResponseException(json($res));
    }

    /**
     * 获取对应数据表的字段数据
     * @param Request $request
     * @return false|string|void
     */
    public function getTableFieldData(Request $request)
    {
        if ($request->isPost()) {
            $table = $request->param('table');
            $is_model = $request->param('isModel');
            if ($is_model) {
                $model = model("app\common\model\\$table");
                $table = $model->getTable();
            }
            $prefix = Config::get('database.prefix');
            $res = [];
            $data = Db::query("SHOW FULL COLUMNS FROM `{$prefix}{$table}`");
            if (!empty($data)) {
                foreach ($data as $k => $v) {
                    $res[$k]['name'] = $v['Field']; //字段名
                    $res[$k]['comment'] = $v['Comment']; //注释
                    $res[$k]['type'] = $v['Type']; //类型
                    $res[$k]['label'] = ''; //名称
                    $res[$k]['curd'] = []; //操作
                    $res[$k]['business'] = ''; //业务类型
                    $res[$k]['search'] = false; //检索
                    $res[$k]['require'] = $v['Null'] == 'NO';//必填
                    $res[$k]['length'] = preg_replace('/\D/s', '', $v['Type']);//字段长度，不严谨
                }
            }
            $this->res($res, '数据表中未定义字段，请添加后刷新重试');
        }
    }

    /**
     * 获取模型
     */
    public function getModelData()
    {
        $model_path = ROOT_PATH . 'application\common\model\*.php';
        $res = [];
        foreach (glob($model_path) as $k => $v) {
            $val = explode('.php', explode('\model\\', $v)[1])[0];
            $arr = [
                'value' => $val,
                'label' => $val,
                'children' => [],
                'loading' => false
            ];
            $res[] = $arr;
        }
        $this->res($res, '没有模型');
    }

    /**
     * 生成
     * @param Request $request
     * @throws GuzzleException
     */
    public function generate(Request $request)
    {
        if ($request->isPost()) {
            $data = $request->post('data/a', []);
            $tableName = $request->post('tableName');
            $showName = $request->post('showName', '');
            if (!$tableName || !$data || !$data['selectVal']) {
                $this->error('参数错误');
            }

            $controllerName = $request->post('controllerName');
            if (empty($controllerName)) {
                $controllerName = $tableName;
            }
            $controllerName = Loader::parseName($controllerName, 1);
            $modelName = Loader::parseName($tableName, 1);

            $responseMessage = '';

            $response = [];

            //判断前台 or 后台
            if ($data['selectVal'] == '前台') {
                //前台
                if (in_array('控制器', $data['fruit'])) {
                    //生成控制器
                    $controllerRes = $this->createAppController($data, $controllerName, $modelName);
                    $responseMessage .= ($controllerRes === true ? "控制器生成成功\n" : "$controllerRes\n") . '</br>';
                    //生成验证器
                    $pk = Db::name($modelName)->getPk();
                    $validateRes = $this->createAppValidate($data, $controllerName, $pk);
                    $responseMessage .= ($validateRes === true ? "验证器生成成功，请根据业务逻辑进行配置\n" : "$validateRes\n") . '</br>';
                    $documentRes = $this->createDocument($data, $controllerName, $showName, $tableName);
                    $responseMessage .= '文档生成结果：' . $documentRes . "</br>";
                }
            } elseif ($data['selectVal'] == '后台') {
                //后台
                //生成控制器
                if (in_array('控制器', $data['fruit'])) {
                    //生成控制器
                    $controllerRes = $this->createAdminController($data, $controllerName, $modelName);
                    $responseMessage .= ($controllerRes === true ? "控制器生成成功\n" : "$controllerRes\n") . '</br>';
                }
                //生成模板
                if (in_array('视图', $data['fruit'])) {
                    $indexRes = $this->createIndexView($data, $controllerName);
                    $responseMessage .= ($indexRes === true ? "index视图生成成功\n" : "$indexRes\n") . '</br>';
                    $addRes = $this->createAddView($data, $controllerName);
                    $responseMessage .= ($addRes === true ? "add视图生成成功\n" : "$addRes\n") . '</br>';
                    $editRes = $this->createEditView($data, $controllerName);
                    $responseMessage .= ($editRes === true ? "edit视图生成成功\n" : "$editRes\n") . '</br>';
                    $dir = Loader::parseName($controllerName);
                    $this->createMeta($showName, $dir);
                    $response['router'] = '<p>{title: \'' . $showName . '\',to: \'/' . $dir . '\'}</p>';
                }
            } else {
                $this->error('参数错误');
            }
            //生成模型
            if (in_array('模型', $data['fruit'])) {
                $modelRes = $this->createModel($data, $modelName);
                $responseMessage .= ($modelRes === true ? "模型生成成功\n" : "$modelRes\n") . '</br>';
            }
            $response['message'] = $responseMessage;
            $this->success($response);
        }
    }

    /**
     * 生成前台控制器
     * @param $data
     * @param $modelName
     * @param $controllerName
     * @return bool|string
     */
    private function createAppController($data, $controllerName, $modelName)
    {
        $controllerPath = APP_PATH . "app/controller/";
        if (file_exists($controllerPath . "{$controllerName}.php")) {
            return '控制器已存在';
        }

        $baseController = Config::get('curd.front_base_controller');
        if (empty($baseController)) {
            $baseController = '\think\Controller';
        }
        $signController = Config::get('curd.front_sign_controller');
        if (empty($signController)) {
            $signController = $baseController;
        }
        if ($data['login'] == '否') {
            $extends = 'extends ' . $baseController;
        } else {
            $extends = 'extends ' . $signController;
        }

        $indexField = [];
        $editField = [];
        $addField = [];
        $orderField = [];
        $detailField = [];
        foreach ($data['pageData'] as $k => $v) {
            if (in_array('列表', $v['curd'])) {
                $indexField[] = "'{$v['name']}'";
            }
            if (in_array('详情', $v['curd'])) {
                $detailField[] = "'{$v['name']}'";
            }
            if (in_array('改', $v['curd'])) {
                $editField[] = "'{$v['name']}'";
            }
            if (in_array('增', $v['curd'])) {
                $addField[] = "'{$v['name']}'";
            }
            if (!empty($v['sort'])) {
                $orderField[] = "{$v['name']} {$v['sort']}";
            }
        }
        $indexField = implode(',', $indexField);
        $detailField = implode(',', $detailField);
        $editField = implode(',', $editField);
        $addField = implode(',', $addField);
        $orderField = implode(',', $orderField);

        $allow = [];
        foreach ($data['allow'] as $c => $v) {
            $allow[] = "'$v'";
        }
        $allow = implode(',', $allow);

        $code = <<<CODE
<?php
namespace app\app\controller;

use Generate\Traits\App\Common;
use Generate\Traits\App\Curd;

class {$controllerName} {$extends}
{
    /**
     * 增删改查封装在Curd内，如需修改复制到控制器即可
     */
    use Common,Curd;
    
    protected \$limit = null; //每页显示的数量
    protected \$model = '{$modelName}';
    protected \$validate = '{$controllerName}';
    protected \$allow = [{$allow}]; //允许的操作，必须为小写，可选值为get\post\put\delete 
    protected \$indexField = [{$indexField}];  //允许在列表页返回的字段名
    protected \$detailField = [{$detailField}];  //允许在详情页返回的字段名
    protected \$addField   = [{$addField}];    //增加时允许前端传入的字段名
    protected \$editField  = [{$editField}];   //修改时允许前端传入的字段名
    protected \$with = '';//关联关系
    protected \$cache = true;//是否开启查询缓存
    protected \$order = '{$orderField}'; //排序字段
}
CODE;
        $this->createPath($controllerPath);
        file_put_contents($controllerPath . "{$controllerName}.php", $code);
        return true;
    }

    /**
     * 创建目录
     * @param $path
     */
    private function createPath($path)
    {
        if (file_exists($path)) {
            return;
        }
        mkdir($path, 0777, true);
        return;
    }

    /**
     * 生成验证文件
     * @param $data
     * @param $controllerName
     * @param $pk
     * @return bool|string
     */
    private function createAppValidate($data, $controllerName, $pk)
    {
        $validatePath = APP_PATH . "app/validate/{$controllerName}.php";
        if (file_exists($validatePath)) {
            return '验证器已存在';
        }
        $rule = '';
        $scene = '';
        $deleteScene = '';
        foreach ($pk as $k) {
            $rule .= "        '{$k}' => 'require',\n";
            $scene .= "'{$k}',";
            $deleteScene .= "'{$k}',";
        }
        foreach ($data['pageData'] as $k => $v) {
            if ($v['require']) {
                $rule .= "        '{$v['name']}|{$v['label']}' => 'require',\n";
                $scene .= "'{$v['name']}',";
            }
        }
        $code = <<<CODE
<?php
namespace app\app\\validate;

use think\Validate;

class {$controllerName} extends Validate
{
    protected \$rule = [
        'id' => 'require'
        {$rule}
    ];

    protected \$message = [
        'id.require'  =>  'id不能为空',
    ];

    protected \$scene = [
        'delete' => [{$deleteScene}],//删
        'update' => [{$scene}],//改
        'store' => [{$scene}],//增
        'index' => [{$scene}],//查
    ];
}
CODE;
        $this->createPath(APP_PATH . "app/validate/");
        file_put_contents($validatePath, $code);
        return true;
    }

    /**
     * @param $data
     * @param $controllerName
     * @param $showName
     * @param $tableName
     * @return string
     * @throws GuzzleException
     */
    private function createDocument($data, $controllerName, $showName, $tableName)
    {
        if (empty($this->config['api_token']) || empty($this->config['api_uri'])) {
            return '请先完善配置';
        }
        $path = '/app/' . Loader::parseName($controllerName) . '/index';
        $detailPath = $path . '?id={id}';

        $paths = [];

        $index = [
            'type' => 'object',
            'description' => ''
        ];
        $detail = [
            'type' => 'object',
            'description' => ''
        ];
        $postParameters = [];
        $postData = [
            'type' => 'object',
            'description' => '插入的数据主键'
        ];
        $putParameters = [];
        $deleteParameters = [];
        $tablePk = Db::name($tableName)->getPk();
        $allowGet = in_array('get', $data['allow']);
        $allowPost = in_array('post', $data['allow']);
        $allowPut = in_array('put', $data['allow']);
        $allowDelete = in_array('delete', $data['allow']);
        $hasPk = [];

        foreach ($data['pageData'] as $k => $v) {
            if ($allowGet) {
                if (in_array('列表', $v['curd'])) {
                    $index['properties'][$v['name']] = [
                        'type' => 'string',
                        'description' => $v['label']
                    ];
                    if (in_array($v['autotype'], ['json', 'array', 'object', 'serialize'])) {
                        $index['properties'][$v['name']]['type'] = 'array';
                        $index['properties'][$v['name']]['items']['type'] = 'string';
                    }
                    $index['required'][] = $v['name'];
                }
                if (in_array('详情', $v['curd'])) {
                    $detail['properties'][$v['name']] = [
                        'type' => 'string',
                        'description' => $v['label']
                    ];
                    if (in_array($v['autotype'], ['json', 'array', 'object', 'serialize'])) {
                        $detail['properties'][$v['name']]['type'] = 'array';
                        $detail['properties'][$v['name']]['items']['type'] = 'string';
                    }
                    $detail['required'][] = $v['name'];
                }
            }
            if ($allowPut) {
                if (in_array('改', $v['curd'])) {
                    $putParameters[] = [
                        'name' => $v['name'] . (in_array($v['autotype'], ['json', 'array', 'object', 'serialize']) ? '[]' : ''),
                        'in' => 'formData',
                        'required' => $v['require'],
                        'description' => $v['label'],
                        'type' => 'string'
                    ];
                    if ((is_array($tablePk) && in_array($v['name'], $tablePk)) || $v['name'] == $tablePk) {
                        $hasPk[$v['name']] = true;
                    }
                }
            }
            if ($allowPost) {
                if (in_array('增', $v['curd'])) {
                    $postParameters[] = [
                        'name' => $v['name'] . (in_array($v['autotype'], ['json', 'array', 'object', 'serialize']) ? '[]' : ''),
                        'in' => 'formData',
                        'required' => $v['require'],
                        'description' => $v['label'],
                        'type' => 'string'
                    ];
                }
            }
        }
        if ($allowGet) {//列表、详情
            $paths[$path]['get'] = [
                'tags' => ['临时分类'],
                'summary' => $showName . '列表',
                'description' => '',
                'parameters' => [
                    [
                        'name' => 'page',
                        'in' => 'query',
                        'required' => false,
                        'description' => '页码',
                        'type' => 'string'
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'successful operation',
                        'schema' => [
                            '$schema' => 'http://json-schema.org/draft-04/schema#',
                            'type' => 'object',
                            'properties' => [
                                'code' => [
                                    'type' => 'number',
                                    'description' => '0--失败 1--成功'
                                ],
                                'status' => [
                                    'type' => 'string',
                                    'description' => '状态值'
                                ],
                                'data' => [
                                    'type' => 'object',
                                    'description' => '',
                                    'properties' => [
                                        "total" => [
                                            "type" => "number",
                                            "description" => "总条数"
                                        ],
                                        "per_page" => [
                                            "type" => "number",
                                            "description" => "每页条数"
                                        ],
                                        "current_page" => [
                                            "type" => "number",
                                            "description" => "当前页"
                                        ],
                                        "last_page" => [
                                            "type" => "number",
                                            "description" => "最大页"
                                        ],
                                        'data' => [
                                            'type' => 'array',
                                            'items' => $index
                                        ],
                                    ],
                                    "required" => ["total", "per_page", "current_page", "last_page", "data"]
                                ]
                            ],
                            "required" => ["code", "status", "data"]
                        ]
                    ]
                ]
            ];
            $paths[$detailPath]['get'] = [
                'tags' => ['临时分类'],
                'summary' => $showName . '详情',
                'description' => '请将路径中的{id}替换为要查看的数据id',
                'responses' => [
                    '200' => [
                        'description' => 'successful operation',
                        'schema' => [
                            '$schema' => 'http://json-schema.org/draft-04/schema#',
                            'type' => 'object',
                            'properties' => [
                                'code' => [
                                    'type' => 'number',
                                    'description' => '0--失败 1--成功'
                                ],
                                'status' => [
                                    'type' => 'string',
                                    'description' => '状态值'
                                ],
                                'data' => $detail
                            ],
                            'required' => ['code', 'status', 'data']
                        ]
                    ]
                ]
            ];
        }
        if ($allowPost) {
            if (!is_null($tablePk)) {
                if (is_array($tablePk)) {
                    foreach ($tablePk as $v) {
                        $postData['properties'][$v] = [
                            'type' => 'string',
                            'description' => ''
                        ];
                    }
                } else {
                    $postData['properties'][$tablePk] = [
                        'type' => 'string',
                        'description' => ''
                    ];
                }
            }
            $paths[$path]['post'] = [
                'tags' => ['临时分类'],
                'summary' => '添加' . $showName,
                'description' => '',
                "consumes" => [
                    "multipart/form-data"
                ],
                'parameters' => $postParameters,
                'responses' => [
                    '200' => [
                        'description' => 'successful operation',
                        'schema' => [
                            '$schema' => 'http://json-schema.org/draft-04/schema#',
                            'type' => 'object',
                            'properties' => [
                                'code' => [
                                    'type' => 'number',
                                    'description' => '0--失败 1--成功'
                                ],
                                'status' => [
                                    'type' => 'string',
                                    'description' => '状态值'
                                ],
                                'data' => $postData
                            ],
                            "required" => ["code", "status", 'data']
                        ]
                    ]
                ]
            ];
        }
        if ($allowPut) {
            if (!is_null($tablePk)) {
                if (is_array($tablePk)) {
                    foreach ($tablePk as $v) {
                        if (empty($hasPk[$v])) {
                            $putParameters[] = [
                                'name' => $v,
                                'in' => 'formData',
                                'required' => true,
                                'description' => '',
                                'type' => 'string'
                            ];
                        }
                    }
                } else {
                    if (empty($hasPk[$tablePk])) {
                        $putParameters[] = [
                            'name' => $tablePk,
                            'in' => 'formData',
                            'required' => true,
                            'description' => '',
                            'type' => 'string'
                        ];
                    }
                }
            }
            $paths[$path]['put'] = [
                'tags' => ['临时分类'],
                'summary' => '修改' . $showName,
                'description' => '',
                "consumes" => [
                    "multipart/form-data"
                ],
                'parameters' => $putParameters,
                'responses' => [
                    '200' => [
                        'description' => 'successful operation',
                        'schema' => [
                            '$schema' => 'http://json-schema.org/draft-04/schema#',
                            'type' => 'object',
                            'properties' => [
                                'code' => [
                                    'type' => 'number',
                                    'description' => '0--失败 1--成功'
                                ],
                                'status' => [
                                    'type' => 'string',
                                    'description' => '状态值'
                                ]
                            ],
                            "required" => ["code", "status"]
                        ]
                    ]
                ]
            ];
        }
        if ($allowDelete && !is_null($tablePk)) {
            if (is_array($tablePk)) {
                foreach ($tablePk as $v) {
                    if (empty($hasPk[$v])) {
                        $deleteParameters[] = [
                            'name' => $v,
                            'in' => 'query',
                            'required' => true,
                            'description' => '',
                            'type' => 'string'
                        ];
                    }
                }
            } else {
                if (empty($hasPk[$tablePk])) {
                    $deleteParameters[] = [
                        'name' => $tablePk,
                        'in' => 'query',
                        'required' => true,
                        'description' => '',
                        'type' => 'string'
                    ];
                }
            }
            $paths[$path]['delete'] = [
                'tags' => ['临时分类'],
                'summary' => '删除' . $showName,
                'description' => '',
                "consumes" => [
                    "multipart/form-data"
                ],
                'parameters' => $deleteParameters,
                'responses' => [
                    '200' => [
                        'description' => 'successful operation',
                        'schema' => [
                            '$schema' => 'http://json-schema.org/draft-04/schema#',
                            'type' => 'object',
                            'properties' => [
                                'code' => [
                                    'type' => 'number',
                                    'description' => '0--失败 1--成功'
                                ],
                                'status' => [
                                    'type' => 'string',
                                    'description' => '状态值'
                                ]
                            ],
                            "required" => ["code", "status"]
                        ]
                    ]
                ]
            ];
        }

        $json = [
            'swagger' => '2.0',
            'tags' => [
                [
                    'name' => '临时分类',
                    'description' => '公共分类',
                ]
            ],
            'paths' => $paths
        ];

        try {
            $client = new Client(['base_uri' => $this->config['api_uri']]);
            $response = $client->request('POST', '/api/open/import_data', [
                'form_params' => [
                    'json' => json_encode($json),
                    'type' => 'swagger',
                    'merge' => 'normal',
                    'token' => $this->config['api_token']
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'http_errors' => false
            ]);
            $code = $response->getStatusCode();
            if ($code == 200) {
                $body = json_decode((string)$response->getBody(), true);
                return $body['errmsg'];
            } else {
                return '请求出错';
            }
        } catch (Exception $e) {
            return '请求出错';
        }
    }

    /**
     * 生成后台控制器
     * @param $data
     * @param $controllerName
     * @param $modelName
     * @return bool|string
     */
    private function createAdminController($data, $controllerName, $modelName)
    {
        $controllerPath = APP_PATH . "admin/controller/";
        if (file_exists($controllerPath . "{$controllerName}.php")) {
            return '控制器已存在';
        }

        $baseController = Config::get('curd.back_base_controller');
        if (empty($baseController)) {
            $baseController = '\think\Controller';
        }
        $editRule = '';
        $addRule = '';
        $indexField = [];
        $editField = [];
        $addField = [];
        $searchField = [];
        $orderField = [];
        foreach ($data['pageData'] as $k => $v) {
            if (in_array('查', $v['curd'])) {
                $indexField[] = "'{$v['name']}'";
            }
            if (in_array('改', $v['curd'])) {
                $editField[] = "'{$v['name']}'";
                if ($v['require']) {
                    $editRule .= "        '{$v['name']}|{$v['label']}' => 'require',\n";
                }
            }
            if (in_array('增', $v['curd'])) {
                $addField[] = "'{$v['name']}'";
                if ($v['require']) {
                    $addRule .= "        '{$v['name']}|{$v['label']}' => 'require',\n";
                }
            }
            if ($v['search'] == true) {
                switch ($v['business']) {
                    case 'date':
                    case 'datetime':
                        $searchField[] = "['{$v['name']}Start'=>['{$v['name']}','time_start']]";
                        $searchField[] = "['{$v['name']}End'=>['{$v['name']}','time_end']]";
                        break;
                    default:
                        $searchField[] = "'{$v['name']}'";
                }
            }
            if (!empty($v['sort'])) {
                $orderField[] = "{$v['name']} {$v['sort']}";
            }
        }
        $indexField = implode(',', $indexField);
        $editField = implode(',', $editField);
        $addField = implode(',', $addField);
        $searchField = implode(',', $searchField);
        $orderField = implode(',', $orderField);
        $code = <<<CODE
<?php
namespace app\admin\controller;

use Generate\Traits\Admin\Common;
use Generate\Traits\Admin\Curd;
use Generate\Traits\Admin\CurdInterface;

class {$controllerName} extends {$baseController} implements curdInterface
{
    /**
     * 特别说明
     * Common中的文件不能直接修改！！！！
     * 如果需要进行业务追加操作，请复制Common中的对应的钩子方法到此控制器中后在进行操作
     * Happy Coding
     **/
    use Curd, Common;

    protected \$cache = true; //是否使用缓存
    protected \$modelName  = '{$modelName}';  //模型名
    protected \$indexField = [{$indexField}];  //查，字段名
    protected \$addField   = [{$addField}];    //增，字段名
    protected \$editField  = [{$editField}];   //改，字段名
    /**
     * 条件查询，字段名,例如：无关联查询['name','age']，关联查询['name','age',['productId' => 'product.name']],解释：参数名为productId,关联表字段p.name
     * 默认的类型为输入框，如果有下拉框，时间选择等需求可以这样定义['name',['type' => ['type','select']]];目前有select,time_start,time_end三种可被定义
     * 通过模型定义的关联查询，可以这样定义['name',['memberId'=>['member.nickname','relation']]],只能有一个关联方法被定义为relation，且字段前的表别名必须为关联的方法名
     * @var array
     */
    protected \$searchField = [{$searchField}];
    protected \$orderField = '$orderField';  //排序字段
    protected \$pageLimit   = 10;               //分页数
    
    //增，数据检测规则
    protected \$add_rule = [
        //'nickName|昵称'  => 'require|max:25'
{$addRule}
    ];
    //改，数据检测规则
    protected \$edit_rule = [
        //'nickName|昵称'  => 'require|max:25'
{$editRule}
    ];
}
CODE;
        $this->createPath($controllerPath);
        file_put_contents($controllerPath . "{$controllerName}.php", $code);
        return true;
    }

    /**
     * 生成列表视图
     * @param $data
     * @param $controllerName
     * @return bool|string
     */
    private function createIndexView($data, $controllerName)
    {
        $viewDirName = Loader::parseName($controllerName);
        if (!empty($this->config['view_root'])) {
            $viewDir = $this->config['view_root'] . "/{$viewDirName}/";
            $viewPath = $viewDir . 'index.vue';
        } else {
            return '配置错误';
        }
        if (file_exists($viewPath)) {
            return 'index视图已存在';
        }

        $searchHtml = '';
        $tableColumns = [];
        $searchField = [];
        $tpl = Config::get('curd');
        foreach ($data['pageData'] as $k => $v) {
            if (in_array('查', $v['curd'])) {
                $tableColumns[] = [
                    'title' => $v['label'],
                    'key' => $v['name']
                ];
            }
            if ($v['search'] == true) {
                $tmpTpl = $tpl['search']['text'];
                if (!empty($tpl['search'][$v['business']])) {
                    $tmpTpl = $tpl['search'][$v['business']];
                }
                $searchHtml .= str_replace(['{{name}}', '{{label}}', '{{value}}'], [$v['name'], $v['label'], $v['name']], $tmpTpl) . "\n";
                $searchField[$v['name']] = '';
            }
        }
        $tableColumns[] = [
            'title' => '操作',
            'slot' => 'action',
            'width' => 150,
            'align' => 'center'
        ];

        $templatePath = Config::get('curd.index_template');
        if (empty($templatePath)) {
            $templatePath = __DIR__ . '/../Templates/index.vue';
        }
        if (!file_exists($templatePath)) {
            return '模板文件不存在:' . $templatePath;
        }
        $code = file_get_contents($templatePath);
        $tableColumns = empty($tableColumns) ? '[]' : json_encode($tableColumns, JSON_UNESCAPED_UNICODE);
        $searchField = empty($searchField) ? '{}' : json_encode($searchField, JSON_UNESCAPED_UNICODE);
        $code = str_replace(['{{search_form}}', '{{table_columns}}', '{{search_field}}', '{{controller_name}}'], [$searchHtml, $tableColumns, $searchField, $viewDirName], $code);
        $this->createPath($viewDir);
        file_put_contents($viewPath, $code);
        return true;
    }

    /**
     * 生成添加视图
     * @param $data
     * @param $controllerName
     * @return bool|string
     */
    private function createAddView($data, $controllerName)
    {
        $viewDirName = Loader::parseName($controllerName);
        if (!empty($this->config['view_root'])) {
            $viewDir = $this->config['view_root'] . "/{$viewDirName}/";
            $viewPath = $viewDir . 'add.vue';
        } else {
            return '配置错误';
        }
        if (file_exists($viewPath)) {
            return 'add视图已存在';
        }

        $tpl = Config::get('curd');
        $html = '';
        $formField = [];
        foreach ($data['pageData'] as $k => $v) {
            if (in_array('增', $v['curd'])) {
                $tmpTpl = '';
                if (!empty($tpl['form'][$v['business']])) {
                    $tmpTpl = $tpl['form'][$v['business']];
                }
                $html .= str_replace(['{{name}}', '{{label}}'], [$v['name'], $v['label']], $tmpTpl) . "\n";
                switch ($v['business']) {
                    case 'uploadImage':
                        $formField[$v['name']] = [];
                        break;
                    default:
                        $formField[$v['name']] = '';
                }
            }
        }
        $templatePath = Config::get('curd.add_template');
        if (empty($templatePath)) {
            $templatePath = __DIR__ . '/../Templates/add.vue';
        }
        if (!file_exists($templatePath)) {
            return '模板文件不存在:' . $templatePath;
        }
        $code = file_get_contents($templatePath);
        $formField = empty($formField) ? '{}' : json_encode($formField, JSON_UNESCAPED_UNICODE);
        $code = str_replace(['{{curd_form_group}}', '{{curd_form_field}}', '{{controller_name}}'], [$html, $formField, $viewDirName], $code);
        $this->createPath($viewDir);
        file_put_contents($viewPath, $code);
        return true;
    }

    /**
     * 生成编辑视图
     * @param $data
     * @param $controllerName
     * @return bool|string
     */
    private function createEditView($data, $controllerName)
    {
        $viewDirName = Loader::parseName($controllerName);
        if (!empty($this->config['view_root'])) {
            $viewDir = $this->config['view_root'] . "/{$viewDirName}/";
            $viewPath = $viewDir . 'edit.vue';
        } else {
            return '配置错误';
        }
        if (file_exists($viewPath)) {
            return 'edit视图已存在';
        }

        $html = '';
        $tpl = Config::get('curd');
        $formField = [];
        foreach ($data['pageData'] as $k => $v) {
            if (in_array('改', $v['curd'])) {
                $tmpTpl = '';
                if (!empty($tpl['form'][$v['business']])) {
                    $tmpTpl = $tpl['form'][$v['business']];
                }
                $html .= str_replace(['{{name}}', '{{label}}'], [$v['name'], $v['label']], $tmpTpl) . "\n";
                switch ($v['business']) {
                    case 'uploadImage':
                        $formField[$v['name']] = [];
                        break;
                    default:
                        $formField[$v['name']] = '';
                }
            }

        }

        $templatePath = Config::get('curd.edit_template');
        if (empty($templatePath)) {
            $templatePath = __DIR__ . '/../Templates/edit.vue';
        }
        if (!file_exists($templatePath)) {
            return '模板文件不存在:' . $templatePath;
        }
        $code = file_get_contents($templatePath);
        $formField = empty($formField) ? '{}' : json_encode($formField, JSON_UNESCAPED_UNICODE);
        $code = str_replace(['{{curd_form_group}}', '{{curd_form_field}}', '{{controller_name}}'], [$html, $formField, $viewDirName], $code);
        $this->createPath($viewDir);
        file_put_contents($viewPath, $code);
        return true;
    }

    private function createMeta($showName, $dir)
    {
        if (!empty($this->config['view_root'])) {
            $viewDir = $this->config['view_root'] . "/{$dir}/";
            $viewPath = $viewDir . 'meta.yml';
        } else {
            return '配置错误';
        }
        if (file_exists($viewPath)) {
            return 'meta.yml文件已存在';
        }
        $meta = <<<META
index:
  breadcrumb: 
    - title: $showName
  title: $showName
add:
  breadcrumb: 
    - title: $showName
      to: /$dir
    - title: 添加
  title: 添加$showName
edit:
  breadcrumb: 
    - title: $showName
      to: /$dir
    - title: 修改
  title: 修改$showName
META;
        $this->createPath($viewDir);
        file_put_contents($viewPath, $meta);
        return true;
    }

    /**
     * 生成模型
     * @param $data
     * @param $modelName
     * @return bool|string
     */
    private function createModel($data, $modelName)
    {
        $modelPath = APP_PATH . "common/model/";
        if (file_exists($modelPath . "{$modelName}.php")) {
            return '模型已存在';
        }
        $mainCode = '';
        $use = "use think\Model;\nuse Generate\Traits\Model\Cache;";
        $time_status = 'false';
        if (in_array('开启软删', $data['model'])) {
            $use .= "use traits\model\SoftDelete;\n";
            $mainCode .= "use SoftDelete;\n";
        }

        if (in_array('自动时间戳', $data['model'])) {
            $time_status = 'true';
        }

        $mainCode .= "    protected \$type = [\n";
        foreach ($data['pageData'] as $k => $v) {
            if (!empty($v['autotype'])) {
                $mainCode .= "        '{$v['name']}' => '{$v['autotype']}',\n";
            }
        }
        $mainCode .= "    ];\n";

        $code = <<<CODE
<?php
namespace app\common\model;

{$use}

class {$modelName} extends Model
{
    use Cache; //处理缓存，请勿修改或删除。
    {$mainCode}
    // 自动维护时间戳
    protected \$autoWriteTimestamp = {$time_status};
    // 定义时间戳字段名
    protected \$createTime = 'create_time';
    protected \$updateTime = 'update_time';
}
CODE;

        $this->createPath($modelPath);
        file_put_contents($modelPath . "{$modelName}.php", $code);
        return true;
    }

    /**
     * 生成关联关系
     * @param Request $request
     */
    public function generateRelation(Request $request)
    {
        $params = $request->param();
        $model_name = $params['tableName'];
        $data = json_decode($params['data'], true);
        $class_name = "app\common\model\\{$model_name}";
        $model = new $class_name;
        $path = APP_PATH . "common/model/{$model_name}.php";
        $html = rtrim(file_get_contents($path), '}');
        foreach ($data['pageData'] as $k => $v) {
            if (is_array($v['business']) && !empty($v['business'])) {
                $fun = ($v['table'][0]);
                $fun_name = empty($v['fun_name']) ? strtolower($fun) : $v['fun_name'];
                $exists = method_exists($model, $fun_name);
                if (!$exists) {
                    switch ($v['business'][0]) {
                        case '1v1':
                            $has = "hasOne({$fun}::class,'{$v['table'][1]}','{$v['name']}');";
                            break;
                        case '1vm':
                            $has = "hasMany({$fun}::class,'{$v['table'][1]}','{$v['name']}');";
                            break;
                        case 'mvm':
                            $has = "belongsToMany({$fun}::class,'{$v['business'][1]}');";
                            break;
                        default:
                            $has = '';
                    }
                    $html .= <<<CODE
                    
    public function {$fun_name}()
    {
        return \$this->{$has}
    }

CODE;
                }
            }
        }

        $html .= <<<CODE
               
}
CODE;
        file_put_contents($path, $html);
        $this->success();
    }
}
