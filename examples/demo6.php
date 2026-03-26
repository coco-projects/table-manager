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


    $class = TableRegistry::sqlToClass($sql);

    print_r($class);
/*
<?php

    namespace __NAMESPACE__;

    class IthinkphpTelegramBotsMessage5814103448 extends \Coco\tableManager\TableAbstract
    {
        public string $comment = '机器人信息表';

        public array $fieldsSqlMap = [
            "bot_id"      => "`__FIELD__NAME__` BIGINT (11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '机器人 telegramid',",
"update_id"      => "`__FIELD__NAME__` BIGINT (11) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'update_id',",
"sender_id"      => "`__FIELD__NAME__` BIGINT (11) NOT NULL DEFAULT '0' COMMENT 'sender_id',",
"media_group_id"      => "`__FIELD__NAME__` BIGINT (11) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'media_group_id',",
"message_load_type"      => "`__FIELD__NAME__` TINYINT (11) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'text=1, video=2, photo=3, audio=4, document=5, animation=6, sticker=7, location=8, contact=9, news=10, poll=11',",
"message_from_type"      => "`__FIELD__NAME__` TINYINT (11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '1:message, 2:edited_message, 3:channel_post, 4:edited_channel_post',",
"file_id"      => "`__FIELD__NAME__` VARCHAR (255) NOT NULL DEFAULT '' COMMENT 'file_id',",
"file_unique_id"      => "`__FIELD__NAME__` VARCHAR (255) NOT NULL DEFAULT '' COMMENT 'file_unique_id',",
"file_size"      => "`__FIELD__NAME__` BIGINT (11) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'file_size',",
"caption"      => "`__FIELD__NAME__` TEXT COMMENT '标题',",
"chat_type"      => "`__FIELD__NAME__` TINYINT (4) DEFAULT NULL COMMENT 'private=1, group=2, supergroup=3, channel=4',",
"text"      => "`__FIELD__NAME__` LONGTEXT COMMENT 'text 信息',",
"raw"      => "`__FIELD__NAME__` LONGTEXT COMMENT '原生json',",
"date"      => "`__FIELD__NAME__` INT (10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '信息发送时间',",
"time"      => "`__FIELD__NAME__` INT (10) UNSIGNED NOT NULL DEFAULT '0',",
"chat_source_type"      => "`__FIELD__NAME__` CHAR(24) NOT NULL DEFAULT '' COMMENT 'channel or user',",
"chat_source_username"      => "`__FIELD__NAME__` CHAR(24) NOT NULL DEFAULT '' COMMENT 'username',",
        ];

        protected array $indexSentence = [



        ];


        public function setBotIdField(string $value): static
        {
            $this->setFeildName('bot_id', $value);

            return $this;
        }

        public function getBotIdField(): string
        {
            return $this->getFieldName('bot_id');
        }

        public function setUpdateIdField(string $value): static
        {
            $this->setFeildName('update_id', $value);

            return $this;
        }

        public function getUpdateIdField(): string
        {
            return $this->getFieldName('update_id');
        }

        public function setSenderIdField(string $value): static
        {
            $this->setFeildName('sender_id', $value);

            return $this;
        }

        public function getSenderIdField(): string
        {
            return $this->getFieldName('sender_id');
        }

        public function setMediaGroupIdField(string $value): static
        {
            $this->setFeildName('media_group_id', $value);

            return $this;
        }

        public function getMediaGroupIdField(): string
        {
            return $this->getFieldName('media_group_id');
        }

        public function setMessageLoadTypeField(string $value): static
        {
            $this->setFeildName('message_load_type', $value);

            return $this;
        }

        public function getMessageLoadTypeField(): string
        {
            return $this->getFieldName('message_load_type');
        }

        public function setMessageFromTypeField(string $value): static
        {
            $this->setFeildName('message_from_type', $value);

            return $this;
        }

        public function getMessageFromTypeField(): string
        {
            return $this->getFieldName('message_from_type');
        }

        public function setFileIdField(string $value): static
        {
            $this->setFeildName('file_id', $value);

            return $this;
        }

        public function getFileIdField(): string
        {
            return $this->getFieldName('file_id');
        }

        public function setFileUniqueIdField(string $value): static
        {
            $this->setFeildName('file_unique_id', $value);

            return $this;
        }

        public function getFileUniqueIdField(): string
        {
            return $this->getFieldName('file_unique_id');
        }

        public function setFileSizeField(string $value): static
        {
            $this->setFeildName('file_size', $value);

            return $this;
        }

        public function getFileSizeField(): string
        {
            return $this->getFieldName('file_size');
        }

        public function setCaptionField(string $value): static
        {
            $this->setFeildName('caption', $value);

            return $this;
        }

        public function getCaptionField(): string
        {
            return $this->getFieldName('caption');
        }

        public function setChatTypeField(string $value): static
        {
            $this->setFeildName('chat_type', $value);

            return $this;
        }

        public function getChatTypeField(): string
        {
            return $this->getFieldName('chat_type');
        }

        public function setTextField(string $value): static
        {
            $this->setFeildName('text', $value);

            return $this;
        }

        public function getTextField(): string
        {
            return $this->getFieldName('text');
        }

        public function setRawField(string $value): static
        {
            $this->setFeildName('raw', $value);

            return $this;
        }

        public function getRawField(): string
        {
            return $this->getFieldName('raw');
        }

        public function setDateField(string $value): static
        {
            $this->setFeildName('date', $value);

            return $this;
        }

        public function getDateField(): string
        {
            return $this->getFieldName('date');
        }

        public function setTimeField(string $value): static
        {
            $this->setFeildName('time', $value);

            return $this;
        }

        public function getTimeField(): string
        {
            return $this->getFieldName('time');
        }

        public function setChatSourceTypeField(string $value): static
        {
            $this->setFeildName('chat_source_type', $value);

            return $this;
        }

        public function getChatSourceTypeField(): string
        {
            return $this->getFieldName('chat_source_type');
        }

        public function setChatSourceUsernameField(string $value): static
        {
            $this->setFeildName('chat_source_username', $value);

            return $this;
        }

        public function getChatSourceUsernameField(): string
        {
            return $this->getFieldName('chat_source_username');
        }


    }
*/