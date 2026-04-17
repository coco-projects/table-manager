<?php

    use Coco\tableManager\TableRegistry;

    require 'common.php';

    /** @var \Coco\examples\TablePartTest $tabIns */
    $tabIns = $db->getTable('part_test');

    $result = $tabIns->findBySymbolAndIdPart(1230540256579684361);
    print_r($result);
