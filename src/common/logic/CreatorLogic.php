<?php

namespace tpext\manager\common\logic;

use think\Loader;

class CreatorLogic
{
    protected $lines = [];

    protected $autoPostDisplayers = ['text', 'textarea', 'select', 'multipleSelect', 'switchBtn', 'radio', 'checkbox'];

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getLins()
    {
        return $this->lines;
    }

    /**
     * Undocumented function
     *
     * @param array $data
     * @return array
     */
    public function getActions($data)
    {
        $actions = ['actions\HasBase'];

        if ($data['table_build'] == 1) {
            $actions[] = 'actions\HasIndex';

            $tableToolbars = $data['table_toolbars'] ?? [];
            $tableActions = $data['table_actions'] ?? [];

            if (in_array('add', $tableToolbars)) {
                $actions[] = 'actions\HasAdd';
            }

            if (in_array('edit', $tableActions)) {
                $actions[] = 'actions\HasEdit';
            }

            if (in_array('view', $tableActions)) {
                $actions[] = 'actions\HasView';
            }

            if (in_array('delete', $tableToolbars) || in_array('delete', $tableActions)) {
                $actions[] = 'actions\HasDelete';
            }

            if (in_array('enable', $tableToolbars) || in_array('enable', $tableActions)) {
                $actions[] = 'actions\HasEnable';
            }

            foreach ($data['TABLE_FIELDS'] as $field) {
                if (!in_array('actions\HasAutopost', $actions) && in_array($field['DISPLAYER_TYPE'], $this->autoPostDisplayers)) {
                    $actions[] = 'actions\HasAutopost';
                }
            }
        }

        return $actions;
    }

    /**
     * Undocumented function
     *
     * @param array $data
     * @param string $prefix
     * @param string $modelNamespace
     * @return void
     */
    public function make($data, $prefix, $modelNamespace)
    {
        $this->begin($data, $prefix, $modelNamespace);

        if ($data['table_build']) {
            $this->buildSearch($data);
            $this->filterWhere($data);
            $this->buildTable($data);
        }

        if ($data['form_build']) {
            $this->buildForm($data);
            $this->save($data);
        }

        $this->end($data);
    }

    /**
     * Undocumented function
     *
     * @param array $data
     * @param string $prefix
     * @param string $modelNamespace
     * @return void
     */
    public function begin($data, $prefix, $modelNamespace)
    {
        $table = preg_replace('/^' . $prefix . '(.+)$/', '$1', $data['TABLE_NAME']);

        $controllerNamespace = '';
        $controllerName = '';

        if (preg_match('/^\w+$/', $data['controller'])) {
            $controllerNamespace = 'app\\admin\\controller';
            $controllerName = ucfirst(strtolower(Loader::parseName($data['controller'], 1)));
        } else if (preg_match('/^(\w+)[\/](\w+)$/', $data['controller'], $mch)) {
            $controllerNamespace = 'app\\admin\\controller\\' . strtolower($mch[1]);
            $controllerName = ucfirst(strtolower(Loader::parseName($mch[2], 1)));
        }

        if ($modelNamespace == 'common') {
            $modelNamespace = 'app\\common\\model\\';
        } else {
            $modelNamespace = 'app\\admin\\model\\';
        }

        $modelName = Loader::parseName($table, 1);

        $controllerTitle = $data['controller_title'];

        $this->lines[] = "<?php";
        $this->lines[] = '';
        $this->lines[] = "namespace {$controllerNamespace};";
        $this->lines[] = '';
        $this->lines[] = "use {$modelNamespace}{$modelName} as {$modelName}Model;";
        $this->lines[] = "use think\\Controller;";
        $this->lines[] = "use think\\facade\\Lang;";
        $this->lines[] = "use tpext\\builder\\traits\actions;";
        $this->lines[] = '';

        $this->lines[] = '/**';
        $this->lines[] = ' * @time tpextmanager 生成于' . date('Y-m-d H:i:s');
        $this->lines[] = ' * @title ' . $controllerTitle;
        $this->lines[] = ' */';

        $this->lines[] = 'class ' . $controllerName . ' extends Controller';
        $this->lines[] = '{';

        $actions = $this->getActions($data);

        foreach ($actions as $action) {
            $this->lines[] = "    use $action;";
        }

        $this->lines[] = '';

        $this->lines[] = '    /**';
        $this->lines[] = '     * Undocumented variable';
        $this->lines[] = "     * @var {$modelName}Model";
        $this->lines[] = '     */';

        $this->lines[] = '    protected $dataModel;';
        $this->lines[] = '';
        $this->lines[] = '    protected function initialize()';
        $this->lines[] = '    {';
        $this->lines[] = "        \$this->dataModel = new {$modelName}Model;";
        $this->lines[] = "        \$this->pageTitle = '{$controllerTitle}';";
        $this->lines[] = "        \$this->selectTextField = '{id}#{name}';";
        $this->lines[] = "        \$this->selectSearch = 'name';";
        $this->lines[] = "        \$this->pk = 'id';";
        $this->lines[] = "        \$this->pagesize = 14;";
        $this->lines[] = "        \$this->sortOrder = 'id desc';";
        $this->lines[] = "        \$this->indexWith = []; //列表页关联";

        $tableToolbars = $data['table_toolbars'] ?? [];
        $tableActions = $data['table_actions'] ?? [];

        if (in_array('enable', $tableToolbars) || in_array('enable', $tableActions)) {
            $this->lines[] = '';
            $enable_field = $data['enable_field'] ?: 'enable';
            $this->lines[] = "        \$this->enableField = '{$enable_field}';";
        }

        $this->lines[] = '';
        $this->lines[] = "        Lang::load(env('root_path') . implode(DIRECTORY_SEPARATOR, ['application', 'admin', 'lang', config('default_lang'), '" . strtolower($modelName) . "' . '.php']));";
        $this->lines[] = '    }';
    }

