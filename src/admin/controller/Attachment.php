<?php
namespace tpext\manager\admin\controller;

use think\Controller;
use tpext\builder\common\model\Attachment as AttachmentModel;
use tpext\builder\traits\actions\HasAutopost;
use tpext\builder\traits\actions\HasBase;
use tpext\builder\traits\actions\HasIndex;

class Attachment extends Controller
{
    use HasBase;
    use HasIndex;
    use HasAutopost;

    protected $dataModel;

    protected function initialize()
    {
        $this->dataModel = new AttachmentModel;

        $this->pageTitle = '文件管理';
        $this->postAllowFields = ['name'];
        $this->pagesize = 6;
    }

    protected function filterWhere()
    {
        $searchData = request()->post();

        $where = [];

        if (!empty($searchData['name'])) {
            $where[] = ['name', 'like', '%' . $searchData['name'] . '%'];
        }

        if (!empty($searchData['url'])) {
            $where[] = ['url', 'like', '%' . $searchData['url'] . '%'];
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

        $search->text('name', '文件名', 4)->maxlength(55);
        $search->text('url', 'url链接', 4)->maxlength(200);
    }
    /**
     * 构建表格
     *
     * @return void
     */
    protected function buildTable(&$data = [])
    {
        $table = $this->table;

        $table->show('id', 'ID');
        $table->text('name', '文件名')->autoPost();
        $table->show('mime', 'mime类型');
        $table->show('size', '大小')->to('{val}MB');
        $table->raw('url', '链接')->to('<a href="{val}" target="_blank">{val}</a>');
        $table->file('file', '文件');
        $table->show('suffix', '后缀')->getWrapper()->addStyle('width:80px');
        $table->show('storage', '位置');

        $table->show('create_time', '添加时间')->getWrapper()->addStyle('width:160px');

        $table->getToolbar()
            ->btnRefresh()
            ->btnImport(url('uploadSuccess'), '', ['250px', '205px'], 0, '上传');

        foreach ($data as &$d) {
            $d['file'] = $d['url'];
        }

        $table->useActionbar(false);
    }

    public function uploadSuccess()
    {
        $builder = $this->builder('上传成功');

        $builder->addScript('parent.lightyear.notify("上传成功","success");parent.$(".search-refresh").trigger("click");parent.layer.close(parent.layer.getFrameIndex(window.name));'); //刷新列表页

        $fileurl = input('fileurl');

        $builder->content()->display($fileurl);

        return $builder->render();
    }
}
