<?php

    use Coco\examples\TestTable1;
    use Coco\examples\TestTable2;
    use Coco\tableManager\TableRegistry;

    require '../vendor/autoload.php';

    $db = TableRegistry::initMysqlClient('ithinkphp_telegraph_test01');

    $db->setStandardLogger('test');

    $db->addStdoutHandler(callback: function(\Monolog\Handler\StreamHandler $handler, TableRegistry $_this) {
        $handler->setFormatter(new \Coco\logger\MyFormatter());
    });

    $t1 = new TestTable1('test1');
    $t2 = new TestTable2('test2');

    $db->addTable($t1, function(TestTable1 $table) {
        $registry = $table->getTableRegistry();

        $table->setPkField('id');
        $table->setIsPkAutoInc(false);
        $table->setPkValueCallable($registry::snowflakePKCallback());

        //设置字段名,不设置就是默认字段：$fieldsSqlMap 的键
        $table->setPageTypeField('page_type__1');
        $table->setTokenField('token__1');
    });

    $db->addTable($t2, function(TestTable2 $table) {
        $registry = $table->getTableRegistry();

        $table->setPkField('id');
        $table->setIsPkAutoInc(true);

        //设置字段名,不设置就是默认字段：$fieldsSqlMap 的键
        $table->setPageTypeField('page_type__2');
        $table->setTokenField('token__2');

    });