    /**
     * Undocumented function
     *
     * @param array $data
     * @return void
     */
    public function end($data)
    {
        $this->lines[] = '';
        $this->lines[] = '}';
        $this->lines[] = '';
    }

    /**
     * Undocumented function
     *
     * @param array $data
     * @return void
     */
    public function buildTable($data)
    {
        $tableToolbars = $data['table_toolbars'] ?? [];
        $tableActions = $data['table_actions'] ?? [];

        $this->lines[] = '';
        $this->lines[] = '    /**';
        $this->lines[] = '     * 构建表格';
        $this->lines[] = '     * @param array $data';
        $this->lines[] = '     * @return mixed';
        $this->lines[] = '     */';
        $this->lines[] = '    protected function buildTable(&$data = [], $isExporting = false)';
        $this->lines[] = '    {';
        $this->lines[] = '        $table = $this->table;';
        $this->lines[] = '';
        if (!empty($data['TABLE_FIELDS'])) {

            $line = '';
            $sortable = [];
            $createAndUpdate = [];

            foreach ($data['TABLE_FIELDS'] as $field) {

                if (empty($field['DISPLAYER_TYPE']) || $field['DISPLAYER_TYPE'] == '_') {
                    continue;
                }

                if (preg_match('/^(?:created?_time|add_time|created?_at|updated?_time|updated?_at)$/', $field['COLUMN_NAME'])) {
                    $createAndUpdate[] = $field;
                    continue;
                }

                if ($field['DISPLAYER_TYPE'] == 'belongsTo') {
                    $line = '        $table->show' . "('{$field['COLUMN_NAME']}')->to('{{$field['COLUMN_NAME']}}#{{$field['FIELD_RELATION']}}')";
                } else {
                    $line = '        $table->' . $field['DISPLAYER_TYPE'] . "('{$field['COLUMN_NAME']}')";
                }

                if (in_array($field['DISPLAYER_TYPE'], $this->autoPostDisplayers)) {
                    $line .= '->autoPost()';
                    if ($field['DISPLAYER_TYPE'] == 'text') {
                        $line .= "->getWrapper()->addStyle('max-width:140px;')";
                    }
                } else if (in_array($field['DISPLAYER_TYPE'], ['match', 'maches'])) {
                    if (!empty($field['FIELD_RELATION']) && preg_match('/^(\w+)\[(\w+),\s*(\w+)\]$/i', trim($field['FIELD_RELATION']), $mch)) {
                        $line .= "->optionsData(db('{$mch[1]}')->select(), '{$mch[2]}', '{$mch[3]}')";
                    } else {
                        $line .= "->options([/*选项*/])";
                    }
                }

                if (isset($field['ATTR']) && in_array('sortable', $field['ATTR'])) {
                    $sortable[] = $field['COLUMN_NAME'];
                }

                $line .= ';';

                $this->lines[] = $line;
            }

            if (count($createAndUpdate)) {
                foreach ($createAndUpdate as $timeField) {
                    $this->lines[] = '        $table->' . $timeField['DISPLAYER_TYPE'] . "('{$timeField['COLUMN_NAME']}');";
                }
            }

            $this->lines[] = '';
            $sline = [];
            $sline[] = '        $table->getToolbar()';

            if (in_array('add', $tableToolbars)) {
                $sline[] = '            ->btnAdd()';
            }

            if (in_array('enable', $tableToolbars)) {
                $sline[] = "            ->btnEnableAndDisable('正常', '禁用')";
            }

            if (in_array('import', $tableToolbars)) {
                $sline[] = '            ->btnImport()';
            }

            if (in_array('delete', $tableToolbars)) {
                $sline[] = '            ->btnDelete()';
            }

            $sline[] = '            ->btnRefresh();';

            $this->lines[] = implode(PHP_EOL, $sline);

            if (!in_array('export', $tableToolbars)) {
                $this->lines[] = '        $table->useExport(false);';
            }

            $sline = [''];

            $sline[] = '        $table->getActionbar()';

            if (in_array('edit', $tableActions)) {
                $sline[] = '            ->btnEdit()';
            }

            if (in_array('view', $tableActions)) {
                $sline[] = '            ->btnView()';
            }

            if (in_array('enable', $tableActions)) {
                $sline[] = "            ->btnEnableAndDisable('正常', '禁用')";
            }

            if (in_array('delete', $tableActions)) {
                $sline[] = '            ->btnDelete()';
            }

            if (count($sline) > 2) {
                $this->lines[] = implode(PHP_EOL, $sline) . ';';
            } else {
                $this->lines[] = '          $table->useActionbar(false);';
            }

            if (count($sortable)) {
                $this->lines[] = '';
                $this->lines[] = '        $table->sortable(\'' . implode(',', $sortable) . '\');';
            }
        }

        $this->lines[] = '    }';
    }

