<?php

    require 'common.php';

    $db->removeTable('test2');

//    $db->createAllTable();
//    $db->dropAllTable();

    /** @var \Coco\examples\TestTable1 $tabIns */
    $tabIns = $db->getTable('test1');

    $tabIns->tableIns()->insert([
        $tabIns->getPkField()       => $tabIns->calcPk(),
        $tabIns->getPathField()     => "test",
        $tabIns->getTitleField()    => "test title",
        $tabIns->getPageTypeField() => 1,
        $tabIns->getTokenField()    => "123456",
    ]);