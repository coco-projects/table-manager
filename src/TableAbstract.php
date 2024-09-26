<?php

    namespace Coco\tableManager;

    use think\db\Query;

abstract class TableAbstract
{
    protected ?TableRegistry $tableRegistry   = null;
    protected string         $comment;
    protected string         $pkField         = 'id';
    protected bool           $isPkAutoInc     = true;
    protected $PkValueCallable = null;

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

    public function getTableRegistry(): TableRegistry
    {
        return $this->tableRegistry;
    }

    public function tableIns(): Query
    {
        return $this->tableRegistry->getDbManager()->table($this->name);
    }

    public function setFeildName($systemName, $customName): static
    {
        $this->fieldsCustomNameMap[$systemName] = $customName;

        return $this;
    }

    public function getFieldName(string $systemFieldName)
    {
        $fieldName = $systemFieldName;
        if (isset($this->fieldsCustomNameMap[$systemFieldName])) {
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

    public function setPkValueCallable(callable $PkValueCallable): static
    {
        $this->PkValueCallable = $PkValueCallable;

        return $this;
    }

    public function calcPk(): int|string
    {
        return call_user_func_array($this->PkValueCallable, []);
    }

    public function drop()
    {
        $sql = $this->buildDropSql();
        $this->tableRegistry->logInfo('执行：' . $sql);

        return $this->tableRegistry->getDbManager()->execute($sql);
    }

    public function buildDropSql(): string
    {
        return 'DROP TABLE IF EXISTS `' . $this->name . '`;';
    }

    public function truncate()
    {
        $sql = $this->buildTruncateSql();
        $this->tableRegistry->logInfo('执行：' . $sql);

        return $this->tableRegistry->getDbManager()->execute($sql);
    }

    public function buildTruncateSql(): string
    {
        return 'TRUNCATE `' . $this->name . '`;';
    }

    public function create(bool $forceCreate = false)
    {
        if ($forceCreate) {
            $this->drop();
        }

        $sql = $this->buildCreateSql();
        $this->tableRegistry->logInfo('执行：' . $sql);

        return $this->tableRegistry->getDbManager()->execute($sql);
    }

    public function buildCreateSql(): string
    {
        $sql = [];

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . $this->name . '` (';

        if ($this->isPkAutoInc) {
            $sql[] = "`" . $this->pkField . "` bigint(10) unsigned NOT NULL AUTO_INCREMENT,";
        } else {
            $sql[] = "`" . $this->pkField . "` bigint(10) unsigned NOT NULL,";
        }

        foreach ($this->fieldsSqlMap as $systemFieldName => $sqlTemplate) {
            $fieldName = $this->getFieldName($systemFieldName);

            $sql[] = strtr($sqlTemplate, [
                "__FIELD__NAME__" => $fieldName,
            ]);
        }

        foreach ($this->indexSentence as $systemFieldName => $sentence) {
            $fieldName = $this->getFieldName($systemFieldName);

            $sql[] = strtr($sentence, [
                "__FIELD__NAME__" => $fieldName,
            ]);
        }

        $sql[] = "PRIMARY KEY (`" . $this->pkField . "`)";
        $sql[] = ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='" . $this->comment . "';";

        return implode(PHP_EOL, $sql);
    }
}
