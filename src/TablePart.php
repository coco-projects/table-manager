<?php

    namespace Coco\tableManager;

    use think\Collection;
    use think\db\Query;

    class TablePart extends TableAbstract
    {
        protected ?string $shardField = null;

        protected bool $allowAcrossQuery = true;

        protected string $defaultQueryMode = PartQueryMode::SINGLE;

        private int $currentTableId = 0;

        private bool $fetchSql = false;

        public function __construct(protected string $name, private int $tableCount = 1)
        {
            if ($tableCount < 1)
            {
                throw new \InvalidArgumentException('tableCount must be greater than 0');
            }
            parent::__construct($name);
        }

        public function __call(string $name, array $arguments)
        {
            /**
             * 稳定收口约束：
             * 1. 这里默认代理到 across() 语义
             * 2. 仅代理“明确支持”的方法
             * 3. 不代理 insert/replace 等写入方法，避免误写跨分表
             */
            $proxyMethods = [
                'field',
                'where',
                'whereOr',
                'whereIn',
                'whereNotIn',
                'whereNull',
                'whereNotNull',
                'whereBetween',
                'whereLike',
                'whereRaw',
                'whereExp',
                'whereColumn',
                'order',
                'orderRaw',
                'limit',
                'page',
                'fetchSql',
                'setAcrossWrapMode',
                'select',
                'find',
                'value',
                'column',
                'count',
                'max',
                'min',
                'sum',
                'avg',
                'paginate',
                'toQuery',
                'toSqlList',
                'update',
                'delete',
            ];

            if (in_array($name, $proxyMethods, true))
            {
                $query = $this->across();

                return $query->{$name}(...$arguments);
            }

            throw new \BadMethodCallException('Method ' . static::class . '::' . $name . ' does not exist or is not allowed in stable mode');
        }

        public function query(): PartQuery
        {
            return $this->newQuery();
        }

        public function newQuery(): PartQuery
        {
            return (new PartQuery($this))->fetchSql($this->fetchSql)->setAcrossWrapMode(true);
        }

        public function byTableId(int $tableId): PartQuery
        {
            return $this->newQuery()->byTableId($tableId);
        }

        public function bySymbol(string|int $symbol): PartQuery
        {
            return $this->newQuery()->bySymbol($symbol);
        }

        public function whereSymbol(string|int $symbol): PartQuery
        {
            return $this->newQuery()->whereSymbol($symbol);
        }

        public function across(): PartQuery
        {
            return $this->newQuery()->across();
        }

        public function partitions(array $tableIds): PartQuery
        {
            return $this->newQuery()->partitions($tableIds);
        }

        public function setUnionCondition(callable $queryCallback): Query
        {
            $proxyQuery = $this->getTableInsById(0);

            call_user_func_array($queryCallback, [
                $proxyQuery,
                $this,
            ]);

            $partQuery = $this->across();
            $this->syncLegacyProxyQueryToPartQuery($proxyQuery, $partQuery);

            return $partQuery->toQuery();
        }

        public function value(string $field, callable $queryCallback): mixed
        {
            $proxyQuery = $this->getTableInsById(0);

            call_user_func_array($queryCallback, [
                $proxyQuery,
                $this,
            ]);

            $partQuery = $this->across();
            $this->syncLegacyProxyQueryToPartQuery($proxyQuery, $partQuery, $field);

            return $partQuery->value($field);
        }

        public function column(string $field, callable $queryCallback): array|string
        {
            $proxyQuery = $this->getTableInsById(0);

            call_user_func_array($queryCallback, [
                $proxyQuery,
                $this,
            ]);

            $partQuery = $this->across();
            $this->syncLegacyProxyQueryToPartQuery($proxyQuery, $partQuery, $field);

            return $partQuery->column($field);
        }

        public function select(callable $queryCallback): Collection|array|string
        {
            $proxyQuery = $this->getTableInsById(0);

            call_user_func_array($queryCallback, [
                $proxyQuery,
                $this,
            ]);

            $partQuery = $this->across();
            $this->syncLegacyProxyQueryToPartQuery($proxyQuery, $partQuery);

            return $partQuery->select();
        }

        public function insertAll(string $symbolField, iterable $data, bool $strict = false): int
        {
            $dataToInsert = [];
            $count        = 0;
            $pkField      = $this->getPkField();

            foreach ($data as $k => $v)
            {
                $row = (array)$v;

                if (!isset($row[$symbolField]))
                {
                    if ($strict)
                    {
                        throw new TablePartException("row index {$k} missing symbolField {$symbolField}");
                    }
                    continue;
                }

                if (!$this->isPkAutoInc() && !array_key_exists($pkField, $row))
                {
                    $pkValue = $this->calcPk();

                    if ($pkValue === null || $pkValue === '')
                    {
                        if ($strict)
                        {
                            throw new TablePartException("row index {$k} missing pk field {$pkField}, and calcPk() returned empty");
                        }
                        continue;
                    }

                    $row[$pkField] = $pkValue;
                }

                $dataToTableId = $this->symbolToTableId($row[$symbolField]);

                $dataToInsert[$dataToTableId][] = $row;
                $count++;
            }

            foreach ($dataToInsert as $dataToTableId => $datas)
            {
                $this->getTableInsById($dataToTableId)->extra('IGNORE')->insertAll($datas);
            }

            return $count;
        }

        public function setFetchSql(bool $fetchSql): static
        {
            $this->fetchSql = $fetchSql;

            return $this;
        }

        public function getFetchSql(): bool
        {
            return $this->fetchSql;
        }

        public function setTableCount(int $tableCount): static
        {
            if ($tableCount < 1)
            {
                throw new \InvalidArgumentException('tableCount must be greater than 0');
            }

            $this->tableCount = $tableCount;

            return $this;
        }

        public function getTableCount(): int
        {
            return $this->tableCount;
        }

        public function setShardField(string $field): static
        {
            $this->shardField = $field;

            return $this;
        }

        public function getShardField(): ?string
        {
            return $this->shardField;
        }

        public function hasShardField(): bool
        {
            return !empty($this->shardField);
        }

        public function assertShardFieldDefined(): void
        {
            if (!$this->hasShardField())
            {
                throw new TablePartException('shardField is not defined');
            }
        }

        public function setAllowAcrossQuery(bool $allowAcrossQuery): static
        {
            $this->allowAcrossQuery = $allowAcrossQuery;

            return $this;
        }

        public function isAllowAcrossQuery(): bool
        {
            return $this->allowAcrossQuery;
        }

        public function setDefaultQueryMode(string $mode): static
        {
            $this->defaultQueryMode = $mode;

            return $this;
        }

        public function getDefaultQueryMode(): string
        {
            return $this->defaultQueryMode;
        }

        public function getAllTableIds(): array
        {
            return range(0, $this->tableCount - 1);
        }

        public function normalizeTableIds(array $tableIds): array
        {
            $result = [];

            foreach ($tableIds as $tableId)
            {
                $tableId = (int)$tableId;

                if ($tableId < 0 || $tableId >= $this->tableCount)
                {
                    throw new TablePartException('invalid table id: ' . $tableId);
                }

                $result[$tableId] = $tableId;
            }

            ksort($result);

            return array_values($result);
        }

        public function getTableNameById(int $id): string
        {
            $this->assertValidTableId($id);

            return $this->buildTableName($id);
        }

        public function insertBySymbol(string|int $symbol, array $data): int|string
        {
            return $this->bySymbol($symbol)->insert($data);
        }

        public function insertGetIdBySymbol(string|int $symbol, array $data): int|string
        {
            return $this->bySymbol($symbol)->insertGetId($data);
        }

        public function saveBySymbol(string|int $symbol, array $data): int
        {
            return (int)$this->bySymbol($symbol)->insert($data);
        }

        /* 根据数值，返回他对应的表实例，并附加上这个symbol条件
         * ---------------------------------------------------------
         * */
        public function getTableInsWithSymbol(string $symbolField, string|int $symbol): Query
        {
            return $this->getTableInsBySymbol($symbol)->where($symbolField, '=', $symbol);
        }

        /* 根据数值，返回他对应的表实例
         * ---------------------------------------------------------
         * */
        public function getTableInsBySymbol(string|int $symbol): Query
        {
            return $this->getTableInsById($this->symbolToTableId($symbol));
        }


        public function insertAllByIdPart($data): int
        {
            return $this->insertAll($this->getPkField(), $data);
        }

        public function insertAllByShardField(iterable $data, bool $strict = false): int
        {
            $this->assertShardFieldDefined();

            return $this->insertAll($this->shardField, $data, $strict);
        }

        public function isTableCreated(): bool
        {
            for ($i = 0; $i < $this->tableCount; $i++)
            {
                try
                {
                    $this->getTableInsById($i)->find();
                }
                catch (\Exception $e)
                {
                    return false;
                }
            }

            return true;
        }

        public function getCount(): int
        {
            $total = 0;
            for ($i = 0; $i < $this->tableCount; $i++)
            {
                $total += $this->getTableInsById($i)->count();
            }

            return $total;
        }

        public function countTableById(int $id): int
        {
            return (int)$this->getTableInsById($id)->count();
        }

        public function drop(): void
        {
            for ($i = 0; $i < $this->tableCount; $i++)
            {
                $this->dropTableById($i);
            }
        }

        public function truncate(): void
        {
            for ($i = 0; $i < $this->tableCount; $i++)
            {
                $this->truncateTableById($i);
            }
        }

        public function create(bool $forceCreate = false): void
        {
            for ($i = 0; $i < $this->tableCount; $i++)
            {
                $this->createTableById($i, $forceCreate);
            }
        }

        public function createTableById(int $id, bool $forceCreate = false): void
        {
            $this->setCurrentTableId($id);

            if ($forceCreate)
            {
                $this->dropTableById($id);
                $this->setCurrentTableId($id);
            }

            $sql = $this->buildCreateSql();
            $this->tableRegistry->logInfo('执行：' . $sql);
            $this->tableRegistry->getDbManager()->execute($sql);
        }

        public function dropTableById(int $id): void
        {
            $this->setCurrentTableId($id);
            $sql = $this->buildDropSql();
            $this->tableRegistry->logInfo('执行：' . $sql);
            $this->tableRegistry->getDbManager()->execute($sql);
        }

        public function truncateTableById(int $id): void
        {
            $this->setCurrentTableId($id);
            $sql = $this->buildTruncateSql();
            $this->tableRegistry->logInfo('执行：' . $sql);
            $this->tableRegistry->getDbManager()->execute($sql);
        }

        public function eachTableIns(callable $callback): static
        {
            for ($i = 0; $i < $this->tableCount; $i++)
            {
                call_user_func_array($callback, [
                    $this->getTableInsById($i),
                ]);
            }

            return $this;
        }

        /**
         * 始终返回 第一个tab，id为0的那个
         * 后面始终使用 PartQuery 查询
         */
        public function tableIns(): Query
        {
            return $this->getTableInsById(0);
        }

        public function getCountCurrent(): int
        {
            return $this->getTableInsById($this->currentTableId)->count();
        }

        public function dropCurrent()
        {
            $sql = $this->buildDropSql();
            $this->tableRegistry->logInfo('执行：' . $sql);

            return $this->tableRegistry->getDbManager()->execute($sql);
        }

        public function truncateCurrent()
        {
            $sql = $this->buildTruncateSql();
            $this->tableRegistry->logInfo('执行：' . $sql);

            return $this->tableRegistry->getDbManager()->execute($sql);
        }

        public function createCurrent(bool $forceCreate = false)
        {
            if ($forceCreate)
            {
                $this->dropTableById($this->getCurrentTableId());
                $this->setCurrentTableId($this->getCurrentTableId());
            }

            $sql = $this->buildCreateSql();
            $this->tableRegistry->logInfo('执行：' . $sql);

            return $this->tableRegistry->getDbManager()->execute($sql);
        }

        public function setCurrentTableId(int $currentTableId): static
        {
            $this->assertValidTableId($currentTableId);

            $this->currentTableId = $currentTableId;

            return $this;
        }

        public function getCurrentTableId(): int
        {
            return $this->currentTableId;
        }

        public function getCurrentTableName(): string
        {
            return $this->buildTableName($this->currentTableId);
        }

        public function setCurrentTableBySymbol(string|int $symbol): static
        {
            $this->setCurrentTableId($this->symbolToTableId($symbol));

            return $this;
        }

        public function tableInsCurrent(): Query
        {
            return $this->getTableInsById($this->currentTableId);
        }

        public function symbolToTableId(string|int $symbol): int
        {
            return $this->stringToInteger($symbol) % $this->tableCount;
        }

        public function routeBySymbol(string|int $symbol): int
        {
            return $this->symbolToTableId($symbol);
        }

        public function getTableInsById(int $id): Query
        {
            $this->assertValidTableId($id);

            return $this->tableRegistry->getDbManager()->table($this->buildTableName($id));
        }


        protected function syncLegacyProxyQueryToPartQuery(Query $proxyQuery, PartQuery $partQuery, ?string $forceField = null): void
        {
            $options = $proxyQuery->getOptions();

            if ($forceField !== null)
            {
                $partQuery->field($forceField);
            }
            elseif (!empty($options['field']))
            {
                $field = $options['field'];

                if (is_array($field))
                {
                    $field = array_keys($field) === range(0, count($field) - 1) ? $field : array_keys($field);
                }

                $partQuery->field($field);
            }

            if (!empty($options['where']))
            {
                foreach ($this->flattenLegacyWhereItems($options['where']) as $item)
                {
                    $type = $item['type'] ?? '';

                    if ($type === 'basic')
                    {
                        $partQuery->where($item['field'], $item['op'], $item['value']);
                        continue;
                    }

                    if ($type === 'in')
                    {
                        $partQuery->whereIn($item['field'], $item['value']);
                        continue;
                    }

                    if ($type === 'not_in')
                    {
                        $partQuery->whereNotIn($item['field'], $item['value']);
                        continue;
                    }

                    if ($type === 'null')
                    {
                        $partQuery->whereNull($item['field']);
                        continue;
                    }

                    if ($type === 'not_null')
                    {
                        $partQuery->whereNotNull($item['field']);
                        continue;
                    }

                    if ($type === 'between')
                    {
                        $partQuery->whereBetween($item['field'], $item['value']);
                        continue;
                    }

                    if ($type === 'like')
                    {
                        $partQuery->whereLike($item['field'], $item['value']);
                        continue;
                    }

                    if ($type === 'raw')
                    {
                        $partQuery->whereRaw($item['sql'], $item['bind'] ?? []);
                    }
                }
            }

            if (!empty($options['order']))
            {
                foreach ($options['order'] as $k => $v)
                {
                    if (is_int($k))
                    {
                        $partQuery->orderRaw((string)$v);
                    }
                    else
                    {
                        $partQuery->order($k, $v);
                    }
                }
            }

            if (isset($options['limit']))
            {
                $limit = (string)$options['limit'];

                if (str_contains($limit, ','))
                {
                    [
                        $offset,
                        $rows,
                    ] = array_map('intval', explode(',', $limit, 2));
                    $partQuery->limit($rows, $offset);
                }
                else
                {
                    $partQuery->limit((int)$limit);
                }
            }

            if (isset($options['page']))
            {
                $page = $options['page'];
                if (is_array($page) && count($page) >= 2)
                {
                    $partQuery->page((int)$page[0], (int)$page[1]);
                }
            }
        }

        protected function flattenLegacyWhereItems(array $whereItems): array
        {
            $result = [];

            $walker = function($items) use (&$walker, &$result) {
                foreach ($items as $item)
                {
                    if (!is_array($item))
                    {
                        continue;
                    }

                    /**
                     * think-orm 常见结构：
                     * ['AND', ['field', '=', value]]
                     * ['AND', ['field', 'IN', [...]]]
                     * [['field', '=', value], ...]
                     */
                    if (count($item) === 2 && is_string($item[0]) && is_array($item[1]))
                    {
                        $walker([$item[1]]);
                        continue;
                    }

                    if (isset($item[0]) && is_array($item[0]) && !isset($item['field']))
                    {
                        $walker($item);
                        continue;
                    }

                    if (isset($item[0]) && is_string($item[0]) && isset($item[1]))
                    {
                        $operator = strtoupper((string)$item[1]);
                        $value    = $item[2] ?? null;

                        if ($operator === 'IN')
                        {
                            $result[] = [
                                'type'  => 'in',
                                'field' => $item[0],
                                'value' => (array)$value,
                            ];
                            continue;
                        }

                        if ($operator === 'NOT IN')
                        {
                            $result[] = [
                                'type'  => 'not_in',
                                'field' => $item[0],
                                'value' => (array)$value,
                            ];
                            continue;
                        }

                        if ($operator === 'NULL')
                        {
                            $result[] = [
                                'type'  => 'null',
                                'field' => $item[0],
                            ];
                            continue;
                        }

                        if ($operator === 'NOT NULL')
                        {
                            $result[] = [
                                'type'  => 'not_null',
                                'field' => $item[0],
                            ];
                            continue;
                        }

                        if ($operator === 'BETWEEN')
                        {
                            $result[] = [
                                'type'  => 'between',
                                'field' => $item[0],
                                'value' => (array)$value,
                            ];
                            continue;
                        }

                        if ($operator === 'LIKE')
                        {
                            $result[] = [
                                'type'  => 'like',
                                'field' => $item[0],
                                'value' => (string)$value,
                            ];
                            continue;
                        }

                        if ($operator === 'EXP')
                        {
                            $result[] = [
                                'type' => 'raw',
                                'sql'  => '`' . $item[0] . '` ' . (string)$value,
                                'bind' => [],
                            ];
                            continue;
                        }

                        $result[] = [
                            'type'  => 'basic',
                            'field' => $item[0],
                            'op'    => $item[1],
                            'value' => $value,
                        ];

                        continue;
                    }

                    if (isset($item['field']) && isset($item['op']))
                    {
                        $operator = strtoupper((string)$item['op']);

                        if ($operator === 'IN')
                        {
                            $result[] = [
                                'type'  => 'in',
                                'field' => $item['field'],
                                'value' => (array)($item['value'] ?? []),
                            ];
                            continue;
                        }

                        $result[] = [
                            'type'  => 'basic',
                            'field' => $item['field'],
                            'op'    => $item['op'],
                            'value' => $item['value'] ?? null,
                        ];

                        continue;
                    }

                    $walker($item);
                }
            };

            $walker($whereItems);

            return $result;
        }

        protected function getBuildTableName(): string
        {
            return $this->getCurrentTableName();
        }

        private function buildTableName(int $id): string
        {
            return $this->name . '_' . $id;
        }

        private function stringToInteger(string|int $string): int
        {
            return (int)sprintf('%u', crc32((string)$string));
        }

        private function assertValidTableId(int $id): void
        {
            if ($id < 0 || $id >= $this->tableCount)
            {
                throw new TablePartException('invalid table id: ' . $id);
            }
        }
    }