    /**
     * Undocumented function
     *
     * @param array $data
     * @return void
     */
    public function filterWhere($data)
    {
        $this->lines[] = '';
        $this->lines[] = '    /**';
        $this->lines[] = '     * 构建搜索条件';
        $this->lines[] = '     * @param array $data';
        $this->lines[] = '     * @return mixed';
        $this->lines[] = '     */';
        $this->lines[] = '    protected function filterWhere()';
        $this->lines[] = '    {';
        $this->lines[] = '        $searchData = request()->get();';

        $this->lines[] = '        $where = [];';

        if (!empty($data['TABLE_FIELDS'])) {

            foreach ($data['TABLE_FIELDS'] as $field) {

                if (isset($field['ATTR']) && in_array('search', $field['ATTR'])) {

                    if (preg_match('/^\w*?(time|date)$/', $field['COLUMN_NAME'])) {
                        $this->lines[] = "        if (isset(\$searchData['{$field['COLUMN_NAME']}_start']) && \$searchData['{$field['COLUMN_NAME']}_start'] != '') {";

                        $this->lines[] = "            \$where[] = ['{$field['COLUMN_NAME']}', '>=', \$searchData['{$field['COLUMN_NAME']}_start']];";
                        $this->lines[] = '        }';

                        $this->lines[] = "        if (isset(\$searchData['{$field['COLUMN_NAME']}_end']) && \$searchData['{$field['COLUMN_NAME']}_end'] != '') {";

                        $this->lines[] = "            \$where[] = ['{$field['COLUMN_NAME']}', '<=', \$searchData['{$field['COLUMN_NAME']}_end']];";
                        $this->lines[] = '        }';
                    } else {

                        $this->lines[] = "        if (isset(\$searchData['{$field['COLUMN_NAME']}']) && \$searchData['{$field['COLUMN_NAME']}'] != '') {";

                        if (preg_match('/varchar|text/i', $field['COLUMN_TYPE'])) {
                            $this->lines[] = "            \$where[] = ['{$field['COLUMN_NAME']}', 'like', '%' . trim(\$searchData['{$field['COLUMN_NAME']}']) . '%'];";
                        } else {
                            $this->lines[] = "            \$where[] = ['{$field['COLUMN_NAME']}', '=', \$searchData['{$field['COLUMN_NAME']}']];";
                        }

                        $this->lines[] = '        }';
                    }
                }
            }
        }

        $this->lines[] = '';

        $this->lines[] = '        return $where;';

        $this->lines[] = '    }';
    }

