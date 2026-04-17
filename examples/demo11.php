<?php

    use Coco\tableManager\TableRegistry;

    require 'common.php';

    /** @var \Coco\examples\TablePartTest $tabIns */
    $tabIns = $db->getTable('part_test');

    $title1 = "test title1";
    $title2 = "test title2";
    $title3 = "test title3";

    $data = [
        [
            $tabIns->getPkField()       => $tabIns->calcPk(),
            $tabIns->getPathField()     => "test",
            $tabIns->getTitleField()    => $title1,
            $tabIns->getPageTypeField() => 1,
            $tabIns->getTokenField()    => "123456",
        ],
        [
            $tabIns->getPkField()       => $tabIns->calcPk(),
            $tabIns->getPathField()     => "test",
            $tabIns->getTitleField()    => $title2,
            $tabIns->getPageTypeField() => 1,
            $tabIns->getTokenField()    => "123456",
        ],
        [
            $tabIns->getPkField()       => $tabIns->calcPk(),
            $tabIns->getPathField()     => "test",
            $tabIns->getTitleField()    => $title3,
            $tabIns->getPageTypeField() => 1,
            $tabIns->getTokenField()    => "123456",
        ],
    ];

//    $tabIns->insertAll($tabIns->getTitleField(), $data);
    $tabIns->insertAllByIdPart($data);