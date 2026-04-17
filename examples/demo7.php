<?php

    use Coco\tableManager\TableRegistry;

    require 'common.php';

    /** @var \Coco\examples\TablePartTest $tabIns */
    $tabIns = $db->getTable('part_test');

    $title1 = "test title1";
    $tabIns->setCurrentTableBySymbol($title1);
    $tabIns->tableInsCurrent()->insert([
        $tabIns->getPkField()       => $tabIns->calcPk(),
        $tabIns->getPathField()     => "test",
        $tabIns->getTitleField()    => $title1,
        $tabIns->getPageTypeField() => 1,
        $tabIns->getTokenField()    => "123456",
    ]);

    $title2 = "test title7";
    $tabIns->setCurrentTableBySymbol($title2);
    $tabIns->tableInsCurrent()->insert([
        $tabIns->getPkField()       => $tabIns->calcPk(),
        $tabIns->getPathField()     => "test",
        $tabIns->getTitleField()    => $title2,
        $tabIns->getPageTypeField() => 1,
        $tabIns->getTokenField()    => "123456",
    ]);