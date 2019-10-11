# 代码生成工具（适用于Vue）

代码生成工具主要用于生成后端开发中简单的增删改查代码，复杂的逻辑还需自己实现，不过你无须担心，我们通过简单的封装简化了开发流程，即便你是新手，也可以写出出色的代码。

> 工具基于ThinkPHP 5.1开发。
>
> 此版本前端使用iview-admin作为UI组件库，配置完善之后会向yapi中自动写入文档。

## 安装

```shell
$ composer require "lvzhao1995/code-generate"
```

## 生成工具的开启

项目根目录下运行 php think generate命令即可；开发期间根目录下生成的generate.lock不可删除。

## 框架说明

框架核心功能为代码生成；主要分为两个大的模块，即前台，后台；目前数据库的操作均为ThinkPHP 5.0中的模型方式。

## 关于性能

为了优化查询性能，默认全部的数据库查询走缓存方式，当然，你也可以在控制器中自己选择是否开启；对此也解决了在执行数据库的增，改，删操作的时候，缓存也会即时刷新，前提是你必须使用model的方式。

## 如何使用

操作之前请先完善数据表，以下全部操作都是基于数据表的字段。

### 名词解释

* 字段，数据库中的字段名
* 类型，该字段在数据库中的字段类型
* 注释，字段在数据库中的注释

### 开启生成工具

在项目根目录执行以下命令：
```shell
$ php think generate
```
执行完毕后访问`项目域名/generate`即可。

### 配置文件

* 开启生成工具时会在`config/curd.php`生成配置文件，详情查看文件中的注释。如果此文件存在则不会覆盖，请自行检查内容是否完整。如果不使用iview-admin可通过更改配置自定义模板。

* 项目根目录下会生成`env.php`文件，此文件不会向git中提交，存储本地配置，详细说明如下：
1. `view_root`生成vue文件的存放目录，使用绝对路径。
2. `api_token` yapi中此项目的token，获取方式：登录yapi后打开此项目，选择`设置/token配置`
1. `api_uri` yapi地址，如`http://api.qwangluo.net/`

### 选项说明

此章节针对生成工具界面上的选项进行简单说明

* 业务选择：选择生成前台还是后台代码，前台生成到`\app\app`中，后台生成到`\app\admin`中。
无论前台还是后台，模型均生成到`\app\common\model`中，因此，可能会提示模型已存在，这是正常的。
* 数据表：根据哪个表的结构来生成。
* 创建：需要生成的内容，如果已有同名文件，则不生成。
* 控制器名称：控制器默认按照表名命名，你可以更改它。
* 模型选项：ThinkPHP 5.0模型提供的功能，详情请查看ThinkPHP 5.0文档。
* 显示名称：生成后台时通过此项配置面包屑、页面标题等信息，生成前台时，通过此项配置文档标题。
* 允许的操作：前台可指定允许的操作。
* 登录访问：前台将根据此选项继承不同的类。
* 名称：例如form中的label名称，用于：列表页，新增加页，编辑页，验证器字段描述。
* 操作：该字段是否需要进行增，改，查的操作。
* 检索：这个字段是否用于查询，在列表页的顶部展示。
* 业务类型：选择输入方式，用于新增页、编辑页和列表页的搜索。
* 排序：此字段是否参与排序以及排序方式，如果多个字段参与排序，生成后可能需要调整顺序。
* 必填：将根据此内容生成验证器。
* 类型自动转换：ThinkPHP 5.0模型提供的功能，详情请查看ThinkPHP 5.0文档。

### 关于生成文档

在正确配置`env.php`中的内容后，生成前台时如果选择生成控制器，则会同时向yapi中写入文档，文档根据生成时配置的`允许的操作`
`名称` `操作` `必填`等内容进行，文档中已有的接口不会被覆盖，统一生成到`临时分类`中，请自行调整分类并检查文档是否正确。

### 生成内容

 数据库相关操作均使用ThinkPHP 5.0的模型操作，具体参考ThinkPHP 5.0文档
 
#### 后台

* 在生成的控制器中，会有以下固定属性

~~~php
protected $modelName  = '模型名'; 
protected $indexField = ['列表页要显示字段']; 在日常开发中，我们需要在列表中追加表格的列，首先我们需要在这里进行编辑
protected $addField = ['新增页面的表单输入字段'];
protected $editField = ['编辑页面的表单输入字段'];
/**
 * 条件查询，字段名,例如：无关联查询['name','age']，关联查询['name','age',['productId' => 'product.name']],解释：参数名为productId,关联表字段p.name
 * 默认的类型为输入框，如果有下拉框，时间选择等需求可以这样定义['name',['type' => ['type','select']]];目前有select,time_start,time_end三种可被定义
 * 通过模型定义的关联查询，可以这样定义['name',['memberId'=>['member.nickname','relation']]],只能有一个被定义为relation，且字段前的表别名必须为关联的方法名
 * @var array
 */
protected $searchField = [];
protected $pageSize = 10;                 //当前页面显示的数据数量，用于分页
//添加数据验证规则，参考tp5的验证规则
protected $add_rule = [
    'nickName|昵称'  => 'require|max:25'
];
//编辑数据验证规则，参考tp5的验证规则
protected $edit_rule = [
    'nickName|昵称'  => 'require|max:25'
];
~~~

