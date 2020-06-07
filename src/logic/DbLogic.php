<?php

namespace tpext\manager\logic;

use think\Db;
use \think\facade\Log;

class DbLogic
{
    protected $database = '';

    public function __construct()
    {
        $this->database =  config('database.database');
    }

    /**
     * Undocumented function
     *
     * @param string $columns
     * @param string $where
     * @param string $sortOrder
     * @return array
     */
    public function getTables($columns = '*', $where = '', $sortOrder = 'TABLE_NAME ASC')
    {
        $tables = Db::query("select {$columns} from information_schema.tables where `TABLE_SCHEMA`='{$this->database}' AND `TABLE_TYPE`='BASE TABLE' {$where} ORDER BY {$sortOrder}");

        return $tables;
    }

    /**
     * Undocumented function
     *
     * @param string $table_name
     * @param string $columns
     * @return array|null
     */
    public function getTable($table_name, $columns = '*')
    {
        $tables = Db::query("select {$columns} from information_schema.tables where `TABLE_SCHEMA`='{$this->database}' AND `TABLE_TYPE`='BASE TABLE' AND `TABLE_NAME`='{$table_name}'");

        return count($tables) ? $tables[0] : null;
    }

    /**
     * Undocumented function
     *
     * @param string $table_name
     * @param array $data
     * @return boolean
     */
    public function updateTable($table_name, $data)
    {
        $tableInfo = $this->getTable($table_name, 'TABLE_NAME,TABLE_COMMENT');

        if (!$tableInfo) {
            return false;
        }

        if (isset($data['TABLE_COMMENT']) && $tableInfo['TABLE_COMMENT'] != $data['TABLE_COMMENT']) {
            if (!$this->changeComment($table_name, $data['TABLE_COMMENT'])) {
                return false;
            }
        }

        if (isset($data['TABLE_NAME']) && $tableInfo['TABLE_NAME'] != $data['TABLE_NAME']) {
            if (!$this->changeTableName($table_name, $data['TABLE_NAME'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Undocumented function
     *
     * @param string $table_name
     * @param array $data
     * @return boolean
     */
    public function createTable($table_name, $data)
    {
    }

    /**
     * Undocumented function
     *
     * @param string $table_name
     * @param string $comment
     * @return boolean
     */
    public function changeComment($table_name, $comment)
    {
        $tableInfo = $this->getTable($table_name, 'TABLE_COMMENT');

        if (!$tableInfo) {
            return false;
        }

        if ($tableInfo['TABLE_COMMENT'] == $comment) {
            return false;
        }

        try {
            Db::execute("ALTER TABLE `{$table_name}` COMMENT '{$comment}'");
        } catch (\Exception $ex) {
            Log::error($ex->__toString());
            return false;
        }

        return true;
    }

    public function changeTableName($table_name, $new_name)
    {
        if ($table_name == $new_name) {
            return false;
        }

        try {
            Db::execute("ALTER TABLE `{$table_name}` RENAME TO `{$new_name}`");
        } catch (\Exception $ex) {
            Log::error($ex->__toString());
            return false;
        }

        return true;
    }

    public function getDataSize($data)
    {
        return round(($data['AVG_ROW_LENGTH'] * $data['TABLE_ROWS'] + $data['INDEX_LENGTH']) / 1024 / 1024, 2);
    }

    public static function isInstalled()
    {
        if (empty(config('database.database'))) {
            return false;
        }
    }
}
