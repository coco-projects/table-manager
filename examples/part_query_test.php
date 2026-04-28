<?php

    use Coco\examples\TablePartTest;
    use Coco\tableManager\TableAbstract;
    use think\db\Query;

    require 'common.php';

    /**
     * 测试说明
     * ---------------------------------------------------------
     * 1. 这个脚本用于覆盖：
     *    - 分表建表
     *    - 批量写入
     *    - 单分片查询
     *    - 跨分片查询
     *    - 指定分片查询
     *    - 聚合查询
     *    - 单分片写入/更新/删除
     *    - 老 callback 风格兼容
     *
     * 2. 如果你不想每次重建数据：
     *    $rebuild = false;
     *
     * 3. 当前跨分表排序/limit 已走“外层包装模式”，
     *    SQL 语义更接近全局 union 结果排序。
     */

    $rebuild = true;

    /** @var TablePartTest $tab */
    $tab = $db->getTable('part_test');

    if (!$tab)
    {
        echo "part_test not found in registry\n";
        exit(1);
    }

    $tab->setShardField($tab->getTokenField());

    echo "============== BASIC INFO ==============\n";
    echo "table name: " . $tab->getName() . PHP_EOL;
    echo "table count: " . $tab->getTableCount() . PHP_EOL;
    echo "shard field: " . $tab->getShardField() . PHP_EOL;

    if ($rebuild)
    {
        echo "============== REBUILD TABLES ==============\n";
        $tab->create(true);

        echo "============== INSERT TEST DATA ==============\n";

        $rows = [];
        for ($i = 1; $i <= 50; $i++)
        {
            $token = 'token_' . ($i % 9);
            $rows[] = [
                'path'      => '/path/' . $i,
                'title'     => 'title_' . $i,
                'page_type' => ($i % 3) + 1,
                'token'     => $token,
            ];
        }

        $insertCount = $tab->insertAllByShardField($rows, true);
        echo "insert count: {$insertCount}\n";

        echo "============== EACH TABLE COUNT ==============\n";
        foreach ($tab->getAllTableIds() as $tableId)
        {
            echo $tab->getTableNameById($tableId) . " => " . $tab->countTableById($tableId) . PHP_EOL;
        }

        echo "============== TOTAL COUNT CHECK ==============\n";
        echo "part total count => " . $tab->getCount() . PHP_EOL;
    }

    echo "============== ROUTE TEST ==============\n";
    $routeToken = 'token_3';
    $routeTableId = $tab->symbolToTableId($routeToken);
    echo "symbol {$routeToken} => tableId {$routeTableId} => " . $tab->getTableNameById($routeTableId) . PHP_EOL;

    echo "============== FETCH SQL: SINGLE SELECT ==============\n";
    $sql1 = $tab->whereSymbol($routeToken)
        ->field([$tab->getPathField(), $tab->getTokenField(), $tab->getPageTypeField()])
        ->where($tab->getPageTypeField(), '=', 1)
        ->order($tab->getPkField(), 'desc')
        ->limit(5)
        ->fetchSql(true)
        ->select();
    print_r($sql1);
    echo PHP_EOL;

    echo "============== REAL SINGLE SELECT ==============\n";
    $res1 = $tab->whereSymbol($routeToken)
        ->field([$tab->getPathField(), $tab->getTokenField(), $tab->getPageTypeField()])
        ->where($tab->getPageTypeField(), '=', 1)
        ->order($tab->getPkField(), 'desc')
        ->limit(5)
        ->select();
    print_r($res1->toArray());

    echo "============== FETCH SQL: ACROSS SELECT GLOBAL ORDER ==============\n";
    $sql2 = $tab->across()
        ->field([$tab->getPathField(), $tab->getPkField()])
        ->where($tab->getPathField(), '<>', '/path/21')
        ->order($tab->getPkField(), 'desc')
        ->limit(10)
        ->fetchSql(true)
        ->select();
    print_r($sql2);
    echo PHP_EOL;

    echo "============== REAL ACROSS SELECT GLOBAL ORDER ==============\n";
    $res2 = $tab->across()
        ->field([$tab->getPathField(), $tab->getPkField()])
        ->where($tab->getPathField(), '<>', '/path/21')
        ->order($tab->getPkField(), 'desc')
        ->limit(10)
        ->select();
    print_r($res2->toArray());

    echo "============== REAL ACROSS COLUMN ==============\n";
    $res2Column = $tab->across()
        ->field($tab->getPathField())
        ->where($tab->getPathField(), '<>', '/path/21')
        ->limit(10)
        ->column($tab->getPathField());
    print_r($res2Column);

    echo "============== PARTITIONS SELECT ==============\n";
    $partitionIds = array_slice($tab->getAllTableIds(), 0, min(2, $tab->getTableCount()));
    $res3 = $tab->partitions($partitionIds)
        ->field([$tab->getPathField(), $tab->getTokenField()])
        ->whereLike($tab->getPathField(), '/path/%')
        ->limit(10)
        ->select();
    print_r($res3->toArray());

    echo "============== SINGLE COUNT ==============\n";
    $count1 = $tab->whereSymbol($routeToken)->count();
    print_r($count1);
    echo PHP_EOL;

    echo "============== ACROSS COUNT ==============\n";
    $count2 = $tab->across()
        ->where($tab->getPageTypeField(), '=', 2)
        ->count();
    print_r($count2);
    echo PHP_EOL;

    echo "============== AGG TEST ==============\n";
    $maxPageType = $tab->across()->max($tab->getPageTypeField());
    $minPageType = $tab->across()->min($tab->getPageTypeField());
    $sumPageType = $tab->across()->sum($tab->getPageTypeField());
    $avgPageType = $tab->across()->avg($tab->getPageTypeField());
    echo "max(page_type)={$maxPageType}\n";
    echo "min(page_type)={$minPageType}\n";
    echo "sum(page_type)={$sumPageType}\n";
    echo "avg(page_type)={$avgPageType}\n";

    echo "============== SINGLE INSERT ==============\n";
    $insertRes = $tab->bySymbol('token_insert_demo')->insert([
        'path'      => '/insert/demo',
        'title'     => 'insert_demo_title',
        'page_type' => 9,
        'token'     => 'token_insert_demo',
    ]);
    print_r($insertRes);
    echo PHP_EOL;

    echo "============== SINGLE VALUE ==============\n";
    $value1 = $tab->whereSymbol('token_insert_demo')
        ->order($tab->getPkField(), 'desc')
        ->value($tab->getPathField());
    print_r($value1);
    echo PHP_EOL;

    echo "============== SINGLE UPDATE ==============\n";
    $updateRes = $tab->whereSymbol('token_insert_demo')
        ->where($tab->getPathField(), '=', '/insert/demo')
        ->update([
            'title' => 'insert_demo_title_updated',
        ]);
    print_r($updateRes);
    echo PHP_EOL;

    echo "============== SINGLE FIND ==============\n";
    $findRes = $tab->whereSymbol('token_insert_demo')
        ->where($tab->getPathField(), '=', '/insert/demo')
        ->order($tab->getPkField(), 'desc')
        ->find();
    print_r($findRes);
    echo PHP_EOL;

    echo "============== SINGLE DELETE ==============\n";
    $deleteRes = $tab->whereSymbol('token_insert_demo')
        ->where($tab->getPathField(), '=', '/insert/demo')
        ->delete();
    print_r($deleteRes);
    echo PHP_EOL;
    echo "============== LEGACY CALLBACK COLUMN COMPAT TEST ==============\n";
    $tab->setFetchSql(true);
    $legacySql = $tab->column($tab->getPathField(), function(Query $query, TableAbstract $tab) {
        $query
            ->field([$tab->getPathField(), $tab->getPkField()])
            ->where($tab->getPathField(), '<>', '/path/21')
            ->order($tab->getPkField(), 'desc')
            ->limit(5);
    });
    print_r($legacySql);
    echo PHP_EOL;

    echo "============== LEGACY CALLBACK COLUMN REAL TEST ==============\n";
    $tab->setFetchSql(false);
    $legacyRes = $tab->column($tab->getPathField(), function(Query $query, TableAbstract $tab) {
        $query
            ->field([$tab->getPathField(), $tab->getPkField()])
            ->where($tab->getPathField(), '<>', '/path/21')
            ->order($tab->getPkField(), 'desc')
            ->limit(5);
    });
    print_r($legacyRes);



    echo "============== LEGACY CALLBACK SELECT COMPAT TEST ==============\n";
    $legacySelect = $tab->select(function(Query $query, TableAbstract $tab) {
        $query
            ->field([$tab->getPathField(), $tab->getTokenField(), $tab->getPageTypeField()])
            ->where($tab->getPageTypeField(), '=', 1)
            ->limit(20);
    });
    print_r($legacySelect->toArray());

    echo "============== FETCH SQL: ACROSS COUNT ==============\n";
    $acrossCountSql = $tab->across()
        ->where($tab->getPageTypeField(), '=', 2)
        ->fetchSql(true)
        ->count();
    print_r($acrossCountSql);
    echo PHP_EOL;

    echo "============== FETCH SQL: AGG ==============\n";
    $aggSql = $tab->across()
        ->where($tab->getPageTypeField(), '>=', 1)
        ->fetchSql(true)
        ->max($tab->getPageTypeField());
    print_r($aggSql);
    echo PHP_EOL;


    echo "============== MAGIC CALL ACROSS SELECT TEST ==============\n";
    $magicRes = $tab->where($tab->getPageTypeField(), '=', 2)
        ->field([$tab->getPathField(), $tab->getPageTypeField()])
        ->limit(5)
        ->select();

    if ($magicRes instanceof \think\Collection)
    {
        print_r($magicRes->toArray());
    }
    else
    {
        print_r($magicRes);
    }


    echo "============== PAGINATE TEST ==============\n";
    $pageRes = $tab->across()
        ->field([$tab->getPathField(), $tab->getTokenField(), $tab->getPageTypeField(), $tab->getPkField()])
        ->whereLike($tab->getPathField(), '/path/%')
        ->order($tab->getPkField(), 'desc')
        ->paginate(1, 5);
    print_r($pageRes);

    echo "============== FETCH SQL PAGINATE TEST ==============\n";
    $pageSqlRes = $tab->across()
        ->field([$tab->getPathField(), $tab->getTokenField(), $tab->getPageTypeField(), $tab->getPkField()])
        ->whereLike($tab->getPathField(), '/path/%')
        ->order($tab->getPkField(), 'desc')
        ->fetchSql(true)
        ->paginate(1, 5);
    print_r($pageSqlRes);


    echo "============== FETCH SQL ACROSS UPDATE TEST ==============\n";
    $updateSqlList = $tab->across()
        ->where($tab->getTokenField(), '=', 'token_3')
        ->fetchSql(true)
        ->update([
            'title' => 'batch_update_demo',
        ]);
    print_r($updateSqlList);

    echo "============== FETCH SQL ACROSS DELETE TEST ==============\n";
    $deleteSqlList = $tab->across()
        ->where($tab->getTokenField(), '=', 'token_3')
        ->fetchSql(true)
        ->delete();
    print_r($deleteSqlList);

    echo "============== TO SQL LIST SELECT TEST ==============\n";
    $sqlList = $tab->partitions([0, 1])
        ->where($tab->getPageTypeField(), '=', 1)
        ->toSqlList();
    print_r($sqlList);
    echo "============== WHERE OR TEST ==============\n";
    $whereOrRes = $tab->across()
        ->field([$tab->getPathField(), $tab->getTokenField(), $tab->getPageTypeField()])
        ->where($tab->getPageTypeField(), '=', 1)
        ->whereOr($tab->getPageTypeField(), '=', 2)
        ->limit(10)
        ->select();
    print_r($whereOrRes->toArray());

    echo "============== WHERE EXP TEST ==============\n";
    $whereExpRes = $tab->across()
        ->field([$tab->getPathField(), $tab->getPageTypeField()])
        ->whereExp($tab->getPageTypeField(), '> 1')
        ->limit(10)
        ->select();
    print_r($whereExpRes->toArray());

    echo "============== FETCH SQL COUNT TOTAL STYLE TEST ==============\n";
    $countSqlRes = $tab->across()
        ->where($tab->getPageTypeField(), '=', 2)
        ->fetchSql(true)
        ->count();
    print_r($countSqlRes);
    echo PHP_EOL;

    echo "============== FETCH SQL MAX TOTAL STYLE TEST ==============\n";
    $maxSqlRes = $tab->across()
        ->where($tab->getPageTypeField(), '>=', 1)
        ->fetchSql(true)
        ->max($tab->getPageTypeField());
    print_r($maxSqlRes);
    echo PHP_EOL;

    echo "============== WHERE COLUMN TEST ==============\n";
    $whereColumnRes = $tab->across()
        ->field([$tab->getPathField(), $tab->getTokenField()])
        ->whereColumn($tab->getPathField(), '<>', $tab->getTokenField())
        ->limit(5)
        ->select();
    print_r($whereColumnRes->toArray());

    echo "============== SINGLE INSERT OR IGNORE FETCH SQL TEST ==============\n";
    $insertIgnoreSql = $tab->bySymbol('token_insert_ignore_demo')
        ->fetchSql(true)
        ->insertOrIgnore([
            'path'      => '/insert/ignore',
            'title'     => 'insert_ignore_title',
            'page_type' => 7,
            'token'     => 'token_insert_ignore_demo',
        ]);
    print_r($insertIgnoreSql);
    echo PHP_EOL;

    echo "============== SINGLE REPLACE FETCH SQL TEST ==============\n";
    $replaceSql = $tab->bySymbol('token_replace_demo')
        ->fetchSql(true)
        ->replace([
            'path'      => '/replace/demo',
            'title'     => 'replace_demo_title',
            'page_type' => 8,
            'token'     => 'token_replace_demo',
        ]);
    print_r($replaceSql);
    echo PHP_EOL;


    echo "============== STABLE MODE GUARD TEST ==============\n";

    try
    {
        $tab->across()->distinct()->select();
    }
    catch (\Throwable $e)
    {
        echo "distinct guard => " . $e->getMessage() . PHP_EOL;
    }

    try
    {
        $tab->across()->group($tab->getPageTypeField())->select();
    }
    catch (\Throwable $e)
    {
        echo "group guard => " . $e->getMessage() . PHP_EOL;
    }

    try
    {
        $tab->across()->having('count(*) > 1')->select();
    }
    catch (\Throwable $e)
    {
        echo "having guard => " . $e->getMessage() . PHP_EOL;
    }

    try
    {
        $tab->across()->orderRaw('RAND()')->select();
    }
    catch (\Throwable $e)
    {
        echo "orderRaw RAND guard => " . $e->getMessage() . PHP_EOL;
    }

    try
    {
        $tab->whereSymbol('token_3')
            ->where($tab->getTokenField(), '=', 'token_4')
            ->select();
    }
    catch (\Throwable $e)
    {
        echo "whereSymbol conflict guard => " . $e->getMessage() . PHP_EOL;
    }

    try
    {
        $tab->whereSymbol('token_3')
            ->whereIn($tab->getTokenField(), ['token_1', 'token_2'])
            ->select();
    }
    catch (\Throwable $e)
    {
        echo "whereSymbol IN conflict guard => " . $e->getMessage() . PHP_EOL;
    }

    try
    {
        $tab->across()->where(function($q) {
        })->select();
    }
    catch (\Throwable $e)
    {
        echo "closure where guard => " . $e->getMessage() . PHP_EOL;
    }


    echo "============== DONE ==============\n";