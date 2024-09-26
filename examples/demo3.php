<?php

    require 'common.php';

    $db->removeTable('test2');

    $db->createAllTable();
//    $db->dropAllTable();

    /** @var \Coco\examples\TestTable1 $tabIns */
    $tabIns = $db->getTable('test1');

    $tabIns->tableIns()->insert([
        "id"          => $tabIns->calcPk(),
        "path"        => "test",
        "title"       => "test title",
        "page_type__" => 1,
        "token__"     => "123456",
    ]);