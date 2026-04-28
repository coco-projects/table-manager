
# coco-tableManager

一个基于 think-orm 的轻量表管理 / 分表管理工具。

当前版本已经稳定收口，重点支持：

- 普通表建表管理
- 分表建表管理
- 单分片查询
- 跨分片查询
- 指定分片查询
- 批量分流写入
- 常用聚合
- fetchSql 调试
- 老 callback 风格兼容

---

# 安装

按你的项目方式通过 composer 引入即可。

---

# 目录结构说明

当前核心类：

- `TableAbstract`
  - 普通表 / 分表的基础抽象
  - 负责字段定义、索引定义、建表 SQL 生成
- `TablePart`
  - 分表模型
  - 负责分片数量、分片路由、分表创建删除、查询入口
- `PartQuery`
  - 分表查询代理
  - 负责链式查询、跨分表 union、单分表写入、聚合、分页、fetchSql
- `TableRegistry`
  - 表注册器
  - 负责数据库连接、表实例注册、批量建表/删表/清表

---

# 快速开始

## 1. 初始化注册器

```php
use Coco\tableManager\TableRegistry;

$db = TableRegistry::initMysqlClient('table_manager');
```

---

## 2. 注册普通表

```php
use Coco\examples\TestTable1;

$t1 = new TestTable1('test1');

$db->addTable($t1, function(TestTable1 $table) {
    $registry = $table->getTableRegistry();

    $table->setPkField('id');
    $table->setIsPkAutoInc(false);
    $table->setPkValueCallable($registry::snowflakePKCallback());

    $table->setPageTypeField('page_type__1');
    $table->setTokenField('token__1');
});
```

---

## 3. 注册分表

```php
use Coco\examples\TablePartTest;
use Coco\tableManager\TablePart;

$partTable = new TablePartTest('part_test', 5);

$db->addTable($partTable, function(TablePart $table) {
    $registry = $table->getTableRegistry();

    $table->setPkField('id');
    $table->setIsPkAutoInc(false);
    $table->setPkValueCallable($registry::snowflakePKCallback());

    $table->setShardField('token');
});
```

---

## 4. 建表

```php
$db->createAllTable(true);
```

---

# 表定义示例

## 普通表

```php
class TestTable1 extends TableAbstract
{
    public string $comment = 'test111 页面';

    public array $fieldsSqlMap = [
        "path"      => "`__FIELD__NAME__` char(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '路径',",
        "title"     => "`__FIELD__NAME__` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '标题',",
        "page_type" => "`__FIELD__NAME__` int(10) unsigned NOT NULL COMMENT '页面类型',",
        "token"     => "`__FIELD__NAME__` char(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'token',",
    ];
}
```

---

## 分表

```php
class TablePartTest extends TablePart
{
    public string $comment = 'part test';

    public array $fieldsSqlMap = [
        "path"      => "`__FIELD__NAME__` char(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '路径',",
        "title"     => "`__FIELD__NAME__` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '标题',",
        "page_type" => "`__FIELD__NAME__` int(10) unsigned NOT NULL COMMENT '页面类型',",
        "token"     => "`__FIELD__NAME__` char(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'token',",
    ];

    protected array $indexSentence = [
        "path,page_type" => "KEY `__INDEX__NAME___index` (__FIELD__NAME__),",
        "page_type"      => "KEY `__INDEX__NAME___index` (__FIELD__NAME__),",
        "token"          => "KEY `__INDEX__NAME___index` (__FIELD__NAME__),",
    ];

    public function __construct(string $name, int $tableCount = 1)
    {
        parent::__construct($name, $tableCount);
        $this->setShardField('token');
    }
}
```

---

# 分表路由规则

当前分表路由规则：

```php
$tableId = crc32((string)$symbol) % $tableCount;
```

对应方法：

```php
$tab->symbolToTableId($symbol);
```

---

# 推荐使用方式

当前版本已经稳定收口，推荐按以下方式使用。

---

