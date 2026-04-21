<?php

    require 'common.php';

    /** @var \Coco\examples\TablePartTest $tab */
    $tab = $db->getTable('part_test');

    $title1 = "test title7";
    $tab->setCurrentTableBySymbol($title1);
    $result = $tab->tableInsCurrent()->field(implode(',', [
        $tab->getPkField(),
        $tab->getTitleField(),
    ]))->fetchSql(false)->select();

    print_r($result);
