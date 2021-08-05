<?php

namespace tpext\manager\admin\controller;

use think\Controller;
use think\facade\Db;
use tpext\builder\traits\actions\HasBase;
use tpext\builder\traits\actions\HasIndex;
use tpext\manager\common\logic\DbLogic;

/**
 * Undocumented class
 * @title 数据表管理
 */
class Dbtable extends Controller
{
    use HasBase;
    use HasIndex;

    protected $database = '';

    /**
     * Undocumented variable
     *
     * @var DbLogic
     */
    protected $dbLogic;

    protected $prefix;

    protected function initialize()
    {
        $this->pageTitle = '数据表管理';

        if (!config('app_debug')) {
            $this->indexText = '不建议在[正式环境]中使用这些功能！';
        }

        $this->pk = 'TABLE_NAME';

        $this->database = config('database.database');

        $this->dbLogic = new DbLogic;

        $this->prefix = $this->dbLogic->getPrefix();

        $this->sortOrder = 'TABLE_NAME ASC';

        $this->pagesize = 9999; //不产生分页
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
        $data = $this->dbLogic->getTables('TABLE_NAME,TABLE_ROWS,CREATE_TIME,TABLE_COLLATION,TABLE_COMMENT,ENGINE,AUTO_INCREMENT,AVG_ROW_LENGTH,INDEX_LENGTH', $where, $sortOrder);

        $total = count($data);

        return $data;
    }

