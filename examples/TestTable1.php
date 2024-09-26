<?php

    namespace Coco\examples;

    class TestTable1 extends \Coco\tableManager\TableAbstract
    {
        public string $comment = 'test111 页面';

        public array $fieldsSqlMap = [
            "path"      => "`__FIELD__NAME__` char(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '路径',",
            "title"     => "`__FIELD__NAME__` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '标题',",
            "page_type" => "`__FIELD__NAME__` int(10) unsigned NOT NULL COMMENT '页面类型，1:首页，2:列表页，3:详情页',",
            "token"     => "`__FIELD__NAME__` char(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '对应页面的token',",
        ];

        protected array $indexSentence = [
            "page_type" => "KEY `__FIELD__NAME___index` (`__FIELD__NAME__`),",
            "token"     => "KEY `__FIELD__NAME___index` (`__FIELD__NAME__`),",
        ];

        public function setPathField(string $value): static
        {
            $this->setFeildName('path', $value);

            return $this;
        }

        public function getPathField(): string
        {
            return $this->getFieldName('path');
        }

        public function setTitleField(string $value): static
        {
            $this->setFeildName('title', $value);

            return $this;
        }

        public function getTitleField(): string
        {
            return $this->getFieldName('title');
        }

        public function setPageTypeField(string $value): static
        {
            $this->setFeildName('page_type', $value);

            return $this;
        }

        public function getPageTypeField(): string
        {
            return $this->getFieldName('page_type');
        }

        public function setTokenField(string $value): static
        {
            $this->setFeildName('token', $value);

            return $this;
        }

        public function getTokenField(): string
        {
            return $this->getFieldName('token');
        }

    }