<?php

    use Coco\tableManager\TableAbstract;
    use think\db\Query;

    require 'common.php';

    /** @var \Coco\examples\TablePartTest $tabIns */
    $tabIns = $db->getTable('part_test');



    $title1 = "test title7";
    $result = $tabIns->setCondition(function(Query $query, TableAbstract $tabIns) {
        $query->where([
            [
                $tabIns->getTitleField(),
                'like',
                '%title%',
            ],
        ]);

    })->fetchSql(false)->order($tabIns->getTitleField(),'desc')->group($tabIns->getTitleField())->select();



//    $result = $tabIns->getCount();

    print_r($result);