    /**
     * Undocumented function
     *
     * @param array $data
     * @return void
     */
    public function buildSearch($data)
    {
        $this->lines[] = '';
        $this->lines[] = '    /**';
        $this->lines[] = '     * 构建搜索';
        $this->lines[] = '     * @return mixed';
        $this->lines[] = '     */';
        $this->lines[] = '    protected function buildSearch()';
        $this->lines[] = '    {';
        $this->lines[] = '        $search = $this->search;';
        $this->lines[] = '';
        if (!empty($data['TABLE_FIELDS'])) {

            foreach ($data['TABLE_FIELDS'] as $field) {

                if (isset($field['ATTR']) && in_array('search', $field['ATTR'])) {

                    if (preg_match('/^\w*?time$/', $field['COLUMN_NAME'])) {
                        $this->lines[] = '        $search->datetime' . "('{$field['COLUMN_NAME']}_start');";
                        $this->lines[] = '        $search->datetime' . "('{$field['COLUMN_NAME']}_end');";
                    } else if (preg_match('/^\w*?date$/', $field['COLUMN_NAME'])) {
                        $this->lines[] = '        $search->date' . "('{$field['COLUMN_NAME']}_start');";
                        $this->lines[] = '        $search->date' . "('{$field['COLUMN_NAME']}_end');";
                    } else if (preg_match('/^(\w+)_id$/', $field['COLUMN_NAME'], $mch)) {
                        $this->lines[] = '        $search->select' . "('{$field['COLUMN_NAME']}')->dataUrl(url('theurl'));";
                    } else if (preg_match('/^(\w+)_ids$/', $field['COLUMN_NAME'], $mch)) {
                        $this->lines[] = '        $search->multipleSelect' . "('{$field['COLUMN_NAME']}')->dataUrl(url('theurl'));";
                    } else if (preg_match('/^is_\w+|enabled?$/', $field['COLUMN_NAME'])) {
                        $this->lines[] = '        $search->select' . "('{$field['COLUMN_NAME']}')->options([]);";
                    } else if (preg_match('/^\w*?(?:status|state)$/', $field['COLUMN_NAME'])) {
                        $this->lines[] = '        $search->select' . "('{$field['COLUMN_NAME']}')->options([]);";
                    } else {
                        $this->lines[] = '        $search->text' . "('{$field['COLUMN_NAME']}');";
                    }
                }
            }
        }
        $this->lines[] = '    }';
    }

