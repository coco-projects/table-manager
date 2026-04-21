<?php

    use Coco\tableManager\TableRegistry;

    require 'common.php';

    /** @var \Coco\examples\TablePartTest $tab */
    $tab = $db->getTable('part_test');

    $title1 = "test title1";
    $title2 = "test title2";
    $title3 = "test title3";

    $data = [
        [
            $tab->getPkField()       => $tab->calcPk(),
            $tab->getPathField()     => "test",
            $tab->getTitleField()    => $title1,
            $tab->getPageTypeField() => 1,
            $tab->getTokenField()    => "123456",
        ],
        [
            $tab->getPkField()       => $tab->calcPk(),
            $tab->getPathField()     => "test",
            $tab->getTitleField()    => $title2,
            $tab->getPageTypeField() => 1,
            $tab->getTokenField()    => "123456",
        ],
        [
            $tab->getPkField()       => $tab->calcPk(),
            $tab->getPathField()     => "test",
            $tab->getTitleField()    => $title3,
            $tab->getPageTypeField() => 1,
            $tab->getTokenField()    => "123456",
        ],
    ];

//    $tab->insertAll($tab->getTitleField(), $data);
    $tab->insertAllByIdPart($data);