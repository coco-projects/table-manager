<?php

    use Coco\tableManager\TableRegistry;
    use think\db\Query;

    require 'common.php';

    /** @var \Coco\examples\TablePartTest $tab */
    $tab = $db->getTable('part_test');

    $totalUpdated = 0;
    $tab->eachTableIns(function(Query $tabIns) use (&$totalUpdated) {
        $totalUpdated += $tabIns->count();
    });

    print_r($totalUpdated);