    /**
     * Undocumented function
     *
     * @param array $data
     * @return void
     */
    public function buildForm($data)
    {
        $this->lines[] = '';
        $this->lines[] = '    /**';
        $this->lines[] = '     * 构建表单';
        $this->lines[] = '     * @param boolean $isEdit';
        $this->lines[] = '     * @param array $data';
        $this->lines[] = '     * @return mixed';
        $this->lines[] = '     */';
        $this->lines[] = '    protected function buildForm($isEdit, &$data = [])';
        $this->lines[] = '    {';
        $this->lines[] = '        $form = $this->form;';
        $this->lines[] = '';
        if (!empty($data['FORM_FIELDS'])) {

            $line = '';
            $createAndUpdate = [];

            foreach ($data['FORM_FIELDS'] as $field) {

                if (empty($field['DISPLAYER_TYPE']) || $field['DISPLAYER_TYPE'] == '_') {
                    continue;
                }

                if (preg_match('/^(?:created?_time|add_time|created?_at|updated?_time|updated?_at)$/', $field['COLUMN_NAME'])) {
                    $createAndUpdate[] = $field;
                    continue;
                }

                if ($field['DISPLAYER_TYPE'] == 'belongsTo') {
                    $line = '        $form->show' . "('{$field['COLUMN_NAME']}')->to('{{$field['COLUMN_NAME']}}#{{$field['FIELD_RELATION']}}')";
                } else {
                    $line = '        $form->' . $field['DISPLAYER_TYPE'] . "('{$field['COLUMN_NAME']}')";
                }

                if (in_array($field['DISPLAYER_TYPE'], ['text', 'textarea']) && preg_match('/^varchar\((\d+)\)$/i', $field['COLUMN_TYPE'], $mch)) {
                    $line .= '->maxlength(' . $mch[1] . ')';
                } else if (in_array($field['DISPLAYER_TYPE'], ['select', 'multipleSelect'])) {
                    if (!empty($field['FIELD_RELATION'])) {
                        $line .= "->dataUrl(url('{$field['FIELD_RELATION']}'))";
                    } else {
                        $line .= "->dataUrl(url('selectpage'))";
                    }
                } else if (in_array($field['DISPLAYER_TYPE'], ['match', 'maches'])) {
                    if (!empty($field['FIELD_RELATION']) && preg_match('/^(\w+)\[(\w+),\s*(\w+)\]$/i', trim($field['FIELD_RELATION']), $mch)) {
                        $line .= "->optionsData(db('{$mch[1]}')->select(), '{$mch[2]}', '{$mch[3]}')";
                    } else {
                        $line .= "->options([/*选项*/])";
                    }
                }

                if (isset($field['ATTR']) && in_array('required', $field['ATTR'])) {
                    $line .= '->required()';
                }

                $line .= ';';

                $this->lines[] = $line;
            }

            if (count($createAndUpdate)) {

                $this->lines[] = '';

                $this->lines[] = '        if ($isEdit) {';

                foreach ($createAndUpdate as $timeField) {
                    $this->lines[] = '            $form->' . $timeField['DISPLAYER_TYPE'] . "('{$timeField['COLUMN_NAME']}');";
                }

                $this->lines[] = '        }';
            }
        }
        $this->lines[] = '    }';
    }

    /**
     * Undocumented function
     *
     * @param array $data
     * @return void
     */
    public function save($data)
    {
        $this->lines[] = '';
        $this->lines[] = '    /**';
        $this->lines[] = '     * 保存数据';
        $this->lines[] = '     * @param integer $id';
        $this->lines[] = '     * @return mixed';
        $this->lines[] = '     */';
        $this->lines[] = '    private function save($id = 0)';
        $this->lines[] = '    {';

        $this->lines[] = '        $data = request()->post();';

        $this->lines[] = '';

        if (!empty($data['FORM_FIELDS'])) {

            $this->lines[] = '        $result = $this->validate($data, [';

            foreach ($data['FORM_FIELDS'] as $field) {

                if (isset($field['ATTR']) && in_array('required', $field['ATTR'])) {
                    $this->lines[] = "            '{$field['COLUMN_NAME']}|{$field['COLUMN_COMMENT']}' => 'require',";
                }
            }

            $this->lines[] = '        ]);';

            foreach ($data['FORM_FIELDS'] as $field) {

                if (preg_match('/^(?:parent_id|pid)$/', $field['COLUMN_NAME'])) {
                    $this->lines[] = '';
                    $this->lines[] = '        if ($id && $data[\'' . $field['COLUMN_NAME'] . '\'] == $id) {';
                    $this->lines[] = '            $this->error(\'上级不能是本身\');';
                    $this->lines[] = '        }';
                    break;
                }
            }

            $this->lines[] = '';
            $this->lines[] = '        if (true !== $result) {';
            $this->lines[] = '            $this->error($result);';
            $this->lines[] = '        }';
            $this->lines[] = '';

            $this->lines[] = '        return $this->doSave($data, $id);';
        }

        $this->lines[] = '    }';
    }

