<?php

    use Coco\tableManager\TableRegistry;

    require 'common.php';

    /** @var \Coco\examples\TablePartTest $tab */
    $tab = $db->getTable('part_test');

    $id = 1230546255399093142;

    $result = $tab->getTableInsWithSymbol($tab->getPkField(), $id)->find();

    print_r($result);