## 一、单分片查询

### 1. 路由并自动附加 shardField 条件

```php
$res = $tab->whereSymbol($token)
    ->where($tab->getPageTypeField(), '=', 1)
    ->order($tab->getPkField(), 'desc')
    ->limit(5)
    ->select();
```

等价语义：

- 根据 `$token` 计算落到哪张分表
- 自动追加：
  ```php
  where token = $token
  ```

---

### 2. 只路由，不自动附加 shardField 条件

```php
$res = $tab->bySymbol($token)
    ->where($tab->getPageTypeField(), '=', 1)
    ->select();
```

适用场景：

- 你明确知道要查哪个分片
- 但不想自动加 `where token = xxx`

---

## 二、跨所有分片查询

```php
$res = $tab->across()
    ->field([$tab->getPathField(), $tab->getPkField()])
    ->where($tab->getPathField(), '<>', '/path/21')
    ->order($tab->getPkField(), 'desc')
    ->limit(10)
    ->select();
```

当前实现会：

- 每张分表做基础子查询
- 用 `UNION ALL` 拼接
- 在需要时外层包装子查询，保证全局排序 / limit 语义更稳定

---

## 三、指定部分分片查询

```php
$res = $tab->partitions([0, 1])
    ->where($tab->getPageTypeField(), '=', 1)
    ->select();
```

适用场景：

- 排查指定分片
- 灰度查询部分分片
- 运维修复脚本

---

## 四、魔术代理调用

当前 `TablePart::__call()` 已开启默认代理。

```php
$res = $tab->where($tab->getPageTypeField(), '=', 2)
    ->field([$tab->getPathField(), $tab->getPageTypeField()])
    ->limit(5)
    ->select();
```

等价于：

```php
$res = $tab->across()
    ->where($tab->getPageTypeField(), '=', 2)
    ->field([$tab->getPathField(), $tab->getPageTypeField()])
    ->limit(5)
    ->select();
```

## 注意
魔术代理默认是 **across 语义**，不是单分片语义。
如果你要单分片，请显式使用：

- `whereSymbol()`
- `bySymbol()`
- `byTableId()`

---

# 常用查询方法

当前稳定版支持：

- `field()`
- `where()`
- `whereOr()`
- `whereIn()`
- `whereNotIn()`
- `whereNull()`
- `whereNotNull()`
- `whereBetween()`
- `whereLike()`
- `whereRaw()`
- `whereExp()`
- `whereColumn()`
- `order()`
- `orderRaw()`（禁止 `RAND()`）
- `limit()`
- `page()`
- `paginate()`
- `select()`
- `find()`
- `value()`
- `column()`
- `count()`
- `max()`
- `min()`
- `sum()`
- `avg()`
- `toQuery()`
- `toSqlList()`
- `fetchSql()`

---

# 查询示例

## select

```php
$res = $tab->across()
    ->field([$tab->getPathField(), $tab->getTokenField()])
    ->whereLike($tab->getPathField(), '/path/%')
    ->limit(10)
    ->select();
```

---

## find

```php
$res = $tab->whereSymbol('token_3')
    ->where($tab->getPathField(), '=', '/path/30')
    ->order($tab->getPkField(), 'desc')
    ->find();
```

---

## value

```php
$value = $tab->whereSymbol('token_3')
    ->order($tab->getPkField(), 'desc')
    ->value($tab->getPathField());
```

---

## column

```php
$list = $tab->across()
    ->field($tab->getPathField())
    ->where($tab->getPathField(), '<>', '/path/21')
    ->limit(10)
    ->column($tab->getPathField());
```

---

## count

```php
$count = $tab->across()
    ->where($tab->getPageTypeField(), '=', 2)
    ->count();
```

---

## max / min / sum / avg

```php
$max = $tab->across()->max($tab->getPageTypeField());
$min = $tab->across()->min($tab->getPageTypeField());
$sum = $tab->across()->sum($tab->getPageTypeField());
$avg = $tab->across()->avg($tab->getPageTypeField());
```

