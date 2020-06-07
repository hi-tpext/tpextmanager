<?php

namespace tpext\manager\admin\controller;

use think\Controller;
use tpext\builder\traits\actions\HasBase;
use tpext\builder\traits\actions\HasIndex;
use tpext\manager\logic\DbLogic;

use think\Db;

class Table extends Controller
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

        $form->text('TABLE_COMMENT', '表注释')->required()->maxlength(50);
        $form->text('TABLE_NAME', '表名')->required()->maxlength(50)->help($isEdit ? '请不要随意修改' : '');

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
            'TABLE_NAME|表名' => 'require',
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
            $this->error('保存失败');
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

        $table->text('TABLE_COMMENT', '表注释')->autoPost('', true)->getWapper()->addStyle('width:260px');
        $table->text('TABLE_NAME', '表名')->autoPost('', true)->getWapper()->addStyle('width:260px');
        $table->show('TABLE_ROWS', '记录条数');
        $table->show('AUTO_INCREMENT', '自增id');
        $table->show('DATA_SIZE', '数据大小')->to('{val}MB');
        $table->show('TABLE_COLLATION', '排序规则');
        $table->show('ENGINE', '存储引擎');
        $table->show('CREATE_TIME', '创建时间')->getWapper()->addStyle('width:160px');

        foreach ($data as &$d) {
            $d['DATA_SIZE'] = $this->dbLogic->getDataSize($d);
        }
        unset($d);

        $table->getToolbar()
            ->btnAdd()
            ->btnRefresh();

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
            $res =   $this->dbLogic->changeComment($id, $value);
        } else if ($name == 'TABLE_NAME') {
            $res =   $this->dbLogic->changeTableName($id, $value);
        }

        if ($res) {
            $this->success('修改成功');
        } else {
            $this->error('修改失败，或无更改');
        }
    }
}
