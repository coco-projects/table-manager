<?php

    namespace Coco\tableManager;

    use think\Collection;
    use think\db\Query;

    class PartQuery
    {
        protected TablePart $tablePart;

        protected array $options = [];

        protected string $mode = PartQueryMode::SINGLE;

        protected ?int $tableId = null;

        protected array $tableIds = [];

        protected bool $fetchSql = false;

        protected array $field = [];

        protected bool $fieldForced = false;

        protected array $whereList = [];

        protected array $whereRawList = [];

        protected array $orderList = [];

        protected array $orderRawList = [];

        protected ?int $limitRows = null;

        protected ?int $offsetRows = null;

        protected ?int $pageNo = null;

        protected ?int $pageListRows = null;

        protected bool $distinct = false;

        protected array $groupList = [];

        protected array $havingRawList = [];

        protected bool $autoWhereShardField = false;

        protected string|int|null $symbol = null;

        public function distinct(bool $distinct = true): static
        {
            if ($distinct)
            {
                throw new TablePartException('distinct is not supported in current stable mode');
            }

            $this->distinct = false;

            return $this;
        }

        public function group(array|string $field): static
        {
            throw new TablePartException('group is not supported in current stable mode');
        }

        public function having(string $sql, array $bind = []): static
        {
            throw new TablePartException('having is not supported in current stable mode');
        }

        public function orderRaw(string $sql): static
        {
            $normalized = strtoupper(trim($sql));

            /**
             * 稳定收口：
             * 1. 禁止 RAND() 这类跨分表全局随机排序
             * 2. 原始 orderRaw 保留能力，但只建议单分片使用
             */
            if (str_contains($normalized, 'RAND(') || str_contains($normalized, 'RAND()'))
            {
                throw new TablePartException('orderRaw RAND() is not supported in current stable mode');
            }

            $this->orderRawList[] = $sql;

            return $this;
        }

        public function whereRaw(string $sql, array $bind = []): static
        {
            if (trim($sql) === '')
            {
                throw new TablePartException('whereRaw sql cannot be empty');
            }

            $this->whereRawList[] = [
                'sql'  => $sql,
                'bind' => $bind,
            ];

            return $this;
        }

        public function whereSymbol(string|int $symbol): static
        {
            $this->symbol              = $symbol;
            $this->autoWhereShardField = true;

            if ($this->tablePart->hasShardField())
            {
                $this->validateSymbolValue($symbol);
            }

            return $this->single($this->tablePart->symbolToTableId($symbol));
        }

        public function where(mixed $field, ?string $op = null, mixed $condition = null): static
        {
            if ($field instanceof \Closure)
            {
                throw new TablePartException('closure where is not supported in current stable mode');
            }

            if (is_array($field) && $op === null)
            {
                $this->whereList[] = [
                    'type'  => 'array',
                    'field' => $field,
                    'op'    => null,
                    'value' => null,
                ];

                return $this;
            }

            if ($condition === null && $op !== null)
            {
                $condition = $op;
                $op        = '=';
            }

            $this->assertShardFieldValueConsistency($field, $condition);

            $this->whereList[] = [
                'type'  => 'basic',
                'field' => $field,
                'op'    => $op ?? '=',
                'value' => $condition,
            ];

            return $this;
        }

        public function whereOr(mixed $field, ?string $op = null, mixed $condition = null): static
        {
            if ($field instanceof \Closure)
            {
                throw new TablePartException('closure whereOr is not supported in current stable mode');
            }

            if (is_array($field) && $op === null)
            {
                $this->whereList[] = [
                    'type'  => 'array',
                    'logic' => 'OR',
                    'field' => $field,
                    'op'    => null,
                    'value' => null,
                ];

                return $this;
            }

            if ($condition === null && $op !== null)
            {
                $condition = $op;
                $op        = '=';
            }

            $this->assertShardFieldValueConsistency($field, $condition);

            $this->whereList[] = [
                'type'  => 'basic',
                'logic' => 'OR',
                'field' => $field,
                'op'    => $op ?? '=',
                'value' => $condition,
            ];

            return $this;
        }

        public function whereIn(string $field, array $values): static
        {
            $this->assertShardFieldInConsistency($field, $values);

            $this->whereList[] = [
                'type'  => 'in',
                'field' => $field,
                'value' => $values,
            ];

            return $this;
        }

        public function update(array $data): int|array
        {
            $this->ensureSafeUpdateOrDelete();

            if ($this->isSingleMode())
            {
                $query = $this->buildSingleQuery($this->resolveSingleTableId());

                return (int)$query->fetchSql($this->fetchSql)->update($data);
            }

            if ($this->fetchSql)
            {
                return $this->toSqlList('update', $data);
            }

            $total = 0;
            foreach ($this->resolveTableIds() as $tableId)
            {
                $query = $this->buildShardBaseQuery($tableId);
                $total += (int)$query->update($data);
            }

            return $total;
        }

        public function delete(): int|array
        {
            $this->ensureSafeUpdateOrDelete();

            if ($this->isSingleMode())
            {
                $query = $this->buildSingleQuery($this->resolveSingleTableId());

                return (int)$query->fetchSql($this->fetchSql)->delete();
            }

            if ($this->fetchSql)
            {
                return $this->toSqlList('delete');
            }

            $total = 0;
            foreach ($this->resolveTableIds() as $tableId)
            {
                $query = $this->buildShardBaseQuery($tableId);
                $total += (int)$query->delete();
            }

            return $total;
        }

        public function insert(array $data): int|string
        {
            $this->ensureSingleModeForWrite();

            $data = $this->prepareInsertData($data);

            $query = $this->tablePart->getTableInsById($this->resolveSingleTableId());

            return $query->fetchSql($this->fetchSql)->insert($data);
        }

        public function insertGetId(array $data): int|string
        {
            $this->ensureSingleModeForWrite();

            $data = $this->prepareInsertData($data);

            $query = $this->tablePart->getTableInsById($this->resolveSingleTableId());

            if (!$this->tablePart->isPkAutoInc())
            {
                $pkField = $this->tablePart->getPkField();

                $query->fetchSql($this->fetchSql)->insert($data);

                return $data[$pkField];
            }

            return $query->fetchSql($this->fetchSql)->insertGetId($data);
        }

        public function insertOrIgnore(array $data): int|string
        {
            $this->ensureSingleModeForWrite();

            $data = $this->prepareInsertData($data);

            $query = $this->tablePart->getTableInsById($this->resolveSingleTableId())->extra('IGNORE');

            return $query->fetchSql($this->fetchSql)->insert($data);
        }

        public function replace(array $data): int|string
        {
            $this->ensureSingleModeForWrite();

            $data = $this->prepareInsertData($data);

            $tableName = $this->tablePart->getTableNameById($this->resolveSingleTableId());
            $dbManager = $this->tablePart->getTableRegistry()->getDbManager();

            $fields  = array_keys($data);
            $columns = implode(',', array_map(function($v) {
                return "`{$v}`";
            }, $fields));

            $values = implode(',', array_map(function($v) {
                if ($v === null)
                {
                    return 'NULL';
                }

                if (is_int($v) || is_float($v))
                {
                    return (string)$v;
                }

                return "'" . addslashes((string)$v) . "'";
            }, array_values($data)));

            $sql = "REPLACE INTO `{$tableName}` ({$columns}) VALUES ({$values})";

            if ($this->fetchSql)
            {
                return $sql;
            }

            return $dbManager->execute($sql);
        }

        public function toSqlList(?string $action = null, array $data = []): array
        {
            $sqlList = [];

            if ($this->isSingleMode())
            {
                $query = $this->buildSingleQuery($this->resolveSingleTableId());

                if ($action === 'update')
                {
                    $sqlList[] = $query->fetchSql(true)->update($data);
                }
                elseif ($action === 'delete')
                {
                    $sqlList[] = $query->fetchSql(true)->delete();
                }
                else
                {
                    $sqlList[] = $query->fetchSql(true)->select();
                }

                return $sqlList;
            }

            foreach ($this->resolveTableIds() as $tableId)
            {
                $query = $this->buildSingleQuery($tableId);

                if ($action === 'update')
                {
                    $sqlList[] = $query->fetchSql(true)->update($data);
                }
                elseif ($action === 'delete')
                {
                    $sqlList[] = $query->fetchSql(true)->delete();
                }
                else
                {
                    $sqlList[] = $query->fetchSql(true)->select();
                }
            }

            return $sqlList;
        }

        public function count(string $field = '*'): int|string|array
        {
            if ($this->isSingleMode())
            {
                $query = $this->buildSingleQuery($this->resolveSingleTableId());

                return $query->fetchSql($this->fetchSql)->count($field);
            }

            if ($this->fetchSql)
            {
                $partSql = $this->buildAcrossCountQuery($field)->fetchSql(true)->select();

                return [
                    'part_sql'  => $partSql,
                    'total_sql' => $this->buildAcrossWrappedTotalSql('SUM', 'c', $partSql),
                ];
            }

            $total = 0;
            foreach ($this->resolveTableIds() as $tableId)
            {
                $query = $this->buildShardBaseQuery($tableId);
                $total += (int)$query->count($field);
            }

            return $total;
        }

        public function max(string $field): mixed
        {
            if ($this->isSingleMode())
            {
                return $this->buildSingleQuery($this->resolveSingleTableId())->fetchSql($this->fetchSql)->max($field);
            }

            if ($this->fetchSql)
            {
                $partSql = $this->buildAcrossAggregateQuery('MAX', $field, 'agg_value')->fetchSql(true)->select();

                return [
                    'part_sql'  => $partSql,
                    'total_sql' => $this->buildAcrossWrappedTotalSql('MAX', 'agg_value', $partSql),
                ];
            }

            $value = null;
            foreach ($this->resolveTableIds() as $tableId)
            {
                $query     = $this->buildShardBaseQuery($tableId);
                $partCount = (int)$query->count($field);

                if ($partCount <= 0)
                {
                    continue;
                }

                $current = $query->max($field);

                if ($current === null || $current === '')
                {
                    continue;
                }

                if ($value === null || $current > $value)
                {
                    $value = $current;
                }
            }

            return $value;
        }

        public function min(string $field): mixed
        {
            if ($this->isSingleMode())
            {
                return $this->buildSingleQuery($this->resolveSingleTableId())->fetchSql($this->fetchSql)->min($field);
            }

            if ($this->fetchSql)
            {
                $partSql = $this->buildAcrossAggregateQuery('MIN', $field, 'agg_value')->fetchSql(true)->select();

                return [
                    'part_sql'  => $partSql,
                    'total_sql' => $this->buildAcrossWrappedTotalSql('MIN', 'agg_value', $partSql),
                ];
            }

            $value = null;
            foreach ($this->resolveTableIds() as $tableId)
            {
                $query     = $this->buildShardBaseQuery($tableId);
                $partCount = (int)$query->count($field);

                if ($partCount <= 0)
                {
                    continue;
                }

                $current = $query->min($field);

                if ($current === null || $current === '')
                {
                    continue;
                }

                if ($value === null || $current < $value)
                {
                    $value = $current;
                }
            }

            return $value;
        }

        public function sum(string $field): mixed
        {
            if ($this->isSingleMode())
            {
                return $this->buildSingleQuery($this->resolveSingleTableId())->fetchSql($this->fetchSql)->sum($field);
            }

            if ($this->fetchSql)
            {
                $partSql = $this->buildAcrossAggregateQuery('SUM', $field, 'agg_value')->fetchSql(true)->select();

                return [
                    'part_sql'  => $partSql,
                    'total_sql' => $this->buildAcrossWrappedTotalSql('SUM', 'agg_value', $partSql),
                ];
            }

            $value = 0.0;
            foreach ($this->resolveTableIds() as $tableId)
            {
                $query     = $this->buildShardBaseQuery($tableId);
                $partCount = (int)$query->count($field);

                if ($partCount <= 0)
                {
                    continue;
                }

                $current = $query->sum($field);

                if ($current === null || $current === '')
                {
                    continue;
                }

                $value += (float)$current;
            }

            return $value;
        }

        public function avg(string $field): mixed
        {
            if ($this->isSingleMode())
            {
                return $this->buildSingleQuery($this->resolveSingleTableId())->fetchSql($this->fetchSql)->avg($field);
            }

            if ($this->fetchSql)
            {
                $partSql = $this->buildAcrossAggregateQuery('AVG', $field, 'agg_value')->fetchSql(true)->select();

                return [
                    'part_sql'  => $partSql,
                    'total_sql' => $this->buildAcrossWrappedTotalSql('AVG', 'agg_value', $partSql),
                ];
            }

            $sum   = 0.0;
            $count = 0;

            foreach ($this->resolveTableIds() as $tableId)
            {
                $query = $this->buildShardBaseQuery($tableId);

                $partCount = (int)$query->count($field);
                if ($partCount <= 0)
                {
                    continue;
                }

                $partSum = $query->sum($field);

                if ($partSum !== null && $partSum !== '')
                {
                    $sum += (float)$partSum;
                }

                $count += $partCount;
            }

            if ($count === 0)
            {
                return 0;
            }

            return $sum / $count;
        }

        public function whereExp(string $field, string $expression): static
        {
            $this->whereList[] = [
                'type'  => 'exp',
                'field' => $field,
                'value' => $expression,
            ];

            return $this;
        }

        public function whereColumn(string $field1, string $op, ?string $field2 = null): static
        {
            if ($field2 === null)
            {
                $field2 = $op;
                $op     = '=';
            }

            $this->whereList[] = [
                'type'   => 'column',
                'field1' => $field1,
                'op'     => $op,
                'field2' => $field2,
            ];

            return $this;
        }

        public function paginate(int $page, int $listRows = 15): array|string
        {
            $query = $this->clone()->page($page, $listRows);

            if (!$query->isSingleMode())
            {
                $query->ensureAcrossOrderFieldsSelected();
            }

            if ($query->fetchSql)
            {
                $countSql = $query->clone()->fetchSql(true)->count();

                return [
                    'count_sql' => $countSql,
                    'list_sql'  => $query->clone()->fetchSql(true)->select(),
                ];
            }

            $total = (int)$query->clone()->count();
            $list  = $query->select();

            if ($list instanceof Collection)
            {
                $list = $list->toArray();
            }

            return [
                'total'        => $total,
                'per_page'     => $listRows,
                'current_page' => $page,
                'last_page'    => $listRows > 0 ? (int)ceil($total / $listRows) : 0,
                'data'         => $list,
            ];
        }

        public function bySymbol(string|int $symbol): static
        {
            $this->symbol = $symbol;

            if ($this->tablePart->hasShardField())
            {
                $this->validateSymbolValue($symbol);
            }

            return $this->single($this->tablePart->symbolToTableId($symbol));
        }

        public function find(): array|object|null|string
        {
            if ($this->pageNo !== null)
            {
                $this->limit(1, (($this->pageNo - 1) * ($this->pageListRows ?? 15)));
            }
            elseif ($this->limitRows === null)
            {
                $this->limit(1);
            }

            if ($this->isSingleMode())
            {
                $query = $this->buildSingleQuery($this->resolveSingleTableId());

                return $query->fetchSql($this->fetchSql)->find();
            }

            $query = $this->buildAcrossFinalQuery();

            return $query->fetchSql($this->fetchSql)->find();
        }

        public function value(string $field): mixed
        {
            $query = $this->clone()->field($field);

            if ($query->pageNo !== null)
            {
                $query->limit(1, (($query->pageNo - 1) * ($query->pageListRows ?? 15)));
            }
            elseif ($query->limitRows === null)
            {
                $query->limit(1);
            }

            if ($query->isSingleMode())
            {
                $ins = $query->buildSingleQuery($query->resolveSingleTableId());

                return $ins->fetchSql($query->fetchSql)->value($field);
            }

            $safeQuery = $query->prepareAcrossSelectField($field);
            $safeQuery->ensureAcrossOrderFieldsSelected();

            $ins = $safeQuery->buildAcrossFinalQuery();

            return $ins->fetchSql($safeQuery->fetchSql)->value($field);
        }

        public function column(string $field, ?string $key = null): array|string
        {
            $query = $this->clone()->field($field);

            if ($query->isSingleMode())
            {
                $ins = $query->buildSingleQuery($query->resolveSingleTableId());

                if ($query->fetchSql)
                {
                    return $key !== null ? $ins->fetchSql(true)->column($field, $key) : $ins->fetchSql(true)
                        ->column($field, '');
                }

                return $key !== null ? $ins->column($field, $key) : $ins->column($field);
            }

            $safeQuery = $query->prepareAcrossSelectField($field);
            $safeQuery->ensureAcrossOrderFieldsSelected();

            if ($key !== null)
            {
                $safeQuery->appendField($key);
            }

            $ins = $safeQuery->buildAcrossFinalQuery();

            if ($safeQuery->fetchSql)
            {
                return $key !== null ? $ins->fetchSql(true)->column($field, $key) : $ins->fetchSql(true)
                    ->column($field, '');
            }

            return $key !== null ? $ins->column($field, $key) : $ins->column($field);
        }

        public function setAcrossWrapMode(bool $enabled = true): static
        {
            $this->options['across_wrap_mode'] = $enabled;

            return $this;
        }

        public function isAcrossWrapMode(): bool
        {
            return (bool)($this->options['across_wrap_mode'] ?? false);
        }

        public function __construct(TablePart $tablePart)
        {
            $this->tablePart = $tablePart;
        }

        public function fetchSql(bool $fetchSql = true): static
        {
            $this->fetchSql = $fetchSql;

            return $this;
        }

        public function select(): Collection|array|string
        {
            if ($this->isSingleMode())
            {
                $query = $this->buildSingleQuery($this->resolveSingleTableId());

                return $query->fetchSql($this->fetchSql)->select();
            }

            $query = $this->buildAcrossFinalQuery();

            return $query->fetchSql($this->fetchSql)->select();
        }

        public function toQuery(): Query
        {
            if ($this->isSingleMode())
            {
                return $this->buildSingleQuery($this->resolveSingleTableId());
            }

            return $this->buildAcrossFinalQuery();
        }

        public function insertAll(iterable $data): int
        {
            $this->ensureSingleModeForWrite();

            $rows = [];
            foreach ($data as $item)
            {
                $rows[] = $this->prepareInsertData((array)$item);
            }

            if (!$rows)
            {
                return 0;
            }

            $query = $this->tablePart->getTableInsById($this->resolveSingleTableId());

            return (int)$query->fetchSql($this->fetchSql)->insertAll($rows);
        }

        public function clone(): static
        {
            return clone $this;
        }

        public function single(int $tableId): static
        {
            $this->mode     = PartQueryMode::SINGLE;
            $this->tableId  = $tableId;
            $this->tableIds = [];

            return $this;
        }

        public function byTableId(int $tableId): static
        {
            return $this->single($tableId);
        }

        public function across(): static
        {
            if (!$this->tablePart->isAllowAcrossQuery())
            {
                throw new TablePartException('across query is not allowed');
            }

            $this->mode     = PartQueryMode::ACROSS;
            $this->tableId  = null;
            $this->tableIds = [];

            return $this;
        }

        public function partitions(array $tableIds): static
        {
            $this->mode     = PartQueryMode::PARTITIONS;
            $this->tableId  = null;
            $this->tableIds = $this->tablePart->normalizeTableIds($tableIds);

            return $this;
        }

        public function getMode(): string
        {
            return $this->mode;
        }

        public function getTableId(): ?int
        {
            return $this->tableId;
        }

        public function getTableIds(): array
        {
            return $this->tableIds;
        }

        public function getResolvedTableIds(): array
        {
            return $this->resolveTableIds();
        }

        public function isFetchSql(): bool
        {
            return $this->fetchSql;
        }

        public function field(array|string $field): static
        {
            $this->fieldForced = true;

            if (is_array($field))
            {
                $this->field = $field;
            }
            else
            {
                $field = trim($field);

                if ($field === '*')
                {
                    $this->field = ['*'];
                }
                else
                {
                    $this->field = array_map('trim', explode(',', $field));
                }
            }

            return $this;
        }

        public function whereNotIn(string $field, array $values): static
        {
            $this->whereList[] = [
                'type'  => 'not_in',
                'field' => $field,
                'value' => $values,
            ];

            return $this;
        }

        public function whereNull(string $field): static
        {
            $this->whereList[] = [
                'type'  => 'null',
                'field' => $field,
            ];

            return $this;
        }

        public function whereNotNull(string $field): static
        {
            $this->whereList[] = [
                'type'  => 'not_null',
                'field' => $field,
            ];

            return $this;
        }

        public function whereBetween(string $field, array $range): static
        {
            $this->whereList[] = [
                'type'  => 'between',
                'field' => $field,
                'value' => $range,
            ];

            return $this;
        }

        public function whereLike(string $field, string $value): static
        {
            $this->whereList[] = [
                'type'  => 'like',
                'field' => $field,
                'value' => $value,
            ];

            return $this;
        }

        public function order(array|string $field, ?string $direction = null): static
        {
            $this->orderList[] = [
                'field'     => $field,
                'direction' => $direction,
            ];

            return $this;
        }

        public function limit(int $limit, ?int $offset = null): static
        {
            $this->limitRows  = $limit;
            $this->offsetRows = $offset;

            return $this;
        }

        public function page(int $page, int $listRows = 15): static
        {
            $this->pageNo       = $page;
            $this->pageListRows = $listRows;

            return $this;
        }

        public function buildAcrossQueryPublicFromCallback(callable $queryCallback): Query
        {
            $proxyQuery = $this->tablePart->getTableInsById(0);

            call_user_func_array($queryCallback, [
                $proxyQuery,
                $this->tablePart,
            ]);

            $this->syncProxyQueryToCurrentPartQuery($proxyQuery);

            return $this->buildAcrossQuery();
        }

        protected function syncProxyQueryToCurrentPartQuery(Query $proxyQuery): void
        {
            $options = $proxyQuery->getOptions();

            if (!empty($options['field']))
            {
                $field = $options['field'];

                if (is_array($field))
                {
                    $field = array_keys($field) === range(0, count($field) - 1) ? $field : array_keys($field);
                }

                $this->field($field);
            }

            if (!empty($options['where']))
            {
                foreach ($options['where'] as $item)
                {
                    if (isset($item[0]) && is_string($item[0]) && isset($item[1]))
                    {
                        if (strtoupper((string)$item[1]) === 'IN' && isset($item[2]))
                        {
                            $this->whereIn($item[0], (array)$item[2]);
                            continue;
                        }

                        $this->where($item[0], $item[1], $item[2] ?? null);
                    }
                }
            }

            if (!empty($options['order']))
            {
                foreach ($options['order'] as $k => $v)
                {
                    if (is_int($k))
                    {
                        $this->orderRaw((string)$v);
                    }
                    else
                    {
                        $this->order($k, $v);
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
                    $this->limit($rows, $offset);
                }
                else
                {
                    $this->limit((int)$limit);
                }
            }

            if (isset($options['page']))
            {
                $page = $options['page'];
                if (is_array($page) && count($page) >= 2)
                {
                    $this->page((int)$page[0], (int)$page[1]);
                }
            }
        }

        protected function isSingleMode(): bool
        {
            return $this->mode === PartQueryMode::SINGLE;
        }

        protected function resolveSingleTableId(): int
        {
            if ($this->tableId === null)
            {
                throw new TablePartException('single query mode requires tableId, please call bySymbol() / byTableId() first');
            }

            return $this->tableId;
        }

        protected function resolveTableIds(): array
        {
            if ($this->mode === PartQueryMode::SINGLE)
            {
                return [$this->resolveSingleTableId()];
            }

            if ($this->mode === PartQueryMode::ACROSS)
            {
                return $this->tablePart->getAllTableIds();
            }

            if ($this->mode === PartQueryMode::PARTITIONS)
            {
                return $this->tablePart->normalizeTableIds($this->tableIds);
            }

            throw new TablePartException('unsupported query mode: ' . $this->mode);
        }

        protected function buildSingleQuery(int $tableId): Query
        {
            $query = $this->buildShardBaseQuery($tableId);

            $query = $this->applySingleQueryOptions($query);

            return $query;
        }

        protected function ensureSafeUpdateOrDelete(): void
        {
            if (!$this->whereList && !$this->whereRawList && !$this->autoWhereShardField)
            {
                throw new TablePartException('unsafe update/delete without where condition is not allowed');
            }
        }

        protected function validateSymbolValue(string|int $symbol): void
        {
            if ($symbol === '')
            {
                throw new TablePartException('symbol cannot be empty string');
            }
        }

        protected function assertShardFieldValueConsistency(mixed $field, mixed $condition): void
        {
            if (!$this->autoWhereShardField)
            {
                return;
            }

            $shardField = $this->tablePart->getShardField();
            if (!$shardField)
            {
                return;
            }

            if ((string)$field !== (string)$shardField)
            {
                return;
            }

            if ((string)$condition !== (string)$this->symbol)
            {
                throw new TablePartException("shardField condition conflict: whereSymbol({$this->symbol}) but where({$shardField}) = {$condition}");
            }
        }

        protected function assertShardFieldInConsistency(string $field, array $values): void
        {
            if (!$this->autoWhereShardField)
            {
                return;
            }

            $shardField = $this->tablePart->getShardField();
            if (!$shardField)
            {
                return;
            }

            if ((string)$field !== (string)$shardField)
            {
                return;
            }

            $stringValues = array_map('strval', $values);

            if (!in_array((string)$this->symbol, $stringValues, true))
            {
                throw new TablePartException("shardField IN condition conflict: whereSymbol({$this->symbol}) but {$shardField} not in given values");
            }
        }

        protected function buildAcrossWrappedTotalSql(string $aggregateFunction, string $fieldAlias, ?string $partSql = null): string
        {
            $aggregateFunction = strtoupper(trim($aggregateFunction));
            $fieldAlias        = trim($fieldAlias);

            if ($aggregateFunction === '' || $fieldAlias === '')
            {
                throw new TablePartException('invalid aggregate function or field alias');
            }

            if ($partSql === null || $partSql === '')
            {
                throw new TablePartException('partSql cannot be empty');
            }

            if ($aggregateFunction === 'SUM' && $fieldAlias === 'c')
            {
                return "SELECT SUM(c) AS total_value FROM ({$partSql}) `__part_total__`";
            }

            if ($aggregateFunction === 'MAX')
            {
                return "SELECT MAX({$fieldAlias}) AS total_value FROM ({$partSql}) `__part_total__`";
            }

            if ($aggregateFunction === 'MIN')
            {
                return "SELECT MIN({$fieldAlias}) AS total_value FROM ({$partSql}) `__part_total__`";
            }

            if ($aggregateFunction === 'SUM')
            {
                return "SELECT SUM({$fieldAlias}) AS total_value FROM ({$partSql}) `__part_total__`";
            }

            if ($aggregateFunction === 'AVG')
            {
                return "SELECT AVG({$fieldAlias}) AS total_value FROM ({$partSql}) `__part_total__`";
            }

            return "SELECT {$aggregateFunction}({$fieldAlias}) AS total_value FROM ({$partSql}) `__part_total__`";
        }

        protected function applySingleQueryOptions(Query $query): Query
        {
            foreach ($this->orderList as $order)
            {
                $query->order($order['field'], $order['direction']);
            }

            foreach ($this->orderRawList as $orderRaw)
            {
                $query->orderRaw($orderRaw);
            }

            if ($this->pageNo !== null)
            {
                $query->page($this->pageNo, $this->pageListRows ?? 15);
            }
            elseif ($this->limitRows !== null)
            {
                if ($this->offsetRows !== null)
                {
                    $query->limit($this->offsetRows, $this->limitRows);
                }
                else
                {
                    $query->limit($this->limitRows);
                }
            }

            return $query;
        }

        protected function ensureSingleModeForWrite(): void
        {
            if (!$this->isSingleMode())
            {
                throw new TablePartException('write operation only allowed in single mode');
            }
        }

        protected function prepareInsertData(array $data): array
        {
            $pkField = $this->tablePart->getPkField();

            if (!$this->tablePart->isPkAutoInc() && !array_key_exists($pkField, $data))
            {
                $pkValue = $this->tablePart->calcPk();

                if ($pkValue === null || $pkValue === '')
                {
                    throw new TablePartException("primary key field `{$pkField}` is required, calcPk() returned empty");
                }

                $data[$pkField] = $pkValue;
            }

            return $data;
        }

        protected function buildAggregateQuery(string $functionName, string $field, string $alias): Query
        {
            $query = $this->buildAcrossQuery();
            $query->field($functionName . '(' . $field . ') as ' . $alias);

            return $query;
        }

        protected function normalizeFieldForQuery(array $field): array|string
        {
            if (count($field) === 1)
            {
                return $field[0];
            }

            return $field;
        }

        protected function shouldApplyAcrossOrder(): bool
        {
            if (!$this->orderList && !$this->orderRawList)
            {
                return false;
            }

            if (!$this->fieldForced || !$this->field)
            {
                return true;
            }

            if (in_array('*', $this->field, true))
            {
                return true;
            }

            $selectedFields = array_map(function($v) {
                return trim((string)$v, " \t\n\r\0\x0B`");
            }, $this->field);

            foreach ($this->orderList as $order)
            {
                $orderField = $order['field'];

                if (is_array($orderField))
                {
                    foreach ($orderField as $k => $v)
                    {
                        $f = is_int($k) ? $v : $k;
                        $f = trim((string)$f, " \t\n\r\0\x0B`");
                        if (!in_array($f, $selectedFields, true))
                        {
                            return false;
                        }
                    }

                    continue;
                }

                $orderField = trim((string)$orderField, " \t\n\r\0\x0B`");
                if (!in_array($orderField, $selectedFields, true))
                {
                    return false;
                }
            }

            /**
             * orderRaw 无法安全分析，保守处理：
             * 只要有 orderRaw，并且字段不是 *，就不自动加到跨分表 union 上，
             * 避免类似 select path order by id 这种 SQL 报错。
             */
            if ($this->orderRawList)
            {
                return false;
            }

            return true;
        }

        protected function prepareAcrossSelectField(string $field): static
        {
            $query = $this->clone();

            if (!$query->fieldForced || !$query->field)
            {
                $query->field($field);
            }

            if (!$query->fieldContains($field))
            {
                $query->appendField($field);
            }

            return $query;
        }

        protected function buildAcrossFinalQuery(): Query
        {
            if (!$this->isAcrossWrapMode())
            {
                return $this->buildAcrossQuery();
            }

            $needWrap = $this->shouldWrapAcrossUnion();

            if (!$needWrap)
            {
                return $this->buildAcrossQuery();
            }

            $baseQuery = $this->clone();
            $baseQuery->clearAcrossOuterOptions();

            $unionSql = $baseQuery->buildAcrossQuery()->fetchSql(true)->select();
            $unionSql = '(' . $unionSql . ')';

            $query = $this->tablePart->getTableRegistry()->getDbManager()->table([$unionSql => '__part_union__']);

            if ($this->fieldForced && $this->field)
            {
                $query->field($this->normalizeFieldForOuterQuery($this->field));
            }
            else
            {
                $query->field('*');
            }

            foreach ($this->orderList as $order)
            {
                $query->order($order['field'], $order['direction']);
            }

            foreach ($this->orderRawList as $orderRaw)
            {
                $query->orderRaw($orderRaw);
            }

            if ($this->pageNo !== null)
            {
                $query->page($this->pageNo, $this->pageListRows ?? 15);
            }
            elseif ($this->limitRows !== null)
            {
                if ($this->offsetRows !== null)
                {
                    $query->limit($this->offsetRows, $this->limitRows);
                }
                else
                {
                    $query->limit($this->limitRows);
                }
            }

            return $query;
        }

        protected function shouldWrapAcrossUnion(): bool
        {
            if ($this->isSingleMode())
            {
                return false;
            }

            if ($this->orderList || $this->orderRawList)
            {
                return true;
            }

            if ($this->pageNo !== null)
            {
                return true;
            }

            if ($this->limitRows !== null)
            {
                return true;
            }

            return false;
        }

        protected function clearAcrossOuterOptions(): void
        {
            $this->orderList    = [];
            $this->orderRawList = [];
            $this->pageNo       = null;
            $this->pageListRows = null;
            $this->limitRows    = null;
            $this->offsetRows   = null;
        }

        protected function normalizeFieldForOuterQuery(array $field): array|string
        {
            if (count($field) === 1)
            {
                return $field[0];
            }

            return $field;
        }

        protected function buildAcrossQuery(): Query
        {
            $tableIds = $this->resolveTableIds();
            if (!$tableIds)
            {
                throw new TablePartException('resolved table ids is empty');
            }

            $unionQuery = null;

            foreach ($tableIds as $index => $tableId)
            {
                if ($index === 0)
                {
                    $unionQuery = $this->buildShardBaseQuery($tableId);
                    continue;
                }

                $unionQuery = $unionQuery->unionAll(function(Query $query) use ($tableId) {
                    $query->table($this->tablePart->getTableNameById($tableId));
                    $this->applyShardBaseQueryOptionsToUnionSubQuery($query);
                });
            }

            if ($this->shouldApplyAcrossOrder())
            {
                foreach ($this->orderList as $order)
                {
                    $unionQuery->order($order['field'], $order['direction']);
                }

                foreach ($this->orderRawList as $orderRaw)
                {
                    $unionQuery->orderRaw($orderRaw);
                }
            }

            if ($this->pageNo !== null)
            {
                $unionQuery->page($this->pageNo, $this->pageListRows ?? 15);
            }
            elseif ($this->limitRows !== null)
            {
                if ($this->offsetRows !== null)
                {
                    $unionQuery->limit($this->offsetRows, $this->limitRows);
                }
                else
                {
                    $unionQuery->limit($this->limitRows);
                }
            }

            return $unionQuery;
        }

        protected function buildAcrossCountQuery(string $field = '*'): Query
        {
            $tableIds = $this->resolveTableIds();
            if (!$tableIds)
            {
                throw new TablePartException('resolved table ids is empty');
            }

            $query = null;

            foreach ($tableIds as $index => $tableId)
            {
                if ($index === 0)
                {
                    $query = $this->buildShardBaseQuery($tableId);
                    $query->removeOption('field');
                    $query->field("COUNT({$field}) AS c");
                    continue;
                }

                $query = $query->unionAll(function(Query $subQuery) use ($tableId, $field) {
                    $subQuery->table($this->tablePart->getTableNameById($tableId));
                    $this->applyShardBaseQueryOptionsToUnionSubQuery($subQuery, true);
                    $subQuery->field("COUNT({$field}) AS c");
                });
            }

            return $query;
        }

        protected function buildAcrossAggregateQuery(string $functionName, string $field, string $alias): Query
        {
            $tableIds = $this->resolveTableIds();
            if (!$tableIds)
            {
                throw new TablePartException('resolved table ids is empty');
            }

            $query = null;

            foreach ($tableIds as $index => $tableId)
            {
                if ($index === 0)
                {
                    $query = $this->buildShardBaseQuery($tableId);
                    $query->removeOption('field');
                    $query->field("{$functionName}({$field}) AS {$alias}");
                    continue;
                }

                $query = $query->unionAll(function(Query $subQuery) use ($tableId, $functionName, $field, $alias) {
                    $subQuery->table($this->tablePart->getTableNameById($tableId));
                    $this->applyShardBaseQueryOptionsToUnionSubQuery($subQuery, true);
                    $subQuery->field("{$functionName}({$field}) AS {$alias}");
                });
            }

            return $query;
        }

        protected function applyShardBaseQueryOptionsToUnionSubQuery(Query $query, bool $ignoreField = false): void
        {
            if (!$ignoreField && $this->fieldForced && $this->field)
            {
                $query->field($this->normalizeFieldForQuery($this->field));
            }

            if ($this->distinct)
            {
                throw new TablePartException('distinct across-shard query is not supported safely in current mode');
            }

            if ($this->groupList)
            {
                throw new TablePartException('group across-shard query is not supported safely in current mode');
            }

            if ($this->havingRawList)
            {
                throw new TablePartException('having across-shard query is not supported safely in current mode');
            }

            foreach ($this->whereList as $where)
            {
                switch ($where['type'])
                {
                    case 'array':
                        $logic = strtoupper((string)($where['logic'] ?? 'AND'));
                        if ($logic === 'OR')
                        {
                            $query->whereOr($where['field']);
                        }
                        else
                        {
                            $query->where($where['field']);
                        }
                        break;

                    case 'basic':
                        $logic = strtoupper((string)($where['logic'] ?? 'AND'));
                        if ($logic === 'OR')
                        {
                            $query->whereOr($where['field'], $where['op'], $where['value']);
                        }
                        else
                        {
                            $query->where($where['field'], $where['op'], $where['value']);
                        }
                        break;

                    case 'in':
                        $query->whereIn($where['field'], $where['value']);
                        break;

                    case 'not_in':
                        $query->whereNotIn($where['field'], $where['value']);
                        break;

                    case 'null':
                        $query->whereNull($where['field']);
                        break;

                    case 'not_null':
                        $query->whereNotNull($where['field']);
                        break;

                    case 'between':
                        $query->whereBetween($where['field'], $where['value']);
                        break;

                    case 'like':
                        $query->whereLike($where['field'], $where['value']);
                        break;

                    case 'raw':
                        $query->whereRaw($where['sql'], $where['bind'] ?? []);
                        break;

                    case 'exp':
                        $query->whereExp($where['field'], $where['value']);
                        break;

                    case 'column':
                        $query->whereColumn($where['field1'], $where['op'], $where['field2']);
                        break;
                }
            }

            foreach ($this->whereRawList as $whereRaw)
            {
                $query->whereRaw($whereRaw['sql'], $whereRaw['bind']);
            }

            if ($this->autoWhereShardField)
            {
                $shardField = $this->tablePart->getShardField();
                if (!$shardField)
                {
                    throw new TablePartException('shardField is not defined');
                }
                $query->where($shardField, '=', $this->symbol);
            }
        }

        protected function ensureAcrossOrderFieldsSelected(): void
        {
            if (!$this->orderList)
            {
                return;
            }

            foreach ($this->orderList as $order)
            {
                $orderField = $order['field'];

                if (is_array($orderField))
                {
                    foreach ($orderField as $k => $v)
                    {
                        $fieldName = is_int($k) ? $v : $k;
                        $this->appendField((string)$fieldName);
                    }

                    continue;
                }

                $this->appendField((string)$orderField);
            }
        }

        protected function appendField(string $field): void
        {
            $field = trim($field);

            if ($field === '')
            {
                return;
            }

            if (!$this->fieldForced)
            {
                $this->fieldForced = true;
                $this->field       = [$field];

                return;
            }

            if ($this->fieldContains($field))
            {
                return;
            }

            $this->field[] = $field;
        }

        protected function fieldContains(string $field): bool
        {
            if (!$this->fieldForced || !$this->field)
            {
                return false;
            }

            if (in_array('*', $this->field, true))
            {
                return true;
            }

            $field = trim($field, " \t\n\r\0\x0B`");

            foreach ($this->field as $item)
            {
                if (trim((string)$item, " \t\n\r\0\x0B`") === $field)
                {
                    return true;
                }
            }

            return false;
        }

        protected function buildShardBaseQuery(int $tableId): Query
        {
            $query = $this->tablePart->getTableInsById($tableId);

            if ($this->distinct)
            {
                throw new TablePartException('distinct across shard/special query is not supported safely in current mode');
            }

            if ($this->fieldForced && $this->field)
            {
                $query->field($this->normalizeFieldForQuery($this->field));
            }

            foreach ($this->whereList as $where)
            {
                switch ($where['type'])
                {
                    case 'array':
                        $logic = strtoupper((string)($where['logic'] ?? 'AND'));
                        if ($logic === 'OR')
                        {
                            $query->whereOr($where['field']);
                        }
                        else
                        {
                            $query->where($where['field']);
                        }
                        break;

                    case 'basic':
                        $logic = strtoupper((string)($where['logic'] ?? 'AND'));
                        if ($logic === 'OR')
                        {
                            $query->whereOr($where['field'], $where['op'], $where['value']);
                        }
                        else
                        {
                            $query->where($where['field'], $where['op'], $where['value']);
                        }
                        break;

                    case 'in':
                        $query->whereIn($where['field'], $where['value']);
                        break;

                    case 'not_in':
                        $query->whereNotIn($where['field'], $where['value']);
                        break;

                    case 'null':
                        $query->whereNull($where['field']);
                        break;

                    case 'not_null':
                        $query->whereNotNull($where['field']);
                        break;

                    case 'between':
                        $query->whereBetween($where['field'], $where['value']);
                        break;

                    case 'like':
                        $query->whereLike($where['field'], $where['value']);
                        break;

                    case 'exp':
                        $query->whereExp($where['field'], $where['value']);
                        break;

                    case 'column':
                        $query->whereColumn($where['field1'], $where['op'], $where['field2']);
                        break;

                    case 'raw':
                        $query->whereRaw($where['sql'], $where['bind'] ?? []);
                        break;
                }
            }

            foreach ($this->whereRawList as $whereRaw)
            {
                $query->whereRaw($whereRaw['sql'], $whereRaw['bind']);
            }

            if ($this->autoWhereShardField)
            {
                $shardField = $this->tablePart->getShardField();
                if (!$shardField)
                {
                    throw new TablePartException('shardField is not defined');
                }
                $query->where($shardField, '=', $this->symbol);
            }

            if ($this->groupList)
            {
                throw new TablePartException('group query is not supported safely in current mode');
            }

            if ($this->havingRawList)
            {
                throw new TablePartException('having query is not supported safely in current mode');
            }

            return $query;
        }

    }