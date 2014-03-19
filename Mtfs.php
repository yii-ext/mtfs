<?php
/**
 * Memory Tables From Scratch
 * Restoring memory table schema & data after reboot of mysql or server
 * @author  Dmitry Semenov <disemx@gmail.com>
 * @version 0.5
 * @package Mtfs
 */
class Mtfs extends CApplicationComponent
{

    /**
     * @var array default tables to be memorized
     */
    public $tables = array();

    /**
     * @var string table name with data for restore
     */
    private $table;

    /**
     * @var string table name to be restored
     */
    private $tableMemory;

    /**
     * @var mixed
     */
    public $timeStart;

    /**
     * Memorizing default tables array if needed
     */
    public function run()
    {
        $this->timeStart = microtime(true);
        echo "Loading memory tables...\n";
        foreach ($this->tables as $table => $tableMemory) {
            if (!$this->compareTables($table, $tableMemory)) {
                if (!$this->memorize($table, $tableMemory)) {
                    Yii::log("Something wrong.", 'error', 'extensions.CodMtfs.Mtfs');
                }
            } else {
                Yii::log("Table {$tableMemory} exist and have same rows structure and count.", 'error', 'extensions.CodMtfs.Mtfs');
            }
        }
        $timeEnd = microtime(true);
        $executionTime = ($timeEnd - $this->timeStart) / 60;
        echo "Done in {$executionTime} Mins!...\n";
    }

    /**
     * Restore action
     *
     * @param string $table
     * @param string $tableMemory
     *
     * @return boolean
     */
    public function memorize($table, $tableMemory)
    {
        $this->table = $table;
        $this->tableMemory = $tableMemory;
        if ($this->prepareSchema()) {
            if ($this->copyData()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Compare memory table with scratch (schema & rows num)
     *
     * @param $table
     * @param $tableMemory
     *
     * @return boolean
     */
    private function compareTables($table, $tableMemory)
    {
        $connection = Yii::app()->db;
        if ($connection->schema->getTable($table) && $connection->schema->getTable($tableMemory)) {
            $tableColumns = $connection->schema->getTable($table)->columns;
            $tableMemoryColumns = $connection->schema->getTable($tableMemory)->columns;
            if (count($tableColumns) !== count($tableMemoryColumns)) {
                return false;
            }
            foreach (array_keys($tableColumns) as $key) {
                if (get_object_vars($tableColumns[$key]) !== get_object_vars($tableMemoryColumns[$key])) {
                    return false;
                }
            }
            $sourceCount = $connection->createCommand('SELECT COUNT(*) FROM ' . $connection->quoteTableName($table))->queryScalar();
            $destinationCount = $connection->createCommand('SELECT COUNT(*) FROM ' . $connection->quoteTableName($tableMemory))->queryScalar();
            if ($sourceCount !== $destinationCount) {
                return false;
            }
        } elseif ($connection->schema->getTable($table) === null || $connection->schema->getTable($tableMemory) === null) {
            return false;
        }
        return true;
    }

    /**
     * Preparing schema of memory table for further data import
     * @return boolean
     */
    private function prepareSchema()
    {
        $connection = Yii::app()->db;
        if ($connection->schema->getTable($this->table)) {
            try {
                $this->dropTableIfExist($connection, $this->tableMemory);
                $connection->createCommand('CREATE TABLE ' . $connection->quoteTableName($this->tableMemory) . ' LIKE ' . $connection->quoteTableName($this->table) . ';')->execute();
                $connection->createCommand('ALTER TABLE ' . $connection->quoteTableName($this->tableMemory) . ' ENGINE=MEMORY;')->execute();
                return true;
            } catch (Exception $e) {
                $this->dropTableIfExist($connection, $this->tableMemory);
                Yii::log('Schema preparation error: ' . print_r($e->getMessage(), true), 'error', 'extensions.CodMtfs.Mtfs');
            }
        } else {
            Yii::log('Nothing to copy.', 'error', 'extensions.CodMtfs.Mtfs');
        }
        return false;
    }

    /**
     * Copy mysql data from table to memory table
     * @return mixed
     */
    private function  copyData()
    {
        $connection = Yii::app()->db;
        $transaction = $connection->beginTransaction();
        try {
            $connection->createCommand('INSERT INTO ' . $connection->quoteTableName($this->tableMemory) . ' SELECT * FROM ' . $connection->quoteTableName($this->table))->execute();
            $transaction->commit();
            return true;
        } catch (Exception $e) {
            Yii::log('Transaction error: ' . print_r($e->getMessage(), true), 'error', 'extensions.CodMtfs.Mtfs');
            $transaction->rollback();
        }
    }

    /**
     * @param $connection
     * @param $table
     */
    private function dropTableIfExist($connection, $table)
    {
        if ($connection->schema->getTable($table)) {
            $connection->createCommand()->dropTable($table);
        }
    }
