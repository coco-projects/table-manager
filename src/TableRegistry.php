<?php

    namespace Coco\tableManager;

    use Coco\logger\Logger;
    use Coco\snowflake\Snowflake;
    use think\DbManager;

class TableRegistry
{
    use Logger;

    protected DbManager    $dbManager;
    protected array        $tables      = [];
    protected static array $connections = [];
    protected string       $dbName;

    private function __construct($dbName, $host = '127.0.0.1', $username = 'root', $password = 'root', $port = 3306)
    {
        $config = [
            'default'     => 'db',
            'connections' => [
                'db' => [
                    'type'     => 'mysql',
                    'hostname' => $host,
                    'password' => $username,
                    'username' => $password,
                    'database' => $dbName,
                    'charset'  => 'utf8mb4',
                    'prefix'   => '',
                    'hostport' => $port,
                    'debug'    => true,
                ],
            ],
        ];

        $this->dbName    = $dbName;
        $this->dbManager = new DbManager();
        $this->dbManager->setConfig($config);
        $this->dbManager->connect();
    }

    public static function initMysqlClient($db, $host = '127.0.0.1', $username = 'root', $password = 'root', $port = 3306): static
    {
        $hash = md5(implode([
            $host,
            $username,
            $password,
            $port,
            $db,
        ]));

        if (!isset(static::$connections[$hash])) {
            static::$connections[$hash] = new static($db, $host, $username, $password, $port);
        }

        return static::$connections[$hash];
    }

    public function addTable(TableAbstract $table, callable $callback): static
    {
        $table->setTableRegistry($this);

        $callback($table);

        $this->tables[$table->getName()] = $table;

        return $this;
    }

    public function initTable(string $name, string $tableClass, callable $callback): static
    {
        $table = new $tableClass($name);

        $this->addTable($table, $callback);

        return $this;
    }

    public function removeTable(string $name): static
    {
        if (isset($this->tables[$name])) {
            $this->tables[$name]->setTableRegistry(null);

            unset($this->tables[$name]);
        }

        return $this;
    }

    public function getTable(string $name): ?TableAbstract
    {
        return $this->tables[$name] ?? null;
    }

    public function dropTable(string $name): static
    {
        $this->getTable($name)?->drop();

        return $this;
    }

    public function truncateTable(string $name): static
    {
        $this->getTable($name)?->truncate();

        return $this;
    }

    public function getDbManager(): DbManager
    {
        return $this->dbManager;
    }

    public function dropAllTable(): static
    {
        /**
         * @var TableAbstract $tableObject
         */
        foreach ($this->tables as $k => $tableObject) {
            $tableObject->drop();
        }

        return $this;
    }

    public function truncateAllTable(): static
    {
        /**
         * @var TableAbstract $tableObject
         */
        foreach ($this->tables as $k => $tableObject) {
            $tableObject->truncate();
        }

        return $this;
    }

    public function createAllTable(bool $forceCreateTable = false): void
    {
        /**
         * @var TableAbstract $tableObject
         */
        foreach ($this->tables as $k => $tableObject) {
            $tableObject->create($forceCreateTable);
        }
    }

    public static function snowflakePKCallback(): \Closure
    {
        return function () {
            $snowflake = new Snowflake();

            return $snowflake->id();
        };
    }

    public static function makeMethod(array $fieldsSqlMap): string
    {
        $result = [];

        $template = <<<'AAA'

        public function set__U__Field(string $value): static
        {
            $this->setFeildName('__L__', $value);

            return $this;
        }

        public function get__U__Field(): string
        {
            return $this->getFieldName('__L__');
        }

AAA;

        foreach ($fieldsSqlMap as $k => $v) {
            $result[] = strtr($template, [
                "__L__" => $k,
                "__U__" => static::snakeToCamel($k, true),
            ]);
        }

        return implode('', $result);
    }


    public static function makeFieldsSqlMap(string $sql): string
    {
        $re = [];
        preg_match_all('/^\s*`([^`]+)` ([^\r\n]+)/sm', $sql, $result, PREG_SET_ORDER);

        foreach ($result as $k => $v) {
            $re[] = '"' . $v[1] . '"      => "`__FIELD__NAME__` ' . $v[2] . '",';
        }

        return implode(PHP_EOL, $re);
    }

    public static function snakeToCamel(string $string, $capitalizeFirstChar = false): string
    {
        // 将字符串按照下划线分割为数组
        $str = str_replace('_', '', ucwords($string, '_'));

        // 根据需要决定是否首字母大写
        if ($capitalizeFirstChar) {
            return $str;
        } else {
            return lcfirst($str);
        }
    }
}