    /**
     * Undocumented function
     *
     * @param string $modelNamespace
     * @param string $table
     * @param array $data
     * @param array $relations
     * @param string $prefix
     * @return array
     */
    public function getModelLines($modelNamespace, $table, $data, $relations, $prefix)
    {
        $modelName = Loader::parseName($table, 1);

        $modelTitle = $data['model_title'];

        $lines = [];
        $lines[] = "<?php";
        $lines[] = '';
        $lines[] = "namespace {$modelNamespace};";
        $lines[] = '';
        $lines[] = "use think\Model;";

        $dbLogic  = new DbLogic;

        $solft_delete = $dbLogic->getFieldInfo($prefix . $table, 'delete_time') ? 1 : 0;

        $create_time = $dbLogic->getFieldInfo($prefix . $table, 'create_time') ? 1 : 0;
        $update_time = $dbLogic->getFieldInfo($prefix . $table, 'update_time') ? 1 : 0;

        if ($solft_delete == 1) {
            $lines[] = "use think\Model\concern\SoftDelete;";
        }

        $lines[] = '';

        $lines[] = '/**';
        $lines[] = ' * @time tpextmanager 生成于' . date('Y-m-d H:i:s');
        $lines[] = ' * @title ' . $modelTitle;
        $lines[] = ' */';

        $lines[] = 'class ' . $modelName . ' extends Model';
        $lines[] = '{';



        if ($solft_delete) {
            $lines[] = "use SoftDelete;";
        }

        $lines[] = "    protected \$name = '{$table}';";
        $lines[] = '';
        $lines[] = '    protected $autoWriteTimestamp = \'datetime\';';

        if (!$create_time) {
            $lines[] = '';
            $lines[] = '    protected $createTime = false;';
        }

        if (!$update_time) {
            $lines[] = '';
            $lines[] = '    protected $updateTime = false;';
        }

        if (!empty($data['TABLE_FIELDS'])) {
            foreach ($data['TABLE_FIELDS'] as $field) {

                if ($field['DISPLAYER_TYPE'] == 'belongsTo') {
                    if (empty($field['FIELD_RELATION'])) {
                        continue;
                    }

                    $relationName = explode('.', $field['FIELD_RELATION'])[0];

                    $find = false;
                    foreach ($relations as $rl) {

                        if ($rl['local_table_name'] == $prefix . $table && $rl['foreign_key'] == $field['COLUMN_NAME']) {
                            $find = true;
                            break;
                        }
                    }

                    if (!$find) { //未找到设置的关联

                        $foreignTableName = $prefix . preg_replace('/_id$/', '', $field['COLUMN_NAME']); //关联表名
                        if ($dbLogic->getTableInfo($foreignTableName, 'TABLE_NAME')) { //表是否存在

                            $foreignModelname = Loader::parseName(preg_replace('/_id$/', '', $field['COLUMN_NAME']), 1);

                            $lines[] = '';
                            $lines[] = "    public function {$relationName}()";
                            $lines[] = '    {';
                            $lines[] = "        return \$this->belongsTo({$foreignModelname}::class, '{$field['COLUMN_NAME']}');";
                            $lines[] = '    }';
                        }
                    }
                }
            }
        }

        foreach ($relations as &$rl) {
            $foreignModelname = Loader::parseName(preg_replace('/^' . $prefix . '(.+)$/', '$1', $rl['foreign_table_name']), 1);

            $lines[] = '';
            $lines[] = "    public function {$rl['relation_name']}()";
            $lines[] = '    {';
            if ($rl['relation_type'] == 'belongs_to') {
                $lines[] = "        return \$this->belongsTo({$foreignModelname}::class, '{$rl['foreign_key']}', '{$rl['local_key']}');";
            } else { //has_one
                $lines[] = "        return \$this->hasOne({$foreignModelname}::class, '{$rl['foreign_key']}', '{$rl['local_key']}');";
            }
            $lines[] = '    }';
        }
        $lines[] = '}';
        $lines[] = '';

        return $lines;
    }

