<?php

namespace tpext\manager\admin\controller;

use think\Controller;
use think\Db;
use think\Loader;
use tpext\builder\common\Wrapper;
use tpext\builder\traits\actions\HasBase;
use tpext\builder\traits\actions\HasIndex;
use tpext\manager\common\logic\CreatorLogic;
use tpext\manager\common\logic\DbLogic;

/**
 * Undocumented class
 * @title 构建器
 */
class Creator extends Controller
{
    use HasBase;
    use HasIndex;

    /**
     * Undocumented variable
     *
     * @var CreatorLogic
     */
    protected $creatorLogic;

    /**
     * Undocumented variable
     *
     * @var DbLogic
     */
    protected $dbLogic;

    protected function initialize()
    {
        $this->pageTitle = '构建器';

        $this->database = config('database.database');

        $this->pk = 'TABLE_NAME';

        $this->creatorLogic = new CreatorLogic;
        $this->dbLogic = new DbLogic;
    }

    protected function filterWhere()
    {
        $searchData = request()->post();

        $where = '';

        if (!empty($searchData['kwd'])) {
            $where .= " AND (`TABLE_NAME` LIKE '%{$searchData['kwd']}%' OR `TABLE_COMMENT` LIKE '%{$searchData['kwd']}%')";
        }

        return $where;
    }

    /**
     * 构建搜索
     *
     * @return void
     */
    protected function buildSearch()
    {
        $search = $this->search;

        $search->text('kwd', '表名/表注释', 4)->maxlength(55);
    }

    protected function buildDataList()
    {
        $sortOrder = input('__sort__', 'TABLE_NAME ASC');
        $where = $this->filterWhere();
        $table = $this->table;

        $data = $this->dbLogic->getTables('TABLE_NAME,CREATE_TIME,TABLE_COMMENT', $where, $sortOrder);

        $this->buildTable($data);
        $table->fill($data);
        $table->sortOrder($sortOrder);

        return $data;
    }

    public function edit($id)
    {
        if (request()->isPost()) {
            return $this->save($id);
        }

        $builder = $this->builder($this->pageTitle, $this->editText);
        $data = $this->dbLogic->getTableInfo($id);
        if (!$data) {
            return $builder->layer()->close(0, '数据不存在');
        }
        $form = $builder->form();
        $this->form = $form;
        $this->buildForm(true, $data);
        $form->fill($data);

        return $builder->render();
    }

