<?php

    use Coco\examples\TestTable1;
    use Coco\examples\TestTable2;
    use Coco\tableManager\TableRegistry;

    require '../vendor/autoload.php';

    $db = TableRegistry::initMysqlClient('table_manager');

    $db->setStandardLogger('test');
    $db->addStdoutHandler(callback: $db::getStandardFormatter());

    // 初始化方式1
    $t1 = new TestTable1('test1');
    $db->addTable($t1, function(TestTable1 $table) {
        $registry = $table->getTableRegistry();

        $table->setPkField('id');
        $table->setIsPkAutoInc(false);
        $table->setPkValueCallable($registry::snowflakePKCallback());

        //设置字段名,不设置就是默认字段：$fieldsSqlMap 的键
        $table->setPageTypeField('page_type__1');
        $table->setTokenField('token__1');
    });

    // 初始化方式2
    $db->initTable('test2', TestTable2::class, function(TestTable2 $table) {
        $registry = $table->getTableRegistry();

        $table->setPkField('id');
        $table->setIsPkAutoInc(true);

        //设置字段名,不设置就是默认字段：$fieldsSqlMap 的键
        $table->setPageTypeField('page_type__2');
        $table->setTokenField('token__2');
    });

    // 初始化分表
    $partTable = new \Coco\examples\TablePartTest('part_test', 5);

    $db->addTable($partTable, function(\Coco\tableManager\TablePart $table) {
        $registry = $table->getTableRegistry();

        $table->setPkField('id');
        $table->setIsPkAutoInc(false);
        $table->setPkValueCallable($registry::snowflakePKCallback());
    });