---

# 分页

## paginate

```php
$pageRes = $tab->across()
    ->field([
        $tab->getPathField(),
        $tab->getTokenField(),
        $tab->getPageTypeField(),
        $tab->getPkField(),
    ])
    ->whereLike($tab->getPathField(), '/path/%')
    ->order($tab->getPkField(), 'desc')
    ->paginate(1, 5);
```

返回结构：

```php
[
    'total'        => 50,
    'per_page'     => 5,
    'current_page' => 1,
    'last_page'    => 10,
    'data'         => [...],
]
```

---

# 写入

## 一、单分片 insert

```php
$tab->bySymbol('token_xxx')->insert([
    'path'      => '/insert/demo',
    'title'     => 'demo',
    'page_type' => 1,
    'token'     => 'token_xxx',
]);
```

如果主键不是自增，且你设置了：

```php
$table->setPkValueCallable(...)
```

则会自动补主键。

---

## 二、单分片 insertGetId

```php
$id = $tab->bySymbol('token_xxx')->insertGetId([
    'path'      => '/insert/demo',
    'title'     => 'demo',
    'page_type' => 1,
    'token'     => 'token_xxx',
]);
```

---

## 三、单分片 insertOrIgnore

```php
$tab->bySymbol('token_xxx')->insertOrIgnore([
    'path'      => '/insert/ignore',
    'title'     => 'demo',
    'page_type' => 1,
    'token'     => 'token_xxx',
]);
```

---

## 四、单分片 replace

```php
$tab->bySymbol('token_xxx')->replace([
    'path'      => '/replace/demo',
    'title'     => 'demo',
    'page_type' => 1,
    'token'     => 'token_xxx',
]);
```

---

## 五、单分片 update

```php
$tab->whereSymbol('token_xxx')
    ->where($tab->getPathField(), '=', '/insert/demo')
    ->update([
        'title' => 'updated',
    ]);
```

---

## 六、单分片 delete

```php
$tab->whereSymbol('token_xxx')
    ->where($tab->getPathField(), '=', '/insert/demo')
    ->delete();
```

---

## 七、批量分流写入

```php
$rows = [
    [
        'path'      => '/path/1',
        'title'     => 'title_1',
        'page_type' => 1,
        'token'     => 'token_1',
    ],
    [
        'path'      => '/path/2',
        'title'     => 'title_2',
        'page_type' => 2,
        'token'     => 'token_2',
    ],
];

$tab->insertAllByShardField($rows, true);
```

如果不是自增主键，会自动补主键。

---

# fetchSql 调试

## 普通 select SQL

```php
$sql = $tab->whereSymbol('token_3')
    ->field([$tab->getPathField(), $tab->getTokenField()])
    ->order($tab->getPkField(), 'desc')
    ->limit(5)
    ->fetchSql(true)
    ->select();
```

---

## 跨分表 count SQL

```php
$sql = $tab->across()
    ->where($tab->getPageTypeField(), '=', 2)
    ->fetchSql(true)
    ->count();
```

返回：

```php
[
    'part_sql'  => '每个分表聚合后 union 的 SQL',
    'total_sql' => '外层总聚合 SQL',
]
```

---

## 跨分表聚合 SQL

```php
$sql = $tab->across()
    ->where($tab->getPageTypeField(), '>=', 1)
    ->fetchSql(true)
    ->max($tab->getPageTypeField());
```

返回：

```php
[
    'part_sql'  => '每个分表聚合后 union 的 SQL',
    'total_sql' => '外层总聚合 SQL',
]
```

---

## 分页 SQL

```php
$sql = $tab->across()
    ->field([
        $tab->getPathField(),
        $tab->getTokenField(),
        $tab->getPageTypeField(),
        $tab->getPkField(),
    ])
    ->whereLike($tab->getPathField(), '/path/%')
    ->order($tab->getPkField(), 'desc')
    ->fetchSql(true)
    ->paginate(1, 5);
```

