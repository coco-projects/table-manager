<?php

    require 'common.php';

    $db->removeTable('test1');

    $db->createAllTable();
//    $db->dropAllTable();
