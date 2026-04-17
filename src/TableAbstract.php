<?php

    namespace Coco\tableManager;

    use think\db\Query;

    abstract class TableAbstract
    {
        protected ?TableRegistry $tableRegistry   = null;
        protected string         $comment         = '';
        protected string         $pkField         = 'id';
        protected bool           $isPkAutoInc     = true;
        protected                $pkValueCallable = null;

        protected array $fieldsSqlMap        = [];
        protected array $fieldsCustomNameMap = [];
        protected array $indexSentence       = [];

        public function __construct(protected string $name)
        {
        }

        public function setTableRegistry(?TableRegistry $tableRegistry): static
        {
            $this->tableRegistry = $tableRegistry;

            return $this;
        }

        public function getTableRegistry(): ?TableRegistry
        {
            return $this->tableRegistry;
        }

        public function isTableCreated(): bool
        {
            try
            {
                $this->tableIns()->find();

                return true;
            }
            catch (\Exception $e)
            {
                return false;
            }
        }

        public function setFieldName(string $systemName, string $customName): static
        {
            $this->fieldsCustomNameMap[$systemName] = $customName;

            return $this;
        }

        public function getFieldName(string $systemFieldName):string
        {
            $fieldName = $systemFieldName;
            if (isset($this->fieldsCustomNameMap[$systemFieldName]))
            {
                $fieldName = $this->fieldsCustomNameMap[$systemFieldName];
            }

            return $fieldName;
        }

        public function getName(): string
        {
            return $this->name;
        }

        public function getFieldsSqlMap(): array
        {
            return $this->fieldsSqlMap;
        }

        public function isPkAutoInc(): bool
        {
            return $this->isPkAutoInc;
        }

        public function setIsPkAutoInc(bool $isPkAutoInc): static
        {
            $this->isPkAutoInc = $isPkAutoInc;

            return $this;
        }

        public function setPkField(string $pkField): static
        {
            $this->pkField = $pkField;

            return $this;
        }

        public function getPkField(): string
        {
            return $this->pkField;
        }

        public function setPkValueCallable(callable $pkValueCallable): static
        {
            $this->pkValueCallable = $pkValueCallable;

            return $this;
        }

        public function calcPk(): ?int
        {
            if (is_callable($this->pkValueCallable))
            {

                return call_user_func_array($this->pkValueCallable, []);
            }

            return null;
        }

        public function getCount(): int
        {
            return $this->tableIns()->count();
        }

        public function drop()
        {
            $sql = $this->buildDropSql();
            $this->tableRegistry->logInfo('执行：' . $sql);

            return $this->tableRegistry->getDbManager()->execute($sql);
        }

        public function truncate()
        {
            $sql = $this->buildTruncateSql();
            $this->tableRegistry->logInfo('执行：' . $sql);

            return $this->tableRegistry->getDbManager()->execute($sql);
        }

        public function create(bool $forceCreate = false)
        {
            if ($forceCreate)
            {
                $this->drop();
            }

            $sql = $this->buildCreateSql();
            $this->tableRegistry->logInfo('执行：' . $sql);

            return $this->tableRegistry->getDbManager()->execute($sql);
        }

        public function tableIns(): Query
        {
            return $this->tableRegistry->getDbManager()->table($this->name);
        }


        public function buildDropSql(): string
        {
            return 'DROP TABLE IF EXISTS `' . $this->getBuildTableName() . '`;';
        }

        public function buildTruncateSql(): string
        {
            return 'TRUNCATE `' . $this->getBuildTableName() . '`;';
        }

        public function buildCreateSql(): string
        {
            $sql = [];

            $sql[] = 'CREATE TABLE IF NOT EXISTS `' . $this->getBuildTableName() . '` (';

            if ($this->isPkAutoInc)
            {
                $sql[] = "`" . $this->pkField . "` bigint(10) unsigned NOT NULL AUTO_INCREMENT,";
            }
            else
            {
                $sql[] = "`" . $this->pkField . "` bigint(10) unsigned NOT NULL,";
            }

            foreach ($this->fieldsSqlMap as $systemFieldName => $sqlTemplate)
            {
                $fieldName = $this->getFieldName($systemFieldName);

                $sql[] = strtr($sqlTemplate, [
                    "__FIELD__NAME__" => $fieldName,
                ]);
            }

            foreach ($this->indexSentence as $systemFieldName => $sentence)
            {
                $fieldNameArray = explode(',', $systemFieldName);

                $t = [];
                foreach ($fieldNameArray as $fieldName)
                {
                    $t[] = $this->getFieldName($fieldName);
                }

                $sql[] = strtr($sentence, [
                    "__INDEX__NAME__" => implode('_', $t),
                    "__FIELD__NAME__" => implode(',', array_map(function($v) {
                        return "`$v`";
                    }, $t)),
                ]);
            }

            $sql[] = "PRIMARY KEY (`" . $this->pkField . "`)";

            $sql[] = ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci COMMENT='" . $this->escapeSqlString($this->comment) . "';" . PHP_EOL;

            return implode(PHP_EOL, $sql);
        }

        protected function escapeSqlString(string $value): string
        {
            return str_replace([
                "\\",
                "'",
            ], [
                "\\\\",
                "\\'",
            ], $value);
        }

        protected function getBuildTableName(): string
        {
            return $this->name;
        }
    }















