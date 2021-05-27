<?php

namespace tpext\manager\common\logic;

use think\facade\Db;
use think\helper\Str;

class CreatorLogic
{
    protected $lines = [];

    protected $autoPostDisplayers = ['text', 'textarea', 'select', 'multipleSelect', 'switchBtn', 'radio', 'checkbox'];

    protected $database = '';

    protected $prefix = '';

    protected $config = [];

    public function __construct()
    {
        $type = Db::getConfig('default', 'mysql');

        $connections = Db::getConfig('connections');

        $this->config = $connections[$type] ?? [];

        if (empty($this->config) || empty($this->config['database'])) {
            return;
        }

        $this->database = $this->config['database'];
        $this->prefix = $this->config['prefix'];
    }

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
     * @return void
     */
    public function make($data)
    {
        $this->begin($data);

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
     * @return void
     */
    public function begin($data)
    {
        $table = preg_replace('/^' . $this->prefix . '(.+)$/', '$1', $data['TABLE_NAME']);

        $controllerNamespace = '';
        $controllerName = '';
        $modelNamespace = '';

        if (preg_match('/^\w+$/', $data['controller'])) {
            $controllerNamespace = 'app\\admin\\controller';
            $controllerName = ucfirst(strtolower(Str::studly($data['controller'])));
        } else if (preg_match('/^(\w+)[\/](\w+)$/', $data['controller'], $mch)) {
            $controllerNamespace = 'app\\admin\\controller\\' . strtolower($mch[1]);
            $controllerName = ucfirst(strtolower(Str::studly($mch[2])));
        }

        if ($data['model_namespace'] == 'common') {
            $modelNamespace = 'app\\common\\model\\';
        } else {
            $modelNamespace = 'app\\admin\\model\\';
        }

        $modelName = Str::studly($table);

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

        $tableToolbars = $data['table_toolbars'] ?? [];
        $tableActions = $data['table_actions'] ?? [];

        if (in_array('enable', $tableToolbars) || in_array('enable', $tableActions)) {
            $this->lines[] = '';
            $enable_field = $data['enable_field'] ?: 'enable';
            $this->lines[] = "        \$this->enableField = '{$enable_field}';";
        }

        $this->lines[] = '';
        $this->lines[] = "        Lang::load(app()->getRootPath() . implode(DIRECTORY_SEPARATOR, ['app', 'admin', 'lang', config('lang.default_lang'), '" . strtolower($modelName) . "' . '.php']));";
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
        $this->lines[] = '    protected function buildTable(&$data = [])';
        $this->lines[] = '    {';
        $this->lines[] = '        $table = $this->table;';
        $this->lines[] = '';
        if (!empty($data['TABLE_FIELDS'])) {

            $line = '';
            $sortable = [];
            foreach ($data['TABLE_FIELDS'] as $field) {

                if (empty($field['DISPLAYER_TYPE']) || $field['DISPLAYER_TYPE'] == '_') {
                    continue;
                }

                if ($field['DISPLAYER_TYPE'] == 'belongsTo') {
                    $line = '        $table->show' . "('{$field['FIELD_RELATION']}', lang('{$field['COLUMN_NAME']}'))";
                } else {
                    $line = '        $table->' . $field['DISPLAYER_TYPE'] . "('{$field['COLUMN_NAME']}')";
                }

                if (in_array($field['DISPLAYER_TYPE'], $this->autoPostDisplayers)) {
                    $line .= '->autoPost()';
                    if ($field['DISPLAYER_TYPE'] == 'text') {
                        $line .= "->getWrapper()->addStyle('max-width:140px;')";
                    }
                }

                if (in_array($field['DISPLAYER_TYPE'], ['match', 'maches'])) {
                    if (!empty($field['FIELD_RELATION']) && preg_match('/^(\w+)\[(\w+), (\w+)\]$/i', trim($field['FIELD_RELATION']), $mch)) {
                        $line .= "->optionsData(\\think\\facade\\Db::name('{$mch[1]}')->select(), '{$mch[2]}', '{$mch[3]}')";
                    } else {
                        $line .= "->optionsData(\\think\\facade\\Db::name('table_name')->select(), 'text')/*请修改table_name为关联表名*/";
                    }
                }

                if (isset($field['ATTR']) && in_array('sortable', $field['ATTR'])) {
                    $sortable[] = $field['COLUMN_NAME'];
                }

                $line .= ';';

                $this->lines[] = $line;
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

                    $this->lines[] = '';

                    if (preg_match('/^\w*?(time|date)$/', $field['COLUMN_NAME'])) {
                        $this->lines[] = "        if (isset(\$searchData['{$field['COLUMN_NAME']}_start']) && \$searchData['{$field['COLUMN_NAME']}_start'] != '') {";

                        $this->lines[] = "            \$where[] = ['{$field['COLUMN_NAME']}', '>=', \$searchData['{$field['COLUMN_NAME']}_start']];";
                        $this->lines[] = '        }';

                        $this->lines[] = '';
                        $this->lines[] = "        if (isset(\$searchData['{$field['COLUMN_NAME']}_end']) && \$searchData['{$field['COLUMN_NAME']}_end'] != '') {";

                        $this->lines[] = "            \$where[] = ['{$field['COLUMN_NAME']}', '<=', \$searchData['{$field['COLUMN_NAME']}_end']];";
                        $this->lines[] = '        }';
                    } else {

                        $this->lines[] = "        if (isset(\$searchData['{$field['COLUMN_NAME']}']) && \$searchData['{$field['COLUMN_NAME']}'] != '') {";

                        if (preg_match('/varchar|text/i', $field['COLUMN_TYPE'])) {
                            $this->lines[] = "            \$where[] = ['{$field['COLUMN_NAME']}', 'like', '%' . \$searchData['{$field['COLUMN_NAME']}'] . '%'];";
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
        $this->lines[] = '     * 构建搜索条件';
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

                $line = '        $form->' . $field['DISPLAYER_TYPE'] . "('{$field['COLUMN_NAME']}')";

                if (in_array($field['DISPLAYER_TYPE'], ['text', 'textarea']) && preg_match('/^varchar\((\d+)\)$/i', $field['COLUMN_TYPE'], $mch)) {
                    $line .= '->maxlength(' . $mch[1] . ')';
                }

                if (in_array($field['DISPLAYER_TYPE'], ['select', 'multipleSelect'])) {
                    if (!empty($field['FIELD_RELATION'])) {
                        $line .= "->dataUrl(url('{$field['FIELD_RELATION']}'))";
                    } else {
                        $line .= "->dataUrl(url('selectpage'))";
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
     * @return array
     */
    public function getModelLines($modelNamespace, $table, $data)
    {
        $modelName = Str::studly($table);

        $tableTitle = $data['controller_title'];

        $lines = [];
        $lines[] = "<?php";
        $lines[] = '';
        $lines[] = "namespace {$modelNamespace};";
        $lines[] = '';
        $lines[] = "use think\Model;";

        if ($data['solft_delete'] == 1) {
            $lines[] = "use think\Model\concern\SoftDelete;";
        }

        $lines[] = '';

        $lines[] = '/**';
        $lines[] = ' * @time tpextmanager 生成于' . date('Y-m-d H:i:s');
        $lines[] = ' * @title ' . $tableTitle;
        $lines[] = ' */';

        $lines[] = 'class ' . $modelName . ' extends Model';
        $lines[] = '{';

        if ($data['solft_delete'] == 1) {
            $lines[] = "use SoftDelete;";
        }
        $lines[] = "    //protected \$name = '{$table}'";
        $lines[] = '';
        $lines[] = '    protected $autoWriteTimestamp = \'datetime\';';
        $lines[] = '';
        $lines[] = '}';
        $lines[] = '';

        return $lines;
    }

    /**
     * Undocumented function
     *
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

                foreach ($data['FORM_FIELDS'] as $formField) {

                    if ($field['COLUMN_NAME'] == $formField['COLUMN_NAME']) {
                        $field['COLUMN_COMMENT'] = $formField['COLUMN_COMMENT'];
                    }
                }

                if (preg_match('/^(.+?)id$/', $field['COLUMN_COMMENT'], $mch)) {
                    $field['COLUMN_COMMENT'] = $mch[1];
                }

                $lines[] = "    '{$field['COLUMN_NAME']}'  => '{$field['COLUMN_COMMENT']}',";

                if (preg_match('/^\w*?(time|date)$/', $field['COLUMN_NAME'])) {
                    $lines[] = "    '{$field['COLUMN_NAME']}_start'  => '{$field['COLUMN_COMMENT']}起',";
                    $lines[] = "    '{$field['COLUMN_NAME']}_end'  => '{$field['COLUMN_COMMENT']}止',";
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
