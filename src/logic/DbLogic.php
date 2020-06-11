<?php

namespace tpext\manager\logic;

use think\Db;
use \think\facade\Log;

class DbLogic
{
    protected $database = '';

    protected $errors = [];

    public static $FIELD_TYPES = [
        'tinyint' => 'tinyint',
        'smallint' => 'smallint',
        'mediumint' => 'mediumint',
        'int' => 'int',
        'bigint' => 'bigint',
        'decimal' => 'decimal',
        'float' => 'float',
        'double' => 'double',
        'boolean' => 'boolean',
        'date' => 'date',
        'datetime' => 'datetime',
        'timestamp' => 'timestamp',
        'time' => 'time',
        'year' => 'year',
        'char' => 'char',
        'varchar' => 'varchar',
        'tinytext' => 'tinytext',
        'text' => 'text',
        'mediumtext' => 'mediumtext',
        'longtext' => 'longtext',
    ];

    public function __construct()
    {
        $this->database =  config('database.database');
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getErrors()
    {
        return  $this->errors;
    }

    public function getErrorsText()
    {
        return  implode('<br>', $this->errors);
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
        $tables = Db::query("select {$columns} from information_schema.tables where `TABLE_SCHEMA`='{$this->database}' AND `TABLE_TYPE`='BASE TABLE' AND `TABLE_NAME` NOT LIKE '%_del_at_%' {$where} ORDER BY {$sortOrder}");

        return $tables;
    }

    /**
     * Undocumented function
     *
     * @param string $tableName
     * @param string $columns
     * @return array|null
     */
    public function getTable($tableName, $columns = '*')
    {
        $tables = Db::query("select {$columns} from information_schema.tables where `TABLE_SCHEMA`='{$this->database}' AND `TABLE_TYPE`='BASE TABLE' AND `TABLE_NAME` NOT LIKE '%_del_at_%' AND `TABLE_NAME`='{$tableName}'");

        return count($tables) ? $tables[0] : null;
    }

    public function getTFields($tableName, $columns = '*', $where = '', $sortOrder = 'ORDINAL_POSITION ASC')
    {
        $tables = Db::query("select {$columns} from information_schema.columns where `TABLE_SCHEMA`='{$this->database}' AND `TABLE_NAME`='{$tableName}' AND `COLUMN_NAME` NOT LIKE '%_del_at_%' {$where} ORDER BY {$sortOrder}");

        return $tables;
    }

    /**
     * Undocumented function
     *
     * @param string $tableName
     * @param string $fieldName
     * @return array
     */
    public function getKeys($tableName, $fieldName)
    {
        $keys = Db::query("select NON_UNIQUE,INDEX_NAME from information_schema.statistics where `TABLE_SCHEMA`='{$this->database}' AND `TABLE_NAME`='{$tableName}' AND `COLUMN_NAME`='{$fieldName}'");
        return $keys;
    }

    /**
     * Undocumented function
     *
     * @param string $tableName
     * @param array $data
     * @return boolean
     */
    public function updateTable($tableName, $data)
    {
        $tableInfo = $this->getTable($tableName, 'TABLE_NAME,TABLE_COMMENT');

        if (!$tableInfo) {
            $this->errors[] = '表不存在';
            return false;
        }

        if (isset($data['TABLE_COMMENT']) && $tableInfo['TABLE_COMMENT'] != $data['TABLE_COMMENT']) {
            if (!$this->changeComment($tableName, $data['TABLE_COMMENT'])) {
                return false;
            }
        }

        if (isset($data['TABLE_NAME']) && $tableInfo['TABLE_NAME'] != $data['TABLE_NAME']) {
            if (!$this->changeTableName($tableName, $data['TABLE_NAME'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Undocumented function
     *
     * @param string $tableName
     * @param array $data
     * @return boolean
     */
    public function createTable($tableName, $data)
    {
        $tableInfo = $this->getTable($tableName, 'TABLE_NAME');

        if ($tableInfo) {
            $this->errors[] = '表名已存在';
            return false;
        }

        $PK_INFO = isset($data['PK_INFO']) ? $data['PK_INFO']['pk'] : [];
        if (empty($PK_INFO)) {
            $PK_INFO = [
                'COLUMN_NAME' => 'id',
                'COLUMN_COMMENT' => '主键',
                'DATA_TYPE' => 'int',
                'LENGTH' => '10',
                'ATTR' =>
                [
                    'auto_inc',
                    'unsigned',
                ],
            ];
        }

        $PK_INFO['IS_NULLABLE'] = 'NO';
        $PK_INFO['COLUMN_DEFAULT'] = '';

        $PK_INFO['ATTR'][] = 'primary';

        $attr = $this->buildFieldAttr($PK_INFO);

        try {
            Db::execute("CREATE TABLE IF NOT EXISTS `$tableName`(
                `id` {$attr} primary key COMMENT '{$PK_INFO['COLUMN_COMMENT']}'
                )ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='{$data['TABLE_COMMENT']}'");
        } catch (\Exception $ex) {
            Log::error($ex->__toString());
            $this->errors[] = $ex->getMessage();
            return false;
        }

        return true;
    }

    /**
     * Undocumented function
     *
     * @param string $tableName
     * @param array $info
     * @return boolean
     */
    public function addField($tableName, $info)
    {
        $attr = $this->buildFieldAttr($info);

        try {
            Db::execute("ALTER TABLE `{$tableName}` add `{$info['COLUMN_NAME']}` $attr COMMENT '{$info['COLUMN_COMMENT']}'");

            if (in_array('index', $info['ATTR'])) {
                Db::execute("ALTER TABLE `{$tableName}` add  INDEX `idx_{$info['COLUMN_NAME']}` (`{$info['COLUMN_NAME']}`)");
            }
            if (in_array('unique', $info['ATTR'])) {
                Db::execute("ALTER TABLE `{$tableName}` add  UNIQUE `unq_{$info['COLUMN_NAME']}` (`{$info['COLUMN_NAME']}`)");
            }
        } catch (\Exception $ex) {
            Log::error($ex->__toString());
            $this->errors[] = $ex->getMessage();
            return false;
        }
    }

    /**
     * Undocumented function
     *
     * @param string $tableName
     * @param string $fieldName
     * @param array $info
     * @return boolean
     */
    public function changeField($tableName, $fieldName, $info)
    {
        $keys = $this->getKeys($tableName, $fieldName);

        $primary = '';
        $index_key = '';
        $unique_key = '';

        foreach ($keys as $key) {
            if (strtoupper($key['INDEX_NAME']) == 'PRIMARY') {
                $info['ATTR'][] = 'primary';
                $info['ATTR'][] = 'auto_inc';
                $primary = $key['INDEX_NAME'];
                continue;
            }
            if ($key['NON_UNIQUE'] == 1) {
                $index_key = $key['INDEX_NAME'];
            } else {
                $unique_key = $key['INDEX_NAME'];
            }
        }

        $attr = $this->buildFieldAttr($info);

        try {

            if ($fieldName ==  $info['COLUMN_NAME']) {
                Db::execute("ALTER TABLE `{$tableName}` modify `{$info['COLUMN_NAME']}` $attr COMMENT '{$info['COLUMN_COMMENT']}'");
            } else {
                Db::execute("ALTER TABLE `{$tableName}` change `{$fieldName}` `{$info['COLUMN_NAME']}` $attr COMMENT '{$info['COLUMN_COMMENT']}'");
            }

            if (in_array('index', $info['ATTR'])) {
                if (!$primary && !$index_key) {
                    Db::execute("ALTER TABLE `{$tableName}` add  INDEX `idx_{$info['COLUMN_NAME']}` (`{$info['COLUMN_NAME']}`)");
                }
            } else {
                if ($index_key) {
                    Db::execute("ALTER TABLE `{$tableName}` drop  INDEX `{$index_key}`");
                }
            }
            if (in_array('unique', $info['ATTR'])) {
                if (!$unique_key) {
                    Db::execute("ALTER TABLE `{$tableName}` add  UNIQUE `unq_{$info['COLUMN_NAME']}` (`{$info['COLUMN_NAME']}`)");
                }
            } else {
                if ($unique_key) {
                    Db::execute("ALTER TABLE `{$tableName}` drop  INDEX `{$unique_key}`");
                }
            }
        } catch (\Exception $ex) {
            Log::error($ex->__toString());
            $this->errors[] = $ex->getMessage();
            return false;
        }
    }

    /**
     * Undocumented function
     *
     * @param array $info
     * @return string
     */
    public function buildFieldAttr($info)
    {
        $isInteger = $this->isInteger($info['DATA_TYPE']);
        $isDecimal = $this->isDecimal($info['DATA_TYPE']);
        $isDatetime = $this->isDatetime($info['DATA_TYPE']);
        $isChartext = $this->isChartext($info['DATA_TYPE']);
        $isText = $this->isText($info['DATA_TYPE']);

        $type = $info['DATA_TYPE'];
        $length = $info['LENGTH'];
        $unsigned = in_array('unsigned', $info['ATTR']) && ($isInteger || $isDecimal) ? 'unsigned' : '';
        $not_null = $info['IS_NULLABLE'] == 1 ? '' : 'NOT NULL';
        $default = trim($info['COLUMN_DEFAULT'], "'");
        $auto_inc = $isInteger && $info['DATA_TYPE'] != 'boolean' && in_array('auto_inc', $info['ATTR']) ? 'AUTO_INCREMENT' : '';

        if (empty($length) || !is_numeric($length)) {
            if ($isInteger) {
                $Length = [
                    'tinyint' => 3,
                    'smallint' => 5,
                    'mediumint' => 8,
                    'int' => 10,
                    'bigint' => 20,
                ];
                $length = isset($Length[$info['DATA_TYPE']]) ?  $Length[$info['DATA_TYPE']] : 10;
            } else if ($isDecimal) {
                $length = 10;
            } else if ($isChartext) {
                $length = 55;
            }
        }

        if ($isInteger || $isChartext) {
            $length = "({$length})";
        } else if ($isDecimal) {
            if (empty($info['NUMERIC_SCALE']) || !is_numeric($info['NUMERIC_SCALE'])) {
                $info['NUMERIC_SCALE'] = 2;
            }
            $length = "({$length},{$info['NUMERIC_SCALE']})";
        } else if ($isDatetime || $isText) {
            $length = '';
        }

        if ($info['DATA_TYPE'] == 'boolean') {

            $length = '(1)';
            $unsigned = 'unsigned';
        } else if ($info['DATA_TYPE'] == 'year') {

            $length = '(4)';
        }

        if ($not_null) {
            if (($isInteger || $isDecimal) && !is_numeric($default)) {
                $default = '0';
            }

            if (strtoupper($default) == 'NULL') {
                $default = '';
            }
        }

        if (strtoupper($default) == 'NULL') {

            $default = 'DEFAULT NULL';
        } else if ($isDatetime) {

            if ($info['DATA_TYPE'] == 'year') {
                $default = is_numeric($default) && $default >= 0 ? $default : 2020;
            } else if ($info['DATA_TYPE'] == 'date') {
                $default = date('Y-m-d', strtotime($default));
            } else if ($info['DATA_TYPE'] == 'time') {
                $default = date('H:i:s', strtotime($default));
            } else {
                $default = date('Y-m-d H:i:s', strtotime($default));
            }

            $default = "DEFAULT '{$default}'";
        } else if ($isInteger || $isDecimal) {

            if ($unsigned) {
                $default = is_numeric($default) && $default >= 0 ? $default : 0;
            } else {
                $default = is_numeric($default) ? $default : 0;
            }

            $default = "DEFAULT '{$default}'";
        } else {
            $default = "DEFAULT '{$default}'";
        }

        if (in_array('primary', $info['ATTR']) || $auto_inc || $isText) {
            $default = '';
        }

        $attr = "{$type}{$length} {$unsigned} {$not_null} {$default} {$auto_inc}";

        return $attr;
    }

    /**
     * Undocumented function
     *
     * @param string $tableName
     * @return boolean
     */
    public function dropTable($tableName)
    {
        try {
            Db::execute("DROP TABLE IF EXISTS `{$tableName}`");
        } catch (\Exception $ex) {
            Log::error($ex->__toString());
            $this->errors[] = $ex->getMessage();
            return false;
        }

        return true;
    }

    /**
     * Undocumented function
     *
     * @param string $tableName
     * @return boolean
     */
    public function trashTable($tableName)
    {
        return $this->changeTableName($tableName, $tableName . '_del_at_' . time());
    }

    /**
     * Undocumented function
     *
     * @param string $tableName
     * @param string $comment
     * @return boolean
     */
    public function changeComment($tableName, $comment)
    {
        $tableInfo = $this->getTable($tableName, 'TABLE_COMMENT');

        if (!$tableInfo) {
            return false;
        }

        if ($tableInfo['TABLE_COMMENT'] == $comment) {
            return false;
        }

        try {
            Db::execute("ALTER TABLE `{$tableName}` COMMENT '{$comment}'");
        } catch (\Exception $ex) {
            Log::error($ex->__toString());
            $this->errors[] = $ex->getMessage();
            return false;
        }

        return true;
    }

    public function changeTableName($tableName, $new_name)
    {
        if ($tableName == $new_name) {
            return false;
        }

        try {
            Db::execute("ALTER TABLE `{$tableName}` RENAME TO `{$new_name}`");
        } catch (\Exception $ex) {
            Log::error($ex->__toString());
            $this->errors[] = $ex->getMessage();
            return false;
        }

        return true;
    }

    public function isInteger($fieldType)
    {
        return in_array($fieldType, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'boolean']);
    }

    public function isDecimal($fieldType)
    {
        return in_array($fieldType, ['decimal', 'float', 'double']);
    }

    public function isDatetime($fieldType)
    {
        return in_array($fieldType, ['date', 'datetime', 'timestamp', 'time', 'year']);
    }

    public function isChartext($fieldType)
    {
        return in_array($fieldType, ['varchar', 'char']);
    }

    public function isText($fieldType)
    {
        return in_array($fieldType, ['tinytext', 'text', 'mediumtext', 'longtext']);
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
