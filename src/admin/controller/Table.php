<?php

namespace tpext\manager\admin\controller;

use think\Controller;
use tpext\builder\traits\actions\HasBase;
use tpext\builder\traits\actions\HasIndex;

use think\Db;

class Table extends Controller
{
    use HasBase;
    use HasIndex;

    protected $dataModel;
    protected $database = '';

    protected function initialize()
    {
        $this->pageTitle = '数据表管理';
        $this->pagesize = 10;
        $this->pk = 'TABLE_NAME';

        $this->database =  config('database.database');
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

        $data = Db::query('select TABLE_NAME,TABLE_ROWS,CREATE_TIME,TABLE_COLLATION,TABLE_COMMENT,ENGINE,AUTO_INCREMENT,AVG_ROW_LENGTH,INDEX_LENGTH'
            . " from information_schema.tables where `TABLE_SCHEMA`='{$this->database}' and `TABLE_TYPE`='BASE TABLE' {$where} ORDER BY {$sortOrder}");

        $this->buildTable($data);
        $table->fill($data);
        $table->sortOrder($sortOrder);

        return $data;
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
        $table->show('TABLE_ROWS', '记录条数');
        $table->show('TABLE_COLLATION', '字符集');
        $table->text('TABLE_COMMENT', '表注释')->autoPost()->getWapper()->addStyle('width:260px');
        $table->show('ENGINE', '存储引擎');
        $table->show('AUTO_INCREMENT', '自增id');
        $table->show('DATA_SIZE', '数据大小')->to('__val__MB');

        foreach ($data as &$d) {
            $d['DATA_SIZE'] = round(($d['AVG_ROW_LENGTH'] * $d['TABLE_ROWS'] + $d['INDEX_LENGTH']) / 1024 / 1024, 2);
        }

        $table->show('CREATE_TIME', '创建时间')->getWapper()->addStyle('width:160px');

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

        if ($name = 'TABLE_COMMENT') {
            Db::execute("ALTER TABLE `{$id}` COMMENT '{$value}'");
            $res = 1;
        }

        if ($res) {
            $this->success('修改成功');
        } else {
            $this->error('修改失败');
        }
    }
}
