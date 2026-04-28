<?php

    use Coco\tableManager\TableRegistry;

    require 'common.php';

    /** @var \Coco\examples\TablePartTest $tab */
    $tab = $db->getTable('part_test');

    $result = $tab->getTableInsWithSymbol($tab->getTokenField(), 'token_3')->find();

    print_r($result);
