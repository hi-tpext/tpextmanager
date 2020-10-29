<?php

namespace tpext\manager\common\logic;

use think\Loader;

class CreatorLogic
{
    protected $lines = [];

    protected $autoDisplayers = ['text', 'textarea', 'select', 'multipleSelect', 'switchBtn', 'radio', 'checkbox'];

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
                if (!in_array('actions\HasAutopost', $actions) && in_array($field['DISPLAYER_TYPE'], $this->autoDisplayers)) {
                    $actions[] = 'actions\HasAutopost';
                }
            }
        }

        return $actions;
    }

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

    public function begin($data)
    {
        $prefix = config('database.prefix');

        $table = preg_replace('/^' . $prefix . '(.+)$/', '$1', $data['TABLE_NAME']);

        $namespace = '';
        $controllerName = '';

        if (preg_match('/^\w+$/', $data['CONTROLLER'])) {
            $namespace = 'app\\admin\\controller';
            $controllerName = ucfirst(strtolower(Loader::parseName($data['CONTROLLER'], 1)));
        } else if (preg_match('/^(\w+)[\/](\w+)$/', $data['CONTROLLER'], $mch)) {
            $namespace = 'app\\admin\\controller\\' . strtolower($mch[1]);
            $controllerName = ucfirst(strtolower(Loader::parseName($mch[2], 1)));
        }

        $modelName = Loader::parseName($table, 1);

        $controllerTitle = $data['controller_title'];

        $this->lines[] = "<?php";
        $this->lines[] = '';
        $this->lines[] = "namespace {$namespace};";
        $this->lines[] = '';
        $this->lines[] = "use app\\common\\model\\{$modelName} as {$modelName}Model;";
        $this->lines[] = "use think\\Controller;";
        $this->lines[] = "use tpext\\builder\\traits\actions;";
        $this->lines[] = '';

        $this->lines[] = '/**';
        $this->lines[] = ' * tpextmanager 生成于' . date('Y-m-d H:i:s');
        $this->lines[] = ' * Undocumented class';
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
        $this->lines[] = '    protected function initialize()';
        $this->lines[] = '    {';
        $this->lines[] = "        \$this->dataModel = new {$modelName}Model;";
        $this->lines[] = "        \$this->pageTitle = '{$controllerTitle}';";
        $this->lines[] = "        \$this->selectTextField = '{id}#{name}';";
        $this->lines[] = "        \$this->selectSearch = 'name';";
        $this->lines[] = '    }';

    }

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
     * @return array
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
        if (!empty($data['TABLE_FIELDS'])) {

            $line = '';
            $sortable = [];
            foreach ($data['TABLE_FIELDS'] as $field) {

                if (empty($field['DISPLAYER_TYPE']) || $field['DISPLAYER_TYPE'] == '_') {
                    continue;
                }

                $line = '        $table->' . $field['DISPLAYER_TYPE'] . "('{$field['COLUMN_NAME']}', '{$field['COLUMN_COMMENT']}')";

                if (in_array($field['DISPLAYER_TYPE'], $this->autoDisplayers)) {
                    $line .= '->autoPost()';
                    if ($field['DISPLAYER_TYPE'] == 'text') {
                        $line .= "->getWrapper()->addStyle('max-width:140px;')";
                    }
                }

                if (in_array($field['DISPLAYER_TYPE'], ['match', 'maches'])) {
                    if (!empty($field['FIELD_RELATION']) && preg_match('/^(\w+)\[(\w+), (\w+)\]$/i', trim($field['FIELD_RELATION']), $mch)) {
                        $line .= "->optionsData(db('{$mch[1]}')->select(), '{$mch[2]}', '{$mch[3]}')";
                    } else {
                        $line .= "->optionsData(db('table_name')->select(), 'text')";
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
                $this->lines[] = '        $table->hasExport(false);';
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
                $this->lines[] = '        $table->sortable(\'' . implode(',', $sortable) . '\');';
            }
        }
        $this->lines[] = '    }';
    }

    /**
     * Undocumented function
     *
     * @param array $data
     * @return array
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
        $this->lines[] = '        $searchData = request()->post();';
        if (!empty($data['TABLE_FIELDS'])) {

            foreach ($data['TABLE_FIELDS'] as $field) {

                if (isset($field['ATTR']) && in_array('search', $field['ATTR'])) {

                    $this->lines[] = '';

                    $this->lines[] = "        if (isset(\$searchData['{$field['COLUMN_NAME']}']) && \$searchData['{$field['COLUMN_NAME']}'] != '') {";

                    if (preg_match('/varchar|text/i', $field['COLUMN_TYPE'])) {
                        $this->lines[] = "            \$where[] = ['{$field['COLUMN_NAME']}', 'like', '%' . \$searchData['{$field['COLUMN_NAME']}' . '%']];";
                    } else {
                        $this->lines[] = "            \$where[] = ['{$field['COLUMN_NAME']}', 'eq', \$searchData['{$field['COLUMN_NAME']}']];";
                    }

                    $this->lines[] = '        }';
                }

            }
        }
        $this->lines[] = '    }';
    }

    /**
     * Undocumented function
     *
     * @param array $data
     * @return array
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
        if (!empty($data['TABLE_FIELDS'])) {

            foreach ($data['TABLE_FIELDS'] as $field) {

                if (isset($field['ATTR']) && in_array('search', $field['ATTR'])) {
                    $this->lines[] = '        $search->text' . "('{$field['COLUMN_NAME']}', '{$field['COLUMN_COMMENT']}');";
                }

            }
        }
        $this->lines[] = '    }';
    }

    /**
     * Undocumented function
     *
     * @param array $data
     * @return array
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
        if (!empty($data['FORM_FIELDS'])) {

            $line = '';
            foreach ($data['FORM_FIELDS'] as $field) {

                if (empty($field['DISPLAYER_TYPE']) || $field['DISPLAYER_TYPE'] == '_') {
                    continue;
                }
                $line = '        $form->' . $field['DISPLAYER_TYPE'] . "('{$field['COLUMN_NAME']}', '{$field['COLUMN_COMMENT']}')";

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
        }
        $this->lines[] = '    }';
    }

    /**
     * Undocumented function
     *
     * @param array $data
     * @return array
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
            $this->lines[] = '';
            $this->lines[] = '        if (true !== $result) {';
            $this->lines[] = '            $this->error($result);';
            $this->lines[] = '        }';
            $this->lines[] = '';

            $this->lines[] = '        return $this->doSave($data, $id);';
        }

        $this->lines[] = '    }';
    }
}