返回：

```php
[
    'count_sql' => [...],
    'list_sql'  => '...',
]
```

---

## 跨分表 update/delete SQL

```php
$sqlList = $tab->across()
    ->where($tab->getTokenField(), '=', 'token_3')
    ->fetchSql(true)
    ->update([
        'title' => 'batch_update_demo',
    ]);
```

```php
$sqlList = $tab->across()
    ->where($tab->getTokenField(), '=', 'token_3')
    ->fetchSql(true)
    ->delete();
```

返回每张分表对应的 SQL 数组。

---

# 老接口兼容

当前仍兼容 callback 风格：

## column(callback)

```php
$res = $tab->column($tab->getPathField(), function(Query $query, TableAbstract $tab) {
    $query
        ->field([$tab->getPathField(), $tab->getPkField()])
        ->where($tab->getPathField(), '<>', '/path/21')
        ->order($tab->getPkField(), 'desc')
        ->limit(5);
});
```

---

## select(callback)

```php
$res = $tab->select(function(Query $query, TableAbstract $tab) {
    $query
        ->field([$tab->getPathField(), $tab->getTokenField(), $tab->getPageTypeField()])
        ->where($tab->getPageTypeField(), '=', 1)
        ->limit(20);
});
```

## 注意
老 callback 风格仅建议用于兼容旧代码。
新代码推荐统一改成链式：

```php
$tab->across()->where(...)->select();
$tab->whereSymbol(...)->select();
```

---

# 当前稳定版明确支持

- `bySymbol`
- `whereSymbol`
- `byTableId`
- `across`
- `partitions`
- `field`
- `where`
- `whereOr`
- `whereIn`
- `whereNotIn`
- `whereNull`
- `whereNotNull`
- `whereBetween`
- `whereLike`
- `whereRaw`
- `whereExp`
- `whereColumn`
- `order`
- `orderRaw`（禁止 `RAND()`）
- `limit`
- `page`
- `paginate`
- `select`
- `find`
- `value`
- `column`
- `count`
- `max`
- `min`
- `sum`
- `avg`
- `insert`
- `insertGetId`
- `insertOrIgnore`
- `replace`
- `update`
- `delete`
- `insertAllByShardField`
- `fetchSql`
- `toSqlList`
- callback 老接口兼容
- `TablePart::__call()` 默认 across 代理

---

# 当前稳定版明确不支持

以下能力当前稳定版直接不支持，或者不建议使用：

- `distinct`
- `group`
- `having`
- `orderRaw('RAND()')`
- 闭包 `where`
- 复杂嵌套条件树
- 跨分表 `join`
- 跨分表 `alias`
- 跨分表复杂子查询
- 跨分表 insert
- 想完全等价模拟 think-orm 全部能力

---

# 稳定模式保护

当前版本内置以下保护：

## 1. 防止无条件跨分表 update/delete

```php
$tab->across()->delete(); // 会抛异常
```

---

## 2. 防止 `whereSymbol()` 与 shardField 条件冲突

```php
$tab->whereSymbol('token_3')
    ->where('token', '=', 'token_4')
    ->select();
```

会抛异常。

---

## 3. 防止 `whereSymbol()` 与 `whereIn(shardField, ...)` 冲突

```php
$tab->whereSymbol('token_3')
    ->whereIn('token', ['token_1', 'token_2'])
    ->select();
```

会抛异常。

---

## 4. 防止不安全随机排序

```php
$tab->across()->orderRaw('RAND()')->select();
```

会抛异常。

---

# 推荐调用姿势

## 单分片
```php
$tab->whereSymbol($token)->select();
$tab->bySymbol($token)->insert($data);
```

## 跨分片
```php
$tab->across()->where(...)->select();
```

## 指定分片
```php
$tab->partitions([0, 1])->where(...)->select();
```

## 默认魔术代理
```php
$tab->where(...)->select();
```

等价于跨分片查询，不是单分片查询。

