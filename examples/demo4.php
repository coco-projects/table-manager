<?php

    use Coco\tableManager\TableRegistry;

    require 'common.php';

//    $method = TableRegistry::makeMethod($t1->getFieldsSqlMap());
    $method = TableRegistry::makeMethod($db->getTable('test2')->getFieldsSqlMap());

    print_r($method);

    /*
            public function setPathField(string $value): static
            {
                $this->setFieldName('path', $value);

                return $this;
            }

            public function getPathField(): string
            {
                return $this->getFieldName('path');
            }

            public function setUrlField(string $value): static
            {
                $this->setFieldName('url', $value);

                return $this;
            }

            public function getUrlField(): string
            {
                return $this->getFieldName('url');
            }

            public function setTitleField(string $value): static
            {
                $this->setFieldName('title', $value);

                return $this;
            }

            public function getTitleField(): string
            {
                return $this->getFieldName('title');
            }

            public function setPageTypeField(string $value): static
            {
                $this->setFieldName('page_type', $value);

                return $this;
            }

            public function getPageTypeField(): string
            {
                return $this->getFieldName('page_type');
            }

            public function setTokenField(string $value): static
            {
                $this->setFieldName('token', $value);

                return $this;
            }

            public function getTokenField(): string
            {
                return $this->getFieldName('token');
            }
     * */