    public function add()
    {
        if (request()->isPost()) {
            return $this->save();
        }

        $builder = $this->builder($this->pageTitle, $this->addText);
        $form = $builder->form();
        $data = [];
        $this->form = $form;
        $this->buildForm(false, $data);
        $form->fill($data);
        return $builder->render();
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
        $form = $this->form;

        $form->text('TABLE_NAME', '表名')->required()->maxlength(50)->help($isEdit ? ' 若非必要，请不要随意修改' : '英文字母或数字组成')->default($this->prefix);
        $form->text('TABLE_COMMENT', '表注释')->required()->maxlength(50)->help('表的描述说明');

        if ($isEdit) {

            $form->raw('fields', '字段管理')->value('<a href="#" id="go-fields">前往&gt;&gt;</a>');

            $url = url('fieldlist', ['name' => $data['TABLE_NAME']]);

            $this->builder()->addScript("

            $('#go-fields').click(function(){
                var index = parent.layer.getFrameIndex(window.name);

                parent.layer.style(index, {
                    width: ($(parent.window).width() * 0.98) + 'px',
                    left : ($(parent.window).width() * 0.01) + 'px',
                });

                location.href ='{$url}';
            });

            ");

            $data['DATA_SIZE'] = $this->dbLogic->getDataSize($data);

            $form->tab('基本信息');
            $form->show('TABLE_ROWS', '记录条数');
            $form->show('AUTO_INCREMENT', '自增id');
            $form->show('DATA_SIZE', '数据大小')->to('{val}MB');
            $form->show('TABLE_COLLATION', '排序规则');
            $form->show('ENGINE', '存储引擎');
            $form->show('CREATE_TIME', '创建时间');

            $form->tab('建表语句');
            $tableInfo = Db::query("SHOW CREATE TABLE `{$data['TABLE_NAME']}`");
            $form->raw('sql', ' ')->value(!empty($tableInfo) ? '<pre>' . $tableInfo[0]['Create Table'] . ';</pre>' : '-')->size(0, 12);
        } else {
            $pkdata = [
                ['id' => 'pk', 'COLUMN_NAME' => 'id', 'COLUMN_COMMENT' => '主键', 'DATA_TYPE' => 'int', 'LENGTH' => 10, 'ATTR' => 'auto_inc,unsigned', '__can_delete__' => 0],
                ['id' => 'create_time', 'COLUMN_NAME' => 'create_time', 'COLUMN_COMMENT' => '添加时间', 'DATA_TYPE' => 'datetime', 'LENGTH' => 0, 'ATTR' => '', '__can_delete__' => 1],
                ['id' => 'update_time', 'COLUMN_NAME' => 'update_time', 'COLUMN_COMMENT' => '更新时间', 'DATA_TYPE' => 'datetime', 'LENGTH' => 0, 'ATTR' => '', '__can_delete__' => 1],
            ];
            //预设字段，在此处就不允许再添加其他字段了。
            $form->items('fields', '字段信息')->dataWithId($pkdata)->canAdd(false)->size(2, 10)
                ->with(
                    $form->text('COLUMN_NAME', '字段名')->required(),
                    $form->text('COLUMN_COMMENT', '注释')->required(),
                    $form->select('DATA_TYPE', '类型')->options($this->dbLogic::$FIELD_TYPES)->required()->getWrapper()->addStyle('width:160px;'),
                    $form->text('LENGTH', '长度')->getWrapper()->addStyle('width:100px;'),
                    $form->checkbox('ATTR', '属性')->options(['auto_inc' => '自增', 'unsigned' => '非负'])->getWrapper()->addStyle('width:160px;')
                );
        }
    }

    /**
     * 保存数据 范例
     *
     * @param integer $id
     * @return mixed
     */
    private function save($id = 0)
    {
        $data = request()->only([
            'TABLE_NAME',
            'TABLE_COMMENT',
            'fields',
        ], 'post');

        if ($this->prefix && strpos($data['TABLE_NAME'], $this->prefix)) {
            $data['TABLE_NAME'] = $this->prefix . $data['TABLE_NAME'];
        }

        $result = $this->validate($data, [
            'TABLE_NAME|表名' => 'require|regex:[a-zA-Z_][a-zA-Z_\d]*',
            'TABLE_COMMENT|表注释' => 'require',
        ]);

        if (true !== $result) {
            $this->error($result);
        }

        if ($id) {
            $res = $this->dbLogic->updateTable($id, $data);
        } else {

            $res = $this->dbLogic->createTable($data['TABLE_NAME'], $data);
        }

        if (!$res) {
            $this->error('保存失败' . $this->dbLogic->getErrorsText());
        }

        if ($id) {
            return $this->builder()->layer()->closeRefresh(1, '保存成功');
        }

        $this->success('新建表成功', url('fieldlist', ['name' => $data['TABLE_NAME']]), ['script' => '<script>parent.$(".search-refresh").trigger("click");</script>'], 1);
    }

    /**
     * 构建表格
     *
     * @return void
     */
    protected function buildTable(&$data = [])
    {
        $table = $this->table;

        $table->text('TABLE_NAME', '表名')->autoPost('', true)->getWrapper()->addStyle('width:260px');
        $table->text('TABLE_COMMENT', '表注释')->autoPost('', true)->getWrapper()->addStyle('width:260px');
        $table->raw('TABLE_ROWS', '记录条数');
        $table->show('AUTO_INCREMENT', '自增id');
        $table->show('DATA_SIZE', '数据大小')->to('{val}MB');
        $table->show('TABLE_COLLATION', '排序规则');
        $table->show('ENGINE', '存储引擎');
        $table->show('CREATE_TIME', '创建时间')->getWrapper()->addStyle('width:160px');

        foreach ($data as &$d) {
            $d['DATA_SIZE'] = $this->dbLogic->getDataSize($d);
            $d['TABLE_ROWS'] = '<a target="_blank" title="查看数据" href="' . url('datalist', ['name' => $d['TABLE_NAME']]) . '">' . $d['TABLE_ROWS'] . '</a>';
        }

        unset($d);

        $table->getToolbar()
            ->btnAdd('', '', 'btn-primary', 'mdi-plus', 'data-layer-size="1200px,98%"')
            ->btnRefresh()
            ->btnToggleSearch()
            ->btnLink(url('trash'), '回收站', 'btn-danger', 'mdi-delete-variant');

        $table->getActionbar()
            ->btnEdit()
            ->btnLink('fields', url('fieldlist', ['name' => '__data.pk__']), '', 'btn-success', 'mdi-format-list-bulleted-type', 'title="字段管理" data-layer-size="98%,98%"')
            ->btnLink('relations', url('/admin/creator/relations', ['id' => '__data.pk__']), '', 'btn-info', 'mdi-link-variant', 'title="设置表关联" data-layer-size="1200px,98%"')
            ->btnLink('lang', url('/admin/creator/lang', ['id' => '__data.pk__']), '', 'btn-danger', 'mdi-translate', 'title="生成翻译文件"')
            ->btnDelete();

        $table->useCheckbox(false);
        $table->sortable('TABLE_NAME,TABLE_ROWS,CREATE_TIME,TABLE_COLLATION,AUTO_INCREMENT,AVG_ROW_LENGTH,INDEX_LENGTH');
    }

    /**
     * Undocumented function
     * @title 回收站
     *
     * @return mixed
     */
    public function trash()
    {
        $builder = $this->builder('回收站', '');

        $table = $builder->table();

        $table->match('type', '类型')->options(['table' => '表', 'field' => '字段'])->mapClassGroup([['table', 'success'], ['field', 'info']]);
        $table->raw('name', '名称');
        $table->show('comment', '注释');
        $table->show('delete_time', '删除时间')->getWrapper()->addStyle('width:180px');
        $table->raw('table_data', '数据')->getWrapper()->addStyle('width:180px');

        $data = [];

        $deletedTables = $this->dbLogic->getDeletedTables();

        foreach ($deletedTables as $dtable) {
            $arr = explode('_del_at_', $dtable['TABLE_NAME']);
            $data[] = [
                'id' => $dtable['TABLE_NAME'],
                'name' => $dtable['TABLE_NAME'],
                'comment' => $dtable['TABLE_COMMENT'],
                'type' => 'table',
                'delete_time' => date('Y-m-d H:i:s', $arr[1]),
                'table_data' => '<a target="_blank" title="查看数据" href="' . url('datalist', ['name' => $dtable['TABLE_NAME']]) . '">查看</a>',
            ];
        }

        unset($dtable);

        $deletedTables = $this->dbLogic->getTables();

        foreach ($deletedTables as $dtable) {
            $deletedFields = $this->dbLogic->getDeletedFields($dtable['TABLE_NAME']);

            foreach ($deletedFields as $field) {
                $arr = explode('_del_at_', $field['COLUMN_NAME']);
                $data[] = [
                    'id' => $dtable['TABLE_NAME'] . '.' . $field['COLUMN_NAME'],
                    'name' => $dtable['TABLE_NAME'] . '<i style="color:green;">@</i>' . $field['COLUMN_NAME'],
                    'comment' => $field['COLUMN_COMMENT'],
                    'type' => 'field',
                    'delete_time' => date('Y-m-d H:i:s', $arr[1]),
                    'table_data' => '<a target="_blank" title="查看数据" href="' . url('datalist', ['name' => $dtable['TABLE_NAME'], 'show_field' => $field['COLUMN_NAME']]) . '">查看</a>',
                ];
            }
        }

        $table->fill($data);

        $table->getActionbar()
            ->btnDelete(url('destroy'), '删除', 'btn-danger', 'mdi-delete', 'title="彻底删除表或字段"', '删除后数据不可恢复，确定要执行此操作吗？')
            ->btnPostRowid('recovery', url('recovery'), '恢复', 'btn-success', 'mdi-backup-restore', 'title="恢复表或字段"');

        $table->useCheckbox(false);
        $table->useToolbar(false);

        if (request()->isAjax()) {
            return $table->partial()->render();
        }

        return $builder->render();
    }

    /**
     * Undocumented function
     * @title 恢复已删除的表或字段
     *
     * @return mixed
     */
    public function recovery()
    {
        $ids = input('post.ids', '');
        $ids = array_filter(explode(',', $ids), 'strlen');

        if (empty($ids)) {
            $this->error('参数有误');
        }

        $res = 0;
        foreach ($ids as $id) {
            if (strpos($id, '.') !== false) {
                $arr = explode('.', $id);
                if ($this->dbLogic->recoveryField($arr[0], $arr[1])) {
                    $res += 1;
                }
            } else {
                if ($this->dbLogic->recoveryTable($id)) {
                    $res += 1;
                }
            }
        }

        if ($res) {
            $this->success('成功恢复' . $res . '条数据', '', ['script' => '<script>parent.$(".search-refresh").trigger("click");</script>']);
        } else {
            $this->error('恢复失败' . $this->dbLogic->getErrorsText());
        }
    }

    /**
     * Undocumented function
     * @title 彻底删除表或字段
     * @return mixed
     */
    public function destroy()
    {
        $ids = input('post.ids', '');
        $ids = array_filter(explode(',', $ids), 'strlen');

        if (empty($ids)) {
            $this->error('参数有误');
        }

        $res = 0;
        foreach ($ids as $id) {
            if (strpos($id, '.') !== false) {
                $arr = explode('.', $id);
                if ($this->dbLogic->dropField($arr[0], $arr[1])) {
                    $res += 1;
                }
            } else {
                if ($this->dbLogic->dropTable($id)) {
                    $res += 1;
                }
            }
        }

        if ($res) {
            $this->success('成功删除' . $res . '条数据');
        } else {
            $this->error('删除失败' . $this->dbLogic->getErrorsText());
        }
    }

    public function autopost()
    {
        $id = input('post.id', '');
        $name = input('post.name', '');
        $value = input('post.value', '');

        if (empty($id) || empty($name)) {
            $this->error('参数有误');
        }

        $res = 0;

        if ($name == 'TABLE_COMMENT') {
            $res = $this->dbLogic->changeComment($id, $value);
        } else if ($name == 'TABLE_NAME') {
            $res = $this->dbLogic->changeTableName($id, $value);
        }

        if ($res) {
            $this->success('修改成功');
        } else {
            $this->error('修改失败，或无更改' . $this->dbLogic->getErrorsText());
        }
    }

    public function delete()
    {
        $ids = input('post.ids', '');
        $ids = array_filter(explode(',', $ids), 'strlen');

        if (empty($ids)) {
            $this->error('参数有误');
        }

        $res = 0;
        foreach ($ids as $id) {
            if ($this->dbLogic->trashTable($id)) {
                $res += 1;
            }
        }

        if ($res) {
            $this->success('成功删除' . $res . '条数据');
        } else {
            $this->error('删除失败' . $this->dbLogic->getErrorsText());
        }
    }

    /**
     * Undocumented function
     *
     * @title 字段管理
     * @return mixed
     */
    public function fieldlist($name)
    {
        if (request()->isPost()) {
            return $this->savefields($name);
        }

        $builder = $this->builder('字段管理', $name);

        $form = $builder->form();

        $fields = $this->dbLogic->getFields($name, 'COLUMN_NAME,COLUMN_TYPE,COLUMN_DEFAULT,COLUMN_COMMENT,IS_NULLABLE,NUMERIC_SCALE,NUMERIC_PRECISION,CHARACTER_MAXIMUM_LENGTH,DATA_TYPE');

        $keys = [];

        $moveTo = [];

        foreach ($fields as &$field) {
            if ($this->dbLogic->isInteger($field['DATA_TYPE']) || $this->dbLogic->isDecimal($field['DATA_TYPE']) || $this->dbLogic->isChartext($field['DATA_TYPE'])) {
                $field['LENGTH'] = preg_replace('/^\w+\((\d+).+?$/', '$1', $field['COLUMN_TYPE']);
            }

            if (strtolower($field['COLUMN_NAME']) == 'id') {
                $field['__can_delete__'] = 0;
            }

            $keys = $this->dbLogic->getKeys($name, $field['COLUMN_NAME']);

            $field['ATTR'] = '';

            $ATTR = [];

            if (strpos($field['COLUMN_TYPE'], 'unsigned')) {
                $ATTR['unsigned'] = 'unsigned';
            }

            foreach ($keys as $key) {
                if (strtoupper($key['INDEX_NAME']) == 'PRIMARY') {
                    $field['__can_delete__'] = 0;
                    $ATTR['index'] = 'index';
                    continue;
                }

                if ($key['NON_UNIQUE'] == 1) {
                    $ATTR['index'] = 'index';
                } else {
                    $ATTR['unique'] = 'unique';
                }
            }

            $field['ATTR'] = implode(',', $ATTR);
            $field['DATA_TYPE'] = strtolower($field['DATA_TYPE']);
            $field['IS_NULLABLE'] = $field['IS_NULLABLE'] == 'YES';

            if (is_null($field['COLUMN_DEFAULT'])) {
                $field['COLUMN_DEFAULT'] = 'NULL';
            } else {
                $field['COLUMN_DEFAULT'] = trim($field['COLUMN_DEFAULT'], "'");
            }

            $moveTo[$field['COLUMN_NAME']] = $field['COLUMN_NAME'] . '后';
        }

        unset($keys, $field);

        $form->items('fields', ' ')->dataWithId($fields, 'COLUMN_NAME')->size(0, 12)
            ->with(
                $form->text('COLUMN_NAME', '字段名')->required(),
                $form->text('COLUMN_COMMENT', '字段注释')->required(),
                $form->select('DATA_TYPE', '数据类型')->options($this->dbLogic::$FIELD_TYPES)->required()->default('varchar')->getWrapper()->addStyle('width:120px;'),
                $form->text('LENGTH', '长度')->default(0)->getWrapper()->addStyle('width:80px;'),
                $form->text('NUMERIC_SCALE', '小数点')->default(0)->getWrapper()->addStyle('width:60px;'),
                $form->text('COLUMN_DEFAULT', '默认值')->default(''),
                $form->switchBtn('IS_NULLABLE', '可空')->getWrapper()->addStyle('width:70px;'),
                $form->checkbox('ATTR', '属性')->options(['index' => '索引', 'unique' => '唯一', 'unsigned' => '非负'])->getWrapper()->addStyle('width:200px;'),
                $form->select('MOVE_AFTER', '移动到')->placeholder('移动字段')->rendering(function ($field) use ($moveTo) {
                    $options = $moveTo;
                    unset($options[$field->data['COLUMN_NAME']]); //自身字段名从选项中移除
                    $field->options($options);
                })->getWrapper()->addStyle('width:180px;')
            );

        return $builder->render();
    }

    private function savefields($name)
    {
        $postfields = input('post.fields/a');

        $errors = [];

        foreach ($postfields as $key => &$pfield) {
            $result = $this->validate($pfield, [
                'COLUMN_NAME|字段名' => 'require|regex:[a-zA-Z_][a-zA-Z_\d]*',
                'COLUMN_COMMENT|字段注释' => 'require',
                'DATA_TYPE|字段注释' => 'require',
            ]);

            if (true !== $result) {
                $errors[] = '[' . $pfield['COLUMN_NAME'] . ']' . $result;
                continue;
            }

            //switch 关闭状态，无键值
            if (!isset($pfield['IS_NULLABLE'])) {
                $pfield['IS_NULLABLE'] = '0';
            }

            //checkbox 未选中任何一个时，无键值
            $pfield['ATTR'] = isset($pfield['ATTR']) ? $pfield['ATTR'] : [];

            if (strpos($key, '__new__') !== false) {

                $this->dbLogic->addField($name, $pfield);
            } else {

                if (isset($pfield['__del__']) && $pfield['__del__'] == 1) {
                    $pfield['COLUMN_NAME'] .= '_del_at_' . time();
                    $pfield['IS_NULLABLE'] = '1';
                }

                $this->dbLogic->changeField($name, $key, $pfield);
            }
        }

        $errors = array_merge($errors, $this->dbLogic->getErrors());

        if (!empty($errors)) {
            $this->error('保存失败-' . implode('<br>', $errors));
        }

        $this->success('保存成功，页面即将刷新~', null, ['script' => '<script>parent.$(".search-refresh").trigger("click");</script>'], 1);
    }

    /**
     * Undocumented function
     *
     * @title 查看数据
     * @return mixed
     */
    public function datalist($name)
    {
        $tableInfo = $this->dbLogic->getTableInfo($name);

        $builder = $this->builder('查看数据', $name . '[' . $tableInfo['TABLE_COMMENT'] . ']');

        $table = $builder->table();

        $show_field = input('show_field', '');

        $page = input('__page__/d', 1);
        $page = $page < 1 ? 1 : $page;

        $pk = Db::table($name)->getPk();

        $sortOrder = input('__sort__', !empty($pk) && is_string($pk) ? $pk . ' desc' : '');

        $pagesize = input('__pagesize__/d', 0);

        $pagesize = $pagesize ?: 16;

        $data = Db::table($name)->order($sortOrder)->limit(($page - 1) * $pagesize, $pagesize)->select();

        $fields = $this->dbLogic->getFields($name, 'COLUMN_NAME,COLUMN_COMMENT');

        $deletedFields = $this->dbLogic->getDeletedFields($name);

        $fieldNames = [];

        foreach ($fields as $field) {
            $fieldNames[] = $field['COLUMN_NAME'];

            $table->show($field['COLUMN_NAME'], $field['COLUMN_NAME'] . '<br>' . $field['COLUMN_COMMENT'])->cut(100)->getWrapper()->addStyle('max-width:400px;max-height:100px;');
        }

        unset($field);

        foreach ($deletedFields as $field) {
            $table->show($field['COLUMN_NAME'], ($field['COLUMN_NAME'] == $show_field ? '<i style="color:red;">=></i>' : '') . $field['COLUMN_NAME'] . '<label class="label label-danger">[已删除]</label>' . '<br>' . $field['COLUMN_COMMENT'])
                ->cut(100)->getWrapper()->addStyle('max-width:400px;max-height:100px;');
        }

        $table->fill($data);

        $table->paginator(Db::table($name)->count(), $pagesize);

        $table->sortOrder($sortOrder);

        if (!empty($pk) && is_string($pk)) {
            $table->getActionbar()
                ->btnView(url('dataview', ['name' => $name, 'id' => "__data.{$pk}__", 'pk' => $pk]));
        } else {
            $table->useActionbar(false);
        }

        $table->getToolbar()
            ->btnRefresh()
            ->useExport(false);

        $table->sortable($fieldNames);

        $this->builder()->addStyleSheet('
            .field-show
            {
                max-width:100%;max-height:100%;overflow:auto;margin:auto auto;
            }
        ');

        if (request()->isAjax()) {
            return $table->partial()->render();
        }

        return $builder->render();
    }

    /**
     * Undocumented function
     *
     * @title 查看数据-详情
     * @return mixed
     */
    public function dataview($name, $id, $pk)
    {
        $tableInfo = $this->dbLogic->getTableInfo($name);

        $builder = $this->builder('查看数据', $name . '[' . $tableInfo['TABLE_COMMENT'] . ']');

        $form = $builder->form();

        $data = Db::table($name)->where($pk, $id)->find();

        $fields = $this->dbLogic->getFields($name, 'COLUMN_NAME,COLUMN_COMMENT');

        foreach ($fields as $field) {

            $form->show($field['COLUMN_NAME'], $field['COLUMN_NAME'] . '(' . $field['COLUMN_COMMENT'] . ')')->fullSize(3);
        }

        $form->fill($data);

        $form->readonly();

        return $builder->render();
    }
}
