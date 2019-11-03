<?php

namespace Generate\Controller;

use Generate\Traits\JsonReturn;
use GuzzleHttp\Exception\GuzzleException;
use think\exception\HttpException;
use think\facade\Config;
use think\facade\Db;
use think\facade\Env;
use think\facade\View;
use think\Request;

class Generate
{
    use JsonReturn;
    protected $config = [];

    public function __construct()
    {
        if (!Env::get('app_debug', false)) {
            throw new HttpException(404, 'module not exists:Generate');
        }
    }

    public function index()
    {
        return View::engine('php')->fetch(__DIR__ . DIRECTORY_SEPARATOR . 'index.html');
    }

    /**
     * 展示所有的表
     */
    public function showTables()
    {
        $databaseConfig = Config::get('database');
        $database = $databaseConfig['connections'][$databaseConfig['default']]['database'];
        $prefix = $databaseConfig['connections'][$databaseConfig['default']]['prefix'];
        $data = Db::query('show tables');
        $res = [];
        if (!empty($data)) {
            foreach ($data as $k => $v) {
                $res[$k]['value'] = str_replace($prefix, '', $v['Tables_in_' . $database]);
                $res[$k]['label'] = str_replace($prefix, '', $v['Tables_in_' . $database]);
            }
        }
        $this->returnRes($res, '没有数据表，请添加数据表后刷新重试', $res);
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
            $table = parse_name($table, 0);
            $databaseConfig = Config::get('database');
            $prefix = $databaseConfig['connections'][$databaseConfig['default']]['prefix'];
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
                    $res[$k]['require'] = $v['Null'] == 'NO'; //必填
                    $res[$k]['length'] = preg_replace('/\D/s', '', $v['Type']); //字段长度，不严谨
                }
            }
            $this->returnRes($res, '数据表中未定义字段，请添加后刷新重试', $res);
        }
    }

    /**
     * 获取模型
     * @param string $appName
     */
    public function getModelData(string $appName = ''): void
    {
        $modelPath = $this->getModelPath('', $appName) . '*.php';
        $res = [];
        foreach (glob($modelPath) as $k => $v) {
            $val = basename($v);
            $arr = [
                'value'    => $val,
                'label'    => $val,
                'children' => [],
                'loading'  => false,
            ];
            $res[] = $arr;
        }
        $this->returnRes($res, '没有模型', $res);
    }

    /**
     * 获取模型路径
     * @param string $modelName
     * @param string $appName
     * @return string
     */
    private function getModelPath(string $modelName = '', string $appName = ''): string
    {
        $modelPath = Config::get('curd.model_path');
        if (empty($modelPath)) {
            if ($this->isMultiApp()) {
                $modelPath = 'common' . DIRECTORY_SEPARATOR . 'model';
            } else {
                $modelPath = 'model';
            }
        }
        $modelPath = base_path() . $modelPath;
        return str_replace('{{app_name}}', $appName, $modelPath) . DIRECTORY_SEPARATOR . (empty($modelName) ? '' : (parse_name($modelName, 1) . '.php'));
    }

    /**
     * 判断是否多应用，以app/controller是否存在为准
     * @return bool
     */
    private function isMultiApp(): bool
    {
        return !file_exists(base_path() . 'controller');
    }

    /**
     * 生成
     * @param Request $request
     * @throws GuzzleException
     */
    public function generate(Request $request)
    {
        if ($request->isPost()) {
            $data = $request->post();
            $tableName = $request->post('tableName');
            $showName = $request->post('showName', '');
            if (empty($tableName) || empty($data['pageData'])) {
                $this->returnFail('参数错误', 0);
            }

            $appName = $request->post('appName');
            $controllerName = $request->post('controllerName');
            if (empty($controllerName)) {
                $controllerName = $tableName;
            }
            $controllerName = parse_name($controllerName, 1);
            $modelName = parse_name($tableName, 1);

            $responseMessage = '';

            $response = [];

            if (in_array('控制器', $data['fruit'])) {
                //生成控制器
                $controllerRes = $this->createController($data, $appName, $controllerName, $modelName);
                $responseMessage .= ($controllerRes === true ? "控制器生成成功\n" : "$controllerRes\n") . '</br>';
                //生成验证器
                $pk = Db::name($modelName)->getPk();
                $validateRes = $this->createAppValidate($data, $controllerName, $pk);
                $responseMessage .= ($validateRes === true ? "验证器生成成功，请根据业务逻辑进行配置\n" : "$validateRes\n") . '</br>';
            }
            //生成模板
            if (in_array('视图', $data['fruit'])) {
                $indexRes = $this->createIndexView($data, $controllerName);
                $responseMessage .= ($indexRes === true ? "index视图生成成功\n" : "$indexRes\n") . '</br>';
                $addRes = $this->createAddView($data, $controllerName);
                $responseMessage .= ($addRes === true ? "add视图生成成功\n" : "$addRes\n") . '</br>';
                $editRes = $this->createEditView($data, $controllerName);
                $responseMessage .= ($editRes === true ? "edit视图生成成功\n" : "$editRes\n") . '</br>';
                $dir = parse_name($controllerName);
                $this->createMeta($showName, $dir);
                $response['router'] = '<p>{title: \'' . $showName . '\',to: \'/' . $dir . '\'}</p>';
            }
            //生成模型
            if (in_array('模型', $data['fruit'])) {
                $modelRes = $this->createModel($data, $modelName, $appName);
                $responseMessage .= ($modelRes === true ? "模型生成成功\n" : "$modelRes\n") . '</br>';
            }
            $response['message'] = $responseMessage;
            $this->returnSuccess($response, 1);
        }
    }

    /**
     * 生成控制器
     * @param array $data
     * @param string $appName
     * @param string $controllerName
     * @param string $modelName
     * @return bool|string
     */
    private function createController(array $data, string $appName, string $controllerName, string $modelName)
    {
        $controllerLayer = Config::get('route.controller_layer', 'controller');
        if ($this->isMultiApp()) {
            $controllerPath = base_path() . $appName . DIRECTORY_SEPARATOR . $controllerLayer . DIRECTORY_SEPARATOR;
            $namespace = 'app\\' . $appName . '\\' . $controllerLayer;
        } else {
            $controllerPath = base_path() . $controllerLayer . DIRECTORY_SEPARATOR;
            $namespace = 'app\\' . $controllerLayer;
        }
        if (file_exists($controllerPath . "{$controllerName}.php")) {
            return '控制器已存在';
        }

        $baseController = Config::get('curd.base_controller');
//        if (!empty($baseController)) {
//            $baseController = 'extends ' . $baseController;
//        }
        $signController = Config::get('curd.sign_controller');
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

        //模型类
        $modelClass = $this->getModelClass($modelName, $appName);

        $code = <<<CODE
<?php
namespace {$namespace};

use Generate\Traits\Curd;

class {$controllerName} {$extends}
{
    /**
     * 增删改查封装在Curd内，如需修改复制到控制器即可
     */
    use Curd;
    
    protected \$limit = null; //每页显示的数量
    protected \$model = {$modelClass}::class;
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
     * 获取模型类名或命名空间
     * @param string $modelName
     * @param string $appName
     * @return string
     */
    private function getModelClass(string $modelName = '', string $appName = ''): string
    {
        $modelPath = Config::get('curd.model_path');
        if (empty($modelPath)) {
            if ($this->isMultiApp()) {
                $modelPath = 'app\\common\\model';
            } else {
                $modelPath = 'app\\model';
            }
        } else {
            $modelPath = 'app\\' . trim($modelPath, '/\\');
        }
        $modelPath = str_replace(['/', '{{app_name}}'], ['\\', $appName], $modelPath);
        return '\\' . $modelPath . (empty($modelName) ? '' : ('\\' . parse_name($modelName, 1)));
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
    }

    /**
     * 生成验证文件
     * @param $data
     * @param $controllerName
     * @param $pk
     * @return bool|string
     */
    private function createAppValidate(array $data, string $controllerName, string $pk)
    {
        $validatePath = base_path() . "app/validate/{$controllerName}.php";
        if (file_exists($validatePath)) {
            return '验证器已存在';
        }
        $rule = '';
        $scene = '';
        $deleteScene = '';
        if (is_string($pk)) {
            $pk = [$pk];
        }
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
        {$rule}
    ];

    protected \$message = [
    ];

    protected \$scene = [
        'delete' => [{$deleteScene}],//删
        'update' => [{$scene}],//改
        'store' => [{$scene}],//增
        'index' => [{$scene}],//查
    ];
}
CODE;
        $this->createPath(base_path() . 'app/validate/');
        file_put_contents($validatePath, $code);
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
        $viewDirName = parse_name($controllerName);
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
                    'key'   => $v['name'],
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
            'slot'  => 'action',
            'width' => 150,
            'align' => 'center',
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
        $viewDirName = parse_name($controllerName);
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
        $viewDirName = parse_name($controllerName);
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
     * @param array $data
     * @param string $modelName
     * @param string $appName
     * @return bool|string
     */
    private function createModel(array $data, string $modelName, string $appName)
    {
        $modelPath = $this->getModelPath($modelName, $appName);
        if (file_exists($modelPath)) {
            return '模型已存在';
        }
        $mainCode = '';
        $use = "use think\Model;\n";
        $time_status = 'false';
        if (in_array('开启软删', $data['model'])) {
            $use .= "use think\model\concern\SoftDelete;\n";
            $mainCode .= "    use SoftDelete;\n";
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

        $namespace = trim($this->getModelClass('', $appName), '\\');

        $code = <<<CODE
<?php
namespace {$namespace};

{$use}

class {$modelName} extends Model
{
{$mainCode}
    // 自动维护时间戳
    protected \$autoWriteTimestamp = {$time_status};
}
CODE;

        $this->createPath(dirname($modelPath));
        file_put_contents($modelPath, $code);
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
        $model = new $class_name();
        $path = Env::get('app_path') . "common/model/{$model_name}.php";
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
        $this->returnSuccess([], 1);
    }
}