    /**
     * 构建表单
     *
     * @param boolean $isEdit
     * @param array $data
     */
    protected function buildForm($isEdit, &$data = [])
    {
        $prefix = config('database.prefix');

        $table = preg_replace('/^' . $prefix . '(.+)$/', '$1', $data['TABLE_NAME']);

        $form = $this->form;
        $fields = $this->dbLogic->getFields($data['TABLE_NAME'], 'COLUMN_NAME,COLUMN_TYPE,COLUMN_DEFAULT,COLUMN_COMMENT,IS_NULLABLE,NUMERIC_SCALE,NUMERIC_PRECISION,CHARACTER_MAXIMUM_LENGTH,DATA_TYPE');
        $form->text('TABLE_NAME', '表名')->readonly();
        $form->text('controller_title', '控制器名称')->default($data['TABLE_COMMENT'])->required();
        $form->switchBtn('table_build', '表格生成')->default(1);
        $form->checkbox('table_toolbars', '表格工具')->options(['add' => '添加', 'delete' => '批量删除', 'export' => '导出', 'enable' => '批量禁用/启用', 'import' => '导入'])
            ->default('add,delete,export')->checkallBtn()->help('未选择任何一项则禁用工具栏');
        $form->checkbox('table_actions', '表格动作')->options(['edit' => '编辑', 'view' => '查看', 'delete' => '删除', 'enable' => '禁用/启用'])
            ->default('edit,view,delete')->checkallBtn()->help('未选择任何一项则禁用动作栏');
        $form->text('enable_field', '禁用/启用字段名称')->help('若使用[禁用/启用]工具栏、动作栏');

        foreach ($fields as &$f) {
            $f['FIELD_TYPE'] = 'show';
            $f['ATTR'] = ['search'];
            $f['FIELD_RELATION'] = '';

            if ($this->dbLogic->isInteger($f['DATA_TYPE'])
                || $this->dbLogic->isDecimal($f['DATA_TYPE'])
                || (preg_match('/^(?:created?_time|add_time|created?_at|updated?_time|updated?_at)$/', $f['COLUMN_NAME']))) {
                $f['ATTR'][] = 'sortable';
            }

            if ($f['COLUMN_NAME'] == 'id') {
                $f['FIELD_TYPE'] = 'hidden';
            } else if (preg_match('/^(?:parent_id|pid)$/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = 'match';
                $f['FIELD_RELATION'] = strtolower($table) . '[id, text]';
            } else if (preg_match('/^(\w+)_id$/', $f['COLUMN_NAME'], $mch)) {
                $f['FIELD_TYPE'] = 'match';
                $f['FIELD_RELATION'] = strtolower($mch[1]) . '[id, text]';
            } else if (preg_match('/^(\w+)_ids$/', $f['COLUMN_NAME'], $mch)) {
                $f['FIELD_TYPE'] = 'matches';
                $f['FIELD_RELATION'] = strtolower($mch[1]) . '[id, text]';
            } else if (preg_match('/^\w*?(?:img|image|pic|photo|avatar|logo)$/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = 'image';
            } else if (preg_match('/^\w*?(?:img|image|pic|photo|avatar|logo)s$/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = 'images';
            } else if (preg_match('/^\w*?(?:file|video|audio|pkg)$/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = 'file';
            } else if (preg_match('/^\w*?(?:file|video|audio|pkg)s$/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = 'files';
            } else if (preg_match('/^is_\w+|enabled?$/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = 'switchBtn';
            } else if (preg_match('/^\w*?(?:status|state)$/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = 'match';
            } else if (preg_match('/^\w*?(?:password|pwd)$/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = '_';
            } else if (preg_match('/^\w*?(?:openid|salt|token)$/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = '_';
            } else if (preg_match('/^\w*?delete_time $/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = '_';
            }
        }

        $form->items('table_fields', ' ')->dataWithId($fields, 'COLUMN_NAME')->size(0, 12)->showLabel(false)
            ->with(
                $form->show('COLUMN_NAME', '字段名')->required(),
                $form->show('COLUMN_TYPE', '字段类型')->readonly()->getWrapper()->addStyle('width:140px;'),
                $form->text('COLUMN_COMMENT', '字段注释')->required(),
                $form->select('FIELD_TYPE', '生成类型')->texts(array_keys(Wrapper::getDisplayerMap()))
                    ->beforOptions(['_' => '无'])->required(),
                $form->checkbox('ATTR', '属性')->options(['sortable' => '排序', 'search' => '搜索']),
                $form->text('FIELD_RELATION', '其他信息')
            )->canNotAddOrDelete();

        foreach ($fields as &$f) {
            $f['FIELD_TYPE'] = 'text';
            $f['FIELD_RELATION'] = '';
            $f['ATTR'] = [];

            if ($f['COLUMN_NAME'] == 'id') {
                $f['FIELD_TYPE'] = 'hidden';
            } else if (preg_match('/^(?:parent_id|pid)$/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = 'select';
                $f['FIELD_RELATION'] = url('/admin/' . strtolower(Loader::parseName($table, 1)) . '/selectpage', [], false);
            } else if (preg_match('/^(\w+)_id$/', $f['COLUMN_NAME'], $mch)) {
                $f['FIELD_TYPE'] = 'select';
                $f['FIELD_RELATION'] = url('/admin/' . strtolower(Loader::parseName($mch[1], 1)) . '/selectpage', [], false);
            } else if (preg_match('/^(\w+)_ids$/', $f['COLUMN_NAME'], $mch)) {
                $f['FIELD_TYPE'] = 'multipleSelect';
                $f['FIELD_RELATION'] = url('/admin/' . strtolower(Loader::parseName($mch[1], 1)) . '/selectpage', [], false);
            } else if (preg_match('/^\w*?(?:img|image|pic|photo|avatar|logo)$/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = 'image';
            } else if (preg_match('/^\w*?(?:img|image|pic|photo|avatar|logo)s$/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = 'images';
            } else if (preg_match('/^\w*?(?:file|video|audio|pkg)$/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = 'file';
            } else if (preg_match('/^\w*?(?:file|video|audio|pkg)s$/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = 'files';
            } else if (preg_match('/^\w*?icon$/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = 'icon';
            } else if (preg_match('/^(?:created?_time|add_time|created?_at|updated?_time|updated?_at)$/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = 'datetime';
            } else if (preg_match('/^\w*?time$/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = 'datetime';
            } else if (preg_match('/^\w*?date$/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = 'date';
            } else if (preg_match('/^\w*?content$/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = 'editor';
            } else if (preg_match('/^\w*?(remark|desc|description)$/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = 'textarea';
            } else if (preg_match('/^is_\w+|enabled?$/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = 'switchBtn';
            } else if (preg_match('/^\w*?(?:status|state)$/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = 'checkbox';
            } else if (preg_match('/^\w*?(?:password|pwd)$/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = 'password';
            } else if (preg_match('/^\w*?(?:openid|salt|token)$/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = 'show';
            } else if (preg_match('/^\w*?(?:tags|kwds)$/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = 'tags';
            } else if (preg_match('/^\w*?map$/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = 'map';
            } else if (preg_match('/^\w*?color$/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = 'color';
            } else if (preg_match('/^\w*?(?:number|num|quantity|qty)$/', $f['COLUMN_NAME'])) {
                $f['FIELD_TYPE'] = 'number';
            }
        }

        $form->switchBtn('form_build', '表单生成')->default(1);
        $form->items('form_fields', ' ')->dataWithId($fields, 'COLUMN_NAME')->size(0, 12)->showLabel(false)
            ->with(
                $form->show('COLUMN_NAME', '字段名')->required(),
                $form->show('COLUMN_TYPE', '字段类型')->readonly()->getWrapper()->addStyle('width:140px;'),
                $form->text('COLUMN_COMMENT', '字段注释')->required(),
                $form->select('FIELD_TYPE', '生成类型')->texts(array_keys(Wrapper::getDisplayerMap()))
                    ->beforOptions(['_' => '无'])->required(),
                $form->checkbox('ATTR', '属性')->options(['required' => '必填']),
                $form->text('FIELD_RELATION', '其他信息')
            )->canNotAddOrDelete();

    }

    /**
     * Undocumented function
     *
     * @title 字段关系
     * @return mixed
     */
    public function relation()
    {
        $prev_ele_id = str_replace('form-FIELD_TYPE', '', input('prev_ele_id'));

        return json(Wrapper::getDisplayerMap());
    }

    /**
     * 保存数据 范例
     *
     * @param integer $id
     * @return mixed
     */
    private function save($id = 0)
    {
        $data = request()->post();

        if ($data['table_build'] == 0 && $data['form_build'] == 0) {
            $this->error('请选择创建表格或表单');
        }

        $tableToolbars = $data['table_toolbars'] ?? [];
        $tableActions = $data['table_actions'] ?? [];

        if ($data['form_build'] == 0 && (in_array('add', $tableToolbars) || in_array('edit', $tableActions) || in_array('view', $tableActions))) {
            $this->error('有添加/删除/查看时必须生成表格');
        }

        $this->creatorLogic->make($data);

        file_put_contents('t.php', implode("\n", $this->creatorLogic->getLins()));

        return $this->builder()->layer()->closeRefresh(1, '保存成功');
    }

    /**
     * 构建表格
     *
     * @return void
     */
    protected function buildTable(&$data = [])
    {
        $table = $this->table;

        $table->fields('表名')->with(
            $table->show('TABLE_NAME', '表名'),
            $table->show('TABLE_COMMENT', '表注释')
        )->getWrapper()->addStyle('width:260px');

        $table->show('CREATE_TIME', '创建时间')->getWrapper()->addStyle('width:160px');

        $table->getToolbar()
            ->btnRefresh()
            ->btnToggleSearch();

        $table->getActionbar()
            ->btnEdit();

        $table->useCheckbox(false);
    }
}