    /**
     * Undocumented function
     *
     * @param string $modelFileName
     * @param array $relations
     * @param string $prefix
     * @return array
     */
    public function getModelRelationLines($modelFileName, $relations, $prefix)
    {
        $lines = [];

        $fileHandle = fopen($modelFileName, "r");

        $find = 0;

        while (!feof($fileHandle)) {
            $line = fgets($fileHandle);

            foreach ($relations as &$rl) {
                if (preg_match('/public\s+function\s+' . $rl['relation_name'] . '\s*\(\)/i', $line)) {
                    $rl['find'] = 1;
                    $find += 1;
                    break;
                }
            }

            $lines[] = $line;
        }

        fclose($fileHandle);

        if (count($relations) > $find) //关联不完整
        {
            $count = count($lines);

            for ($i = $count - 1; $i >= 0; $i -= 1) {
                $line = $lines[$i];
                unset($lines[$i]);

                if (strpos($line, '}') !== false) { //最后一个}符号，class结束符
                    break;
                }
            }

            $newLine = implode('', $lines);

            $lines = [$newLine];

            $r = 0;
            foreach ($relations as &$rl) {
                if (!isset($rl['find'])) {
                    $r += 1;
                    $foreignModelname = Loader::parseName(preg_replace('/^' . $prefix . '(.+)$/', '$1', $rl['foreign_table_name']), 1);

                    if ($r > 1) {
                        $lines[] = '';
                    }

                    $lines[] = "    public function {$rl['relation_name']}()";
                    $lines[] = '    {';
                    if ($rl['relation_type'] == 'belongs_to') {
                        $lines[] = "        return \$this->belongsTo({$foreignModelname}::class, '{$rl['foreign_key']}', '{$rl['local_key']}');";
                    } else { //has_one
                        $lines[] = "        return \$this->hasOne({$foreignModelname}::class, '{$rl['foreign_key']}', '{$rl['local_key']}');";
                    }
                    $lines[] = '    }';
                }
            }
            $lines[] = '}'; //class结束符
        } else {
            $newLine = implode('', $lines);

            $lines = [$newLine];
            //关联完整，原样返回
        }

        return $lines;
    }

    /**
     * Undocumented function
     *
     * @param array $data
     * @param array $fields
     * @return array
     */
    public function getLangLines($data, $fields)
    {
        $lines = [];

        $tableTitle = $data['controller_title'];

        $lines[] = "<?php";
        $lines[] = '';
        $lines[] = '/**';
        $lines[] = ' * @time tpextmanager 生成于' . date('Y-m-d H:i:s');
        $lines[] = ' * @title ' . $tableTitle;
        $lines[] = ' */';
        $lines[] = '';
        $lines[] = "return [";
        if (!empty($fields)) {

            foreach ($fields as $field) {

                if (empty($field['COLUMN_NAME'])) {
                    continue;
                }

                foreach ($data['FORM_FIELDS'] as $formField) {

                    if ($field['COLUMN_NAME'] == $formField['COLUMN_NAME']) {
                        $field['COLUMN_COMMENT'] = $formField['COLUMN_COMMENT'];
                        break;
                    }
                }

                if ($field['COLUMN_NAME'] == 'id' && $field['COLUMN_COMMENT'] == '主键') {
                    $field['COLUMN_COMMENT'] = '编号';
                } else if (preg_match('/^(.+?)id$/', $field['COLUMN_COMMENT'], $mch)) {
                    $field['COLUMN_COMMENT'] = $mch[1];
                }

                $lines[] = "    '{$field['COLUMN_NAME']}'  => '{$field['COLUMN_COMMENT']}',";

                if (preg_match('/^\w*?(time|date)$/', $field['COLUMN_NAME'])) {
                    if (!isset($fields[$field['COLUMN_NAME'] . '_start'])) {
                        $lines[] = "    '{$field['COLUMN_NAME']}_start'  => '{$field['COLUMN_COMMENT']}起',";
                    }
                    if (!isset($fields[$field['COLUMN_NAME'] . '_end'])) {
                        $lines[] = "    '{$field['COLUMN_NAME']}_end'  => '{$field['COLUMN_COMMENT']}止',";
                    }
                }
            }
        }
        $lines[] = '];';
        $lines[] = '';

        return $lines;
    }

    /**
     * Undocumented function
     *
     * @param string $dir
     * @param string $file
     * @param string $content
     * @return boolean
     */
    public function saveFile($dir, $file, $content)
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (is_file($file)) {
            file_put_contents($file . '.' . date('YmdHis') . '.bak', file_get_contents($file));
        }

        return file_put_contents($file, $content);
    }
}
