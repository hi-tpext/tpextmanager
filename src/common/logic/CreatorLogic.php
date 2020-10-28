<?php

namespace tpext\manager\common\logic;

use think\Loader;

class CreatorLogic
{
    protected $lines = [];

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

            if (in_array('edit', $tableToolbars)) {
                $actions[] = 'actions\HasEdit';
            }

            if (in_array('view', $tableToolbars)) {
                $actions[] = 'actions\HasView';
            }

            if (in_array('delete', $tableToolbars) || in_array('delete', $tableActions)) {
                $actions[] = 'actions\HasDelete';
            }

            if (in_array('enable', $tableToolbars) || in_array('enable', $tableActions)) {
                $actions[] = 'actions\HasEnable';
            }

            foreach ($data['table_fields'] as $field) {
                if (in_array($field['FIELD_TYPE'], ['text', 'textarea', 'select', 'multipleSelect', 'switchBtn', 'radio', 'checkbox'])) {
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

        $modelName = Loader::parseName($table, 1);

        $controllerTitle = $data['controller_title'];

        $this->lines[] = "<?php";
        $this->lines[] = '';
        $this->lines[] = "namespace tpext\\myadmin\\admin\\controller;";
        $this->lines[] = "use think\\Controller;";
        $this->lines[] = "use tpext\\builder\\traits\actions;";
        $this->lines[] = "use app\\common\\model\\{$modelName} as {$modelName}Model;";
        $this->lines[] = '';

        $this->lines[] = '/**';
        $this->lines[] = ' * tpextmanager 生成于' . date('Y-m-d H:i:s');
        $this->lines[] = ' * Undocumented class';
        $this->lines[] = ' * @title ' . $controllerTitle;
        $this->lines[] = ' */';

        $this->lines[] = 'class Permission extends Controller';
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
    }

    /**
     * Undocumented function
     *
     * @param array $data
     * @return array
     */
    public function buildTable($data)
    {
        $this->lines[] = '';
        $this->lines[] = '    /**';
        $this->lines[] = '     * 构建表格';
        $this->lines[] = '     * @param array $data';
        $this->lines[] = '     * @return mixed';
        $this->lines[] = '     */';
        $this->lines[] = '    protected function buildTable(&$data = [])';
        $this->lines[] = '    {';
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
        $this->lines[] = '    }';
    }
}
