<?php

    use Coco\tableManager\TableRegistry;

    require 'common.php';

    $sql = <<<'SQL'
CREATE TABLE `ithinkphp_telegram_bots_message_5814103448` (
  `bot_id` BIGINT (11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '机器人 telegramid',
  `update_id` BIGINT (11) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'update_id',
  `sender_id` BIGINT (11) NOT NULL DEFAULT '0' COMMENT 'sender_id',
  `media_group_id` BIGINT (11) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'media_group_id',
  `message_load_type` TINYINT (11) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'text=1, video=2, photo=3, audio=4, document=5, animation=6, sticker=7, location=8, contact=9, news=10, poll=11',
  `message_from_type` TINYINT (11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '1:message, 2:edited_message, 3:channel_post, 4:edited_channel_post',
  `file_id` VARCHAR (255) NOT NULL DEFAULT '' COMMENT 'file_id',
  `file_unique_id` VARCHAR (255) NOT NULL DEFAULT '' COMMENT 'file_unique_id',
  `file_size` BIGINT (11) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'file_size',
  `caption` TEXT COMMENT '标题',
  `chat_type` TINYINT (4) DEFAULT NULL COMMENT 'private=1, group=2, supergroup=3, channel=4',
  `text` LONGTEXT COMMENT 'text 信息',
  `raw` LONGTEXT COMMENT '原生json',
  `date` INT (10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '信息发送时间',
  `time` INT (10) UNSIGNED NOT NULL DEFAULT '0',
  `chat_source_type` CHAR(24) NOT NULL DEFAULT '' COMMENT 'channel or user',
  `chat_source_username` CHAR(24) NOT NULL DEFAULT '' COMMENT 'username',
  PRIMARY KEY (`id`),
  KEY `idx_sender_date` (`sender`, `date`),
  KEY `idx_bot_sender` (`bot_uid`, `sender`),
  KEY `idx_date` (`date`),
  KEY `message_load_type` (`message_load_type`),
  KEY `media_group_id` (`media_group_id`),
  KEY `update_id` (`update_id`),
  KEY `message_from_type` (`message_from_type`),
  KEY `chat_type` (`chat_type`),
  KEY `bot_uid` (`bot_uid`),
  KEY `bot_id` (`bot_id`)
) ENGINE = INNODB CHARSET = utf8mb4 COMMENT = '机器人信息表' 
SQL;


    $arrDefine = TableRegistry::makeFieldsSqlMap($sql);

    print_r($arrDefine);

    /*


     * */