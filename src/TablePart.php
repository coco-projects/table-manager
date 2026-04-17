<?php

    namespace Coco\tableManager;

    use think\db\Query;

    class TablePart extends TableAbstract
    {
        private int $currentTableId = 0;

        public function __construct(protected string $name, private int $tableCount = 1)
        {
            if ($tableCount < 1)
            {
                throw new \InvalidArgumentException('tableCount must be greater than 0');
            }
            parent:: __construct($name);
        }

        public function findBySymbolAndIdPart(string|int $symbol)
        {
            return $this->findBySymbol($this->getPkField(), $symbol);
        }

        public function findBySymbol(string $symbolField, string|int $symbol)
        {
            $tableId = $this->symbolToTableId($symbol);

            $this->setCurrentTableId($tableId);

            return $this->tableInsCurrent()->where($symbolField, '=', $symbol)->find();
        }

        public function insertAllByIdPart($data): void
        {
            $this->insertAll($this->getPkField(), $data);
        }

        public function insertAll(string $symbolField, $data): void
        {
            $dataToInsert = [];

            foreach ($data as $k => $v)
            {
                if (!isset($v[$symbolField]))
                {
                    continue;
                }
                $dataToTableId                  = $this->symbolToTableId($v[$symbolField]);
                $dataToInsert[$dataToTableId][] = $v;
            }

            foreach ($dataToInsert as $dataToTableId => $datas)
            {
                $this->setCurrentTableId($dataToTableId);
                $this->tableInsCurrent()->insertAll($datas);
            }
        }

        public function isTableCreated(): bool
        {
            for ($i = 0; $i < $this->tableCount; $i++)
            {
                try
                {
                    $this->getTableInsById($i)->find();
                    $this->tableRegistry->getDbManager()->execute($sql);

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
                $this->setCurrentTableId($i);

                $sql = $this->buildTruncateSql();
                $this->tableRegistry->logInfo('执行：' . $sql);
                $this->tableRegistry->getDbManager()->execute($sql);
            }
        }

        public function create(bool $forceCreate = false): void
        {
            if ($forceCreate)
            {
                $this->drop();
            }

            for ($i = 0; $i < $this->tableCount; $i++)
            {
                $this->setCurrentTableId($i);
                $sql = $this->buildCreateSql();
                $this->tableRegistry->logInfo('执行：' . $sql);
                $this->tableRegistry->getDbManager()->execute($sql);
            }
        }

        /**
         * 始终返回 第一个tab，id为0的那个
         * 后面始终使用 setCondition 设置条件
         *
         * @return Query
         */
        public function tableIns(): Query
        {
            $instance = $this->getTableInsById(0);

            return $instance;
        }

        public function setCondition(callable $queryCallback): Query
        {
            $tableIns0 = $this->tableIns();

            call_user_func_array($queryCallback, [
                $tableIns0,
                $this,
            ]);

            for ($i = 1; $i < $this->tableCount; $i++)
            {
                $tableIns0 = $tableIns0->unionAll(function(Query $query) use ($i, $queryCallback) {

                    $query->table($this->buildTableName($i));

                    call_user_func_array($queryCallback, [
                        $query,
                        $this,
                    ]);

                });
            }

            return $tableIns0;
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
            }

            $sql = $this->buildCreateSql();
            $this->tableRegistry->logInfo('执行：' . $sql);

            return $this->tableRegistry->getDbManager()->execute($sql);
        }


        public function getTableCount(): int
        {
            return $this->tableCount;
        }

        public function setCurrentTableId(int $currentTableId): static
        {
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

        //根据表示切换到指定的表，后面直接调用 tableInsCurrent 获得tableIns 插入数据
        public function setCurrentTableBySymbol(string|int $symbol): static
        {
            $this->setCurrentTableId($this->symbolToTableId($symbol));

            return $this;
        }

        public function dropTableById(int $id): void
        {
            $this->setCurrentTableId($id);
            $sql = $this->buildDropSql();
            $this->tableRegistry->logInfo('执行：' . $sql);
            $this->tableRegistry->getDbManager()->execute($sql);
        }

        public function tableInsCurrent(): Query
        {
            return $this->getTableInsById($this->currentTableId);
        }

        //要写入数据时，传入一个标识，比如数据是那个账号的，根据这个标识计算数据会写入哪个表
        public function symbolToTableId(string|int $symbol): int
        {
            return $this->stringToInteger($symbol) % $this->tableCount;
        }

        private function buildTableName(int $id): string
        {
            return $this->name . '_' . $id;
        }

        private function getTableInsById(int $id): Query
        {
            return $this->tableRegistry->getDbManager()->table($this->buildTableName($id));
        }

        private function stringToInteger(string|int $string): int
        {
            return hexdec(substr(md5($string), 0, 8));
        }

        protected function getBuildTableName(): string
        {
            return $this->getCurrentTableName();
        }

    }






