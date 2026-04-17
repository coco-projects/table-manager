<?php

    require 'common.php';

    /** @var \Coco\examples\TablePartTest $tabIns */
    $tabIns = $db->getTable('part_test');

    $title1 = "test title7";
    $tabIns->setCurrentTableBySymbol($title1);

    $result = $tabIns->getCountCurrent();

    print_r($result);
