<?php

namespace tpext\manager\admin\controller;

use think\Controller;
use tpext\builder\traits\actions\HasBase;
use tpext\builder\traits\actions\HasIndex;
use tpext\manager\logic\DbLogic;

use think\Db;

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

    protected function initialize()
    {
        $this->pageTitle = '数据表管理';
        $this->pagesize = 10;
        $this->pk = 'TABLE_NAME';

        $this->database =  config('database.database');

        $this->dbLogic = new DbLogic;
    }

    protected function filterWhere()
    {
        $searchData = request()->post();

        $where =  '';

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
    protected function builSearch()
    {
        $search = $this->search;

        $search->text('kwd', '表名/表注释', 4)->maxlength(55);
    }

    protected function buildDataList()
    {
        $sortOrder = input('__sort__', 'TABLE_NAME ASC');
        $where = $this->filterWhere();
        $table = $this->table;

        $data = $this->dbLogic->getTables('TABLE_NAME,TABLE_ROWS,CREATE_TIME,TABLE_COLLATION,TABLE_COMMENT,ENGINE,AUTO_INCREMENT,AVG_ROW_LENGTH,INDEX_LENGTH', $where, $sortOrder);

        $this->buildTable($data);
        $table->fill($data);
        $table->sortOrder($sortOrder);

        return $data;
    }

    public function add()
    {
        if (request()->isPost()) {
            return $this->save();
        } else {
            $builder = $this->builder($this->pageTitle, $this->addText);
            $form = $builder->form();
            $data = [];
            $this->form = $form;
            $this->builForm(false, $data);
            $form->fill($data);
            return $builder->render();
        }
    }

    public function edit($id)
    {
        if (request()->isPost()) {
            return $this->save($id);
        } else {
            $builder = $this->builder($this->pageTitle, $this->editText);
            $data = $this->dbLogic->getTable($id);
            if (!$data) {
                return $builder->layer()->close(0, '数据不存在');
            }
            $form = $builder->form();
            $this->form = $form;
            $this->builForm(true, $data);
            $form->fill($data);

            return $builder->render();
        }
    }

    /**
     * 构建表单
     *
     * @param boolean $isEdit
     * @param array $data
     */
    protected function builForm($isEdit, &$data = [])
    {
        $form = $this->form;

        $form->text('TABLE_NAME', '表名')->required()->maxlength(50)->help($isEdit ? ' 若非必要，请不要随意修改' : '英文字母或数字组成');
        $form->text('TABLE_COMMENT', '表注释')->required()->maxlength(50)->help('表的描述说明');

        if ($isEdit) {

            $data['DATA_SIZE'] = $this->dbLogic->getDataSize($data);;

            $form->show('TABLE_ROWS', '记录条数');
            $form->show('AUTO_INCREMENT', '自增id');
            $form->show('DATA_SIZE', '数据大小')->to('{val}MB');
            $form->show('TABLE_COLLATION', '排序规则');
            $form->show('ENGINE', '存储引擎');
            $form->show('CREATE_TIME', '创建时间');
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
        ], 'post');

        $result = $this->validate($data, [
            'TABLE_NAME|表名' => 'require|regex:\w+',
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

        $table->text('TABLE_NAME', '表名')->autoPost('', true)->getWapper()->addStyle('width:260px');
        $table->text('TABLE_COMMENT', '表注释')->autoPost('', true)->getWapper()->addStyle('width:260px');
        $table->raw('TABLE_ROWS', '记录条数');
        $table->show('AUTO_INCREMENT', '自增id');
        $table->show('DATA_SIZE', '数据大小')->to('{val}MB');
        $table->show('TABLE_COLLATION', '排序规则');
        $table->show('ENGINE', '存储引擎');
        $table->show('CREATE_TIME', '创建时间')->getWapper()->addStyle('width:160px');

        foreach ($data as &$d) {
            $d['DATA_SIZE'] = $this->dbLogic->getDataSize($d);
            $d['TABLE_ROWS'] = '<a target="_blank" title="查看数据" href="' . url('datalist', ['name' => $d['TABLE_NAME']]) . '">' . $d['TABLE_ROWS'] . '</a>';
        }

        unset($d);

        $table->getToolbar()
            ->btnAdd()
            ->btnRefresh();
        $table->getActionbar()
            ->btnEdit()
            ->btnLink('fields', url('fieldlist', ['name' => '__data.pk__']), '', 'btn-success', 'mdi-format-list-bulleted-type', 'title="字段管理" data-layer-size="98%,98%"')
            ->btnDelete();

        $table->useCheckbox(false);
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
            $res =  $this->dbLogic->changeComment($id, $value);
        } else if ($name == 'TABLE_NAME') {
            $res =  $this->dbLogic->changeTableName($id, $value);
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

    public function fieldlist($name)
    {
        $builder = $this->builder('字段管理', $name);

        $form = $builder->form();

        

        $fields = $this->dbLogic->getTFields($name, 'COLUMN_NAME,COLUMN_DEFAULT,COLUMN_COMMENT,IS_NULLABLE,NUMERIC_SCALE,NUMERIC_PRECISION,CHARACTER_MAXIMUM_LENGTH,DATA_TYPE');

        foreach ($fields as &$field) {
            $field['LENGTH'] = $field['CHARACTER_MAXIMUM_LENGTH'] ? $field['CHARACTER_MAXIMUM_LENGTH'] : $field['NUMERIC_PRECISION'];
        }

        echo json_encode($fields);

        unset($field);

        $form->items('fields', ' ')->dataWithId($fields, 'COLUMN_NAME')->size(0, 12)
            ->with(
                $form->text('COLUMN_NAME', '名称'),
                $form->text('COLUMN_COMMENT', '注释'),
                $form->text('DATA_TYPE', '数据类型'),
                $form->text('LENGTH', '长度')->getWapper()->addStyle('width:100px;'),
                $form->text('NUMERIC_SCALE', '小数点')->getWapper()->addStyle('width:100px;'),
                $form->text('COLUMN_DEFAULT', '默认值'),
                $form->select('IS_NULLABLE', '可空')->options(['NO' => '否', 'YES' => '是'])->getWapper()->addStyle('width:100px;'),
            );

        return $builder->render();
    }


    public function datalist($name)
    {
        $builder = $this->builder('查看数据', $name);

        $table = $builder->table();

        $page = input('__page__/d', 1);
        $page = $page < 1 ? 1 : $page;
        $sortOrder = input('__sort__', Db::table($name)->getPk() . ' desc');

        $pagesize = input('__pagesize__/d', 0);

        $pagesize = $pagesize ?: 16;

        $data = Db::table($name)->order($sortOrder)->limit(($page - 1) * $pagesize, $pagesize)->select();

        $table->fill($data);

        $table->paginator(Db::table($name)->count(), $pagesize);

        $table->sortOrder($sortOrder);

        $table->useActionbar(false);

        $table->useCheckbox(false);

        $table->useToolbar(false);

        if (request()->isAjax()) {
            return $table->partial()->render();
        }

        return $builder->render();
    }
}
