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

    protected $prefix;

    protected function initialize()
    {
        $this->pageTitle = '构建器';

        $this->database = config('database.database');

        $this->pk = 'TABLE_NAME';

        $this->creatorLogic = new CreatorLogic;

        $this->dbLogic = new DbLogic;

        $this->prefix = config('database.prefix');

        $this->sortOrder = 'TABLE_NAME ASC';
    }

    protected function filterWhere()
    {
        $searchData = request()->get();

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

    /**
     * 生成数据，如数据不是从`$this->dataModel`得来时，可重写此方法
     * 比如使用db()助手方法、多表join、或以一个自定义数组为数据源
     *
     * @param array $where
     * @param string $sortOrder
     * @param integer $page
     * @param integer $total
     * @return array|\think\Collection|\Generator
     */
    protected function buildDataList($where = [], $sortOrder = '', $page = 1, &$total = -1)
    {
        $data = $this->dbLogic->getTables('TABLE_NAME,CREATE_TIME,TABLE_COMMENT,TABLE_ROWS,AUTO_INCREMENT', $where, $sortOrder);

        $total = count($data);

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
        $table = preg_replace('/^' . $this->prefix . '(.+)$/', '$1', $data['TABLE_NAME']);

        $form = $this->form;
        $fields = $this->dbLogic->getFields($data['TABLE_NAME'], 'COLUMN_NAME,COLUMN_TYPE,COLUMN_DEFAULT,COLUMN_COMMENT,IS_NULLABLE,NUMERIC_SCALE,NUMERIC_PRECISION,CHARACTER_MAXIMUM_LENGTH,DATA_TYPE');
        $form->hidden('TABLE_NAME');
        $form->hidden('solft_delete')->value($this->dbLogic->getFieldInfo($data['TABLE_NAME'], 'delete_time') ? 1 : 0);
        $form->radio('model_namespace', 'model命名空间')->options(['common' => 'app\common\model', 'admin' => 'app\admin\model'])->default('common');
        $form->text('controller', 'Controller名称')->default(ucfirst(strtolower(Loader::parseName($table, 1))))->help('支持二级目录，如：shop/order');
        $form->text('controller_title', '控制器注释')->default($data['TABLE_COMMENT'])->required();

        $form->switchBtn('table_build', '表格生成')->default(1);
        $form->checkbox('table_toolbars', '表格工具')->options(['add' => '添加', 'delete' => '批量删除', 'export' => '导出', 'enable' => '批量禁用/启用', 'import' => '导入'])
            ->default('add,delete,export')->checkallBtn()->help('未选择任何一项则禁用工具栏');
        $form->checkbox('table_actions', '表格动作')->options(['edit' => '编辑', 'view' => '查看', 'delete' => '删除', 'enable' => '禁用/启用'])
            ->default('edit,view,delete')->checkallBtn()->help('未选择任何一项则禁用动作栏');
        $form->text('enable_field', '禁用/启用字段名称')->help('若使用[禁用/启用]工具栏、动作栏');

        foreach ($fields as &$field) {
            $field['DISPLAYER_TYPE'] = 'show';
            $field['ATTR'] = ['search'];
            $field['FIELD_RELATION'] = '';

            if ($this->dbLogic->isInteger($field['DATA_TYPE'])
                || $this->dbLogic->isDecimal($field['DATA_TYPE'])
                || (preg_match('/^.*(date|time)$/', $field['COLUMN_NAME']))) {
                $field['ATTR'][] = 'sortable';
            }

            if (preg_match('/^(?:parent_id|pid)$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = 'belongsTo';
                $field['FIELD_RELATION'] = 'parent.name';
            } else if (preg_match('/^(\w+)_id$/', $field['COLUMN_NAME'], $mch)) {
                $field['DISPLAYER_TYPE'] = 'belongsTo';
                $field['FIELD_RELATION'] = strtolower($mch[1]) . '.name';
            } else if (preg_match('/^(\w+)_ids$/', $field['COLUMN_NAME'], $mch)) {
                $field['DISPLAYER_TYPE'] = 'matches';
                $field['FIELD_RELATION'] = strtolower($mch[1]) . '[text, id]';
            } else if (preg_match('/^\w*?(?:img|image|pic|photo|avatar|logo)$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = 'image';
            } else if (preg_match('/^\w*?(?:img|image|pic|photo|avatar|logo)s$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = 'images';
            } else if (preg_match('/^\w*?(?:file|video|audio|pkg)$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = 'file';
            } else if (preg_match('/^\w*?(?:file|video|audio|pkg)s$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = 'files';
            } else if (preg_match('/^is_\w+|has_\w+|on_\w+|enabled?$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = 'switchBtn';
            } else if (preg_match('/^\w*?(?:status|state)$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = 'match';
            } else if (preg_match('/^\w*?(?:password|pwd)$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = '_';
            } else if (preg_match('/^\w*?(?:openid|salt|token)$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = '_';
            } else if (preg_match('/^(?:delete_time|delete_at)$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = '_';
            }
        }

        $form->items('TABLE_FIELDS', ' ')->dataWithId($fields, 'COLUMN_NAME')->size(0, 12)->showLabel(false)
            ->with(
                $form->text('COLUMN_NAME', '字段名')->readonly(),
                $form->text('COLUMN_TYPE', '字段类型')->readonly()->getWrapper()->addStyle('width:140px;'),
                $form->text('COLUMN_COMMENT', '字段注释')->readonly(),
                $form->select('DISPLAYER_TYPE', '生成类型')->texts(array_keys(Wrapper::getDisplayerMap()))
                    ->beforOptions(['_' => '无', 'belongsTo' => 'belongsTo'])->required(),
                $form->checkbox('ATTR', '属性')->options(['sortable' => '排序', 'search' => '搜索']),
                $form->text('FIELD_RELATION', '其他信息')
            )->canNotAddOrDelete();

        foreach ($fields as &$field) {
            $field['DISPLAYER_TYPE'] = 'text';
            $field['FIELD_RELATION'] = '';
            $field['ATTR'] = [];

            if ($field['COLUMN_NAME'] == 'id') {
                $field['DISPLAYER_TYPE'] = 'hidden';
            } else if (preg_match('/^(?:parent_id|pid)$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = 'select';
                $field['FIELD_RELATION'] = 'selectpage';
            } else if (preg_match('/^(\w+)_id$/', $field['COLUMN_NAME'], $mch)) {
                $field['DISPLAYER_TYPE'] = 'select';
                $field['FIELD_RELATION'] = '/admin/' . strtolower(Loader::parseName($mch[1], 1)) . '/selectpage';
            } else if (preg_match('/^(\w+)_ids$/', $field['COLUMN_NAME'], $mch)) {
                $field['DISPLAYER_TYPE'] = 'multipleSelect';
                $field['FIELD_RELATION'] = '/admin/' . strtolower(Loader::parseName($mch[1], 1)) . '/selectpage';
            } else if (preg_match('/^\w*?(?:img|image|pic|photo|avatar|logo)$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = 'image';
            } else if (preg_match('/^\w*?(?:img|image|pic|photo|avatar|logo)s$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = 'images';
            } else if (preg_match('/^\w*?(?:file|video|audio|pkg)$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = 'file';
            } else if (preg_match('/^\w*?(?:file|video|audio|pkg)s$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = 'files';
            } else if (preg_match('/^\w*?icon$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = 'icon';
            } else if (preg_match('/^(?:created?_time|add_time|created?_at|updated?_time|updated?_at)$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = 'show';
            } else if (preg_match('/^\w*?time$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = 'datetime';
            } else if (preg_match('/^\w*?date$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = 'date';
            } else if (preg_match('/^\w*?content$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = 'editor';
            } else if (preg_match('/^\w*?(remark|desc|description)$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = 'textarea';
            } else if (preg_match('/^is_\w+|has_\w+|on_\w+|enabled?$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = 'switchBtn';
            } else if (preg_match('/^\w*?(?:status|state)$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = 'radio';
            } else if (preg_match('/^\w*?(?:password|pwd)$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = 'password';
            } else if (preg_match('/^\w*?(?:openid|salt|token)$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = 'show';
            } else if (preg_match('/^\w*?(?:tags|kwds)$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = 'tags';
            } else if (preg_match('/^\w*?map$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = 'map';
            } else if (preg_match('/^\w*?color$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = 'color';
            } else if (preg_match('/^\w*?(?:number|num|quantity|qty)$/', $field['COLUMN_NAME'])) {
                $field['DISPLAYER_TYPE'] = 'number';
            }
        }

        $form->switchBtn('form_build', '表单生成')->default(1);
        $form->items('FORM_FIELDS', ' ')->dataWithId($fields, 'COLUMN_NAME')->size(0, 12)->showLabel(false)
            ->with(
                $form->text('COLUMN_NAME', '字段名')->readonly(),
                $form->text('COLUMN_TYPE', '字段类型')->readonly()->getWrapper()->addStyle('width:140px;'),
                $form->text('COLUMN_COMMENT', '字段注释')->required(),
                $form->select('DISPLAYER_TYPE', '生成类型')->texts(array_keys(Wrapper::getDisplayerMap()))
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
        $prev_ele_id = str_replace('form-DISPLAYER_TYPE', '', input('prev_ele_id'));

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

        $dir = '';
        $controllerName = '';

        if (preg_match('/^\w+$/', $data['controller'])) {
            $dir = env('root_path') . implode(DIRECTORY_SEPARATOR, ['application', 'admin', 'controller', '']);

            $controllerName = ucfirst(strtolower(Loader::parseName($data['controller'], 1)));
        } else if (preg_match('/^(\w+)[\/](\w+)$/', $data['controller'], $mch)) {
            $dir = env('root_path') . implode(DIRECTORY_SEPARATOR, ['application', 'admin', 'controller', strtolower($mch[1]), '']);

            $controllerName = ucfirst(strtolower(Loader::parseName($mch[2], 1)));
        } else {
            $this->error('控制器名称有误');
        }

        $fileName = $dir . $controllerName . '.php';

        if (!$this->creatorLogic->saveFile($dir, $fileName, implode(PHP_EOL, $this->creatorLogic->getLins()))) {
            $this->error('控制器文件保失败：' . $fileName);
        }

        $modelNamespace = '';
        $mdir = '';
        if ($data['model_namespace'] == 'common') {
            $modelNamespace = 'app\\common\\model';
            $mdir = env('root_path') . implode(DIRECTORY_SEPARATOR, ['application', 'common', 'model', '']);
        } else {
            $modelNamespace = 'app\\admin\\model';
            $mdir = env('root_path') . implode(DIRECTORY_SEPARATOR, ['application', 'admin', 'model', '']);
        }

        $table = preg_replace('/^' . $this->prefix . '(.+)$/', '$1', $data['TABLE_NAME']);

        $modelName = Loader::parseName($table, 1);

        $modelFileName = $mdir . $modelName . '.php';

        $this->creatorLogic->saveFile($mdir, $modelFileName, implode(PHP_EOL, $this->creatorLogic->getModelLines($modelNamespace, $table, $data)));

        $fields = $this->dbLogic->getFields($data['TABLE_NAME'], 'COLUMN_NAME,COLUMN_COMMENT');

        $ldir = env('root_path') . implode(DIRECTORY_SEPARATOR, ['application', 'admin', 'lang', config('default_lang'), '']);

        $this->creatorLogic->saveFile($ldir, $ldir . strtolower($modelName) . '.php', implode(PHP_EOL, $this->creatorLogic->getLangLines($data, $fields)));

        return $this->builder()->layer()->closeRefresh(1, '生成成功，文件保存在：' . $fileName);
    }

    /**
     * 构建表格
     *
     * @return void
     */
    protected function buildTable(&$data = [])
    {
        $table = $this->table;

        $table->show('TABLE_NAME', '表名');
        $table->show('TABLE_COMMENT', '表注释');
        $table->raw('TABLE_ROWS', '记录条数');
        $table->show('AUTO_INCREMENT', '自增id');
        $table->show('CREATE_TIME', '创建时间');

        $table->getToolbar()
            ->btnRefresh()
            ->btnToggleSearch();

        $table->getActionbar()
            ->btnEdit();

        $table->useCheckbox(false);
    }
}
