<?php

    namespace Coco\tableManager;

    use Coco\logger\Logger;
    use Coco\snowflake\Snowflake;
    use \PDO;
    use think\DbManager;

class TableRegistry
{
    use Logger;

    protected DbManager    $dbManager;
    protected array        $tables      = [];
    protected static array $connections = [];
    protected string       $dbName;

    public function __construct($dbName, $host = '127.0.0.1', $username = 'root', $password = 'root', $port = 3306)
    {
        $config = [
            'default'     => 'db',
            'connections' => [
                'db' => [
                    'type'     => 'mysql',
                    'hostname' => $host,
                    'username' => $username,
                    'password' => $password,
                    'database' => $dbName,
                    'charset'  => 'utf8mb4',
                    'prefix'   => '',
                    'hostport' => $port,
                    'debug'    => true,
                    'params'   => [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                    ],
                ],
            ],
        ];

        $this->dbName    = $dbName;
        $this->dbManager = new DbManager();
        $this->dbManager->setConfig($config);
        $this->dbManager->connect();

        $this->dbManager->execute("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
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

    public function testDbConnect(): bool
    {
        try {
            $this->dbManager->query('select 1');

            return true;
        } catch (\Exception $e) {
            return false;
        }
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

    public function createAllTable(bool $forceCreateTable = false): static
    {
        /**
         * @var TableAbstract $tableObject
         */
        foreach ($this->tables as $k => $tableObject) {
            $tableObject->create($forceCreateTable);
        }

        return $this;
    }

    public function getAllTableStatus(): array
    {
        $data = [];

        /**
         * @var TableAbstract $tableObject
         */
        foreach ($this->tables as $k => $tableObject) {
            $isTableCerated = $tableObject->isTableCerated();

            $data[$tableObject->getName()] = [
                'is_created' => (int)$isTableCerated,
                'count'      => $isTableCerated ? (int)$tableObject->getCount() : 0,
            ];
        }

        return $data;
    }

    public function isAllTablesExists(): bool
    {
        $tableStatus        = $this->getAllTableStatus();
        $isAllTablesCreated = true;

        foreach ($tableStatus as $k => $v) {
            if (!$v['is_created']) {
                $isAllTablesCreated = false;
                break;
            }
        }

        return $isAllTablesCreated;
    }

    public static function snowflakePKCallback(): \Closure
    {
        $snowflake = new Snowflake();

        return function () use ($snowflake) {
            return $snowflake->id();
        };
    }


    public static function sqlToClass(string $sql): string
    {
        $result = static::parseSql($sql);

        $methodTemplate = <<<'AAA'

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

        $classTemplate = <<<'AAA'
<?php

    namespace __NAMESPACE__;

    class __TABLE_NAME__ extends \Coco\tableManager\TableAbstract
    {
        public string $comment = '__COMMENT__';

        public array $fieldsSqlMap = [
            __FIELD__
        ];

        protected array $indexSentence = [
            __INDEX__
        ];

        __METHOD__

    }
AAA;

        $method = [];
        foreach ($result['fields'] as $k => $v) {
            $method[] = strtr($methodTemplate, [
                "__L__" => $k,
                "__U__" => static::snakeToCamel($k, true),
            ]);
        }

        $class = strtr($classTemplate, [
            //"__NAMESPACE__"  => '',
            "__TABLE_NAME__" => static::snakeToCamel($result['table_name'], true),
            "__COMMENT__"    => $result['comment'],
            "__FIELD__"      => static::makeFieldsSqlkv($result['fields']),
            "__INDEX__"      => '',
            "__METHOD__"     => implode('', $method),
        ]);

        return $class;
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

    public static function makeFieldsSqlkv($sqlMap): string
    {
        $re = [];
        foreach ($sqlMap as $k => $v) {
            $re[] = '"' . $k . '"      => "`__FIELD__NAME__` ' . $v . '",';
        }

        return implode(PHP_EOL, $re);
    }

    public static function makeFieldsSqlMap(string $sql): string
    {
        $result = static::parseSql($sql);

        return static::makeFieldsSqlkv($result["fields"]);
    }

    public static function parseSql(string $sql): array
    {
        preg_match_all('/^\s*`([^`]+)` ([^\r\n]+)/ism', $sql, $fields, PREG_SET_ORDER);
        preg_match('/^\s*CREATE\s*TABLE\s*`([^`]+)`/ism', $sql, $tableName);
        preg_match("/COMMENT\s*=\s*'([^']+)'/ism", $sql, $comment);

        $f = [];
        foreach ($fields as $k => $v) {
            $f[$v[1]] = $v[2];
        }

        return [
            "fields"     => $f,
            "table_name" => $tableName[1] ?? '',
            "comment"    => $comment[1] ?? '',
        ];
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