* 针对默认操作，定义了以下钩子方法（方法所在命名空间：`\Generate\Traits\Admin\Common`），如有需要，复制到生成的控制器中进行修改即可。

    * 列表页
    ```
   indexQuery($sql)      //列表查询的sql语句，如果再列表查询上我们需要其他的操作，可以进行链式操作，如$sql->where(['id' => 1])
   indexAssign($data)    //列表页面输出到视图的数据，如果我们需要往视图中追加数据可以在此方法中实现，如$data['id'] = 1
   pageEach($item, $key) //分页查询后数据遍历处理，方便修改分页后的数据
   ```
   
    * 添加页
    ```
   addAssign($data)    //用法跟indexAssign相同
   addData($data)      //要入库的数据数组，如果你需要追加数据，那么在此方法中操作是一个好的选择，如$data['createtime'] = date('Y-m-d H:i:s')
   addEnd($id,$data)   //用法跟deleteEnd相同,特别说明$data是当前接受的参数，是包含追加后的数据集合
    ```
    
    * 修改页
    ```
    // 用法与添加页相同
    editAssign($data)
    editData($data)
    editEnd($id,$data)
    ```
    
   * 删除操作
    ```
    deleteEnd($id)     数据删除完成后，我们还需要其他操作？那么你可以选择在这个方法里书写你的逻辑，$id是你插入数据库后的id
                       如果开启事务，只需在方法内判断错误即可，如
                       if(!false){//业务逻辑判断
                           $this->returnFail('错误信息') // 输出错误提示
                       }
                       特别注意：无需书写事务提交方法（Db::commit()）和成功提示
    ```
    
#### 前台
* 前台生成的控制器有get、post、put、delete四个方法，对应实现了基础的查、增、改、删操作，
如需自定义逻辑，复制`\Generate\Traits\Admin\Common`中的方法到控制器中进行修改。

* 前台生成的控制器，包含以下固定属性：

```php
protected $limit = null; //每页显示的数量，为null时取配置文件中的值，可被前端传过来的pageSize参数覆盖
protected $model = ''; //模型名
protected $validate = ''; //位于\app\app\validate下的验证器名，增、删、改操作会调用进行验证，默认与控制器同名
protected $allow = []; //允许的操作，必须为小写，可选值为get\post\put\delete 
protected $indexField = [];  //允许在列表页返回的字段名
protected $detailField = []; //允许在详情页返回的字段名
protected $addField   = [];  //增加时允许前端传入的字段名
protected $editField  = [];  //修改时允许前端传入的字段名
protected $with = '';//关联关系
protected $cache = true;//是否开启查询缓存
protected $order = ''; //排序字段
```

* 针对默认操作，定义了以下钩子方法（方法所在命名空间：`\Generate\Traits\App\Curd`），如有需要，复制到生成的控制器中进行修改即可。

    * 列表
    ```
   indexQuery($sql)      //列表查询的模型实例，如果再列表查询上我们需要其他的操作，可以进行链式操作，如$sql->where(['id' => 1])，必须将$sql返回
   indexAssign($data)    //列表页查询到的数据，可以在这个方法里追加或修改数据，必须返回数组
   pageEach($item, $key) //分页查询后数据遍历处理，方便修改分页后的数据
   ```
  
   * 详情
    ```
  //用法与列表相同
   detailsQuery($sql) 
   detailAssign($data)
   ```   
   
    * 添加
    ```
   addData($data)      //要入库的数据数组，如果你需要追加数据，那么在此方法中操作是一个好的选择，如$data['createtime'] = date('Y-m-d H:i:s')，必须返回数组
   addEnd($pk,$data)   //添加结束后的数据处理，$pk是数据库主键值，复合主键则传入数组，$data是当前接受的参数，是包含追加后的数据集合
    ```
    
    * 修改
    ```
    // 用法与添加相同
    editData($data)
    editEnd($pk,$data)
    ```
    
   * 删除
    ```
    deleteEnd($pk,$data)     数据删除完成后，我们还需要其他操作？那么你可以选择在这个方法里书写你的逻辑，$pk是数据库主键值，复合主键则传入数组，$data是被删除的模型实例
                             if(!false){//业务逻辑判断
                                 $this->returnFail('错误信息') // 输出错误提示
                             }
                             特别注意：无需书写事务提交方法（Db::commit()）和成功提示
    ```

#### 模型

当前版本生成的模型，全部位于`\app\common\model`，前后台使用统一的模型类。具体属性含义，请参考模型内注释和ThinkPHP 5.0文档。

#### 前台接口

默认提供四个接口，路径一致，通过请求方式和参数进行区分，不影响自定义的方法，详见下方表格：

| 地址 | 请求方式 | 响应方法 | 备注 |
| --- | --- | --- | --- |
| /app/控制器/index | get | get | 查询。传入id时返回详情，字段按照$detailField定义的返回；<br>否则返回列表，字段按照$indexField定义的返回。 |
| /app/控制器/index | post | post | 新增 |
| /app/控制器/index | put | put | 修改 |
| /app/控制器/index | delete | delete | 删除 |

#### 数据返回

目前工具封装了统一的返回方法，位于`\Generate\Traits\JsonReturn`，提供了以下方法
```php
/**
 * 通用返回，程序内部判断应该返回的状态
 * @param $flag
 * @param $failMessage
 * @param array $res
 * @param null|integer $code
 */
public function returnRes($flag, $failMessage, $res = [], $code = null)

/**
 * 操作成功的返回
 * @param array $res
 * @param null|integer $code
 */
public function returnSuccess($res = [], $code = null)

/**
 * 操作失败的返回
 * @param string $failMessage
 * @param null|integer $code
 */
public function returnFail($failMessage = '操作失败', $code = null)
```

## 项目上线

* 务必将根目录下的generate.lock文件删除
* 本项目建议在php7.0以上的环境中运行，如使用php5.6，请将php.ini中的`always_populate_raw_post_data`值改为-1

## 参考文档
* [ThinkPHP5.0完全开发手册](https://www.kancloud.cn/manual/thinkphp5/118003)
* [Vue](https://cn.vuejs.org/v2/guide/)
* [iView](https://www.iviewui.com/docs/guide/install)