<?php

    use Coco\tableManager\TableRegistry;

    require 'common.php';

    /** @var \Coco\examples\TablePartTest $tab */
    $tab = $db->getTable('part_test');

    $title1 = "test title1";
    $tab->setCurrentTableBySymbol($title1);
    $tab->tableInsCurrent()->insert([
        $tab->getPkField()       => $tab->calcPk(),
        $tab->getPathField()     => "test",
        $tab->getTitleField()    => $title1,
        $tab->getPageTypeField() => 1,
        $tab->getTokenField()    => "123456",
    ]);

    $title2 = "test title7";
    $tab->setCurrentTableBySymbol($title2);
    $tab->tableInsCurrent()->insert([
        $tab->getPkField()       => $tab->calcPk(),
        $tab->getPathField()     => "test",
        $tab->getTitleField()    => $title2,
        $tab->getPageTypeField() => 1,
        $tab->getTokenField()    => "123456",
    ]);