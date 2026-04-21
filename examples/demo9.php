<?php

    use Coco\tableManager\TableAbstract;
    use think\db\Query;

    require 'common.php';

    /** @var \Coco\examples\TablePartTest $tab */
    $tab = $db->getTable('part_test');

    $title1 = "test title7";
    $result = $tab->setUnionCondition(function(Query $query, TableAbstract $tab) {
        $query->where([
            [
                $tab->getTitleField(),
                'like',
                '%title%',
            ],
        ]);

    })->fetchSql(false)->order($tab->getTitleField(), 'desc')->group($tab->getTitleField())->select();

//    $result = $tab->getCount();

    print_r($result);
