<?php

namespace tpext\manager\admin\controller;

use think\Controller;
use tpext\builder\common\Builder;
use tpext\builder\common\Module as builderRes;
use tpext\builder\traits\actions\HasBase;
use tpext\builder\traits\actions\HasIndex;
use tpext\common\ExtLoader;
use tpext\common\model\Extension as ExtensionModel;
use tpext\common\Module as BaseModule;
use tpext\common\TpextCore;
use tpext\lightyearadmin\common\Resource as LightyearRes;
use tpext\builder\mdeditor\common\Resource as MdeditorRes;
use tpext\manager\common\Module;

/**
 * Undocumented class
 * @title 扩展管理
 */
class Extension extends Controller
{
    use HasBase;
    use HasIndex;

    protected $extensions = [];

    protected $remoteUrl = 'https://gitee.com/tpext/myadmin/raw/5.0/extensions.json';

    protected $remote = 0;

    /**
     * Undocumented variable
     *
     * @var ExtensionModel
     */
    protected $dataModel;

    protected function initialize()
    {
        $this->pageTitle = '扩展管理';

        ExtLoader::clearCache();

        $this->extensions = ExtLoader::getExtensions();

        $this->extensions[TpextCore::class] = TpextCore::getInstance();

        ksort($this->extensions);

        $this->dataModel = new ExtensionModel;
    }

    /**
     * 构建搜索
     *
     * @return void
     */
    protected function buildSearch()
    {
        $search = $this->search;
        $search->tabLink('remote')->options([0 => '本地', 1 => '远程']);
    }

    /**
     * @title 首次预安装
     * @return mixed
     */
    public function prepare()
    {
        $step = input('step', '0');
        if ($step == 0) {
            Module::getInstance()->install();
            $next = url('/admin/extension/prepare', ['step' => 1]);
            return "<h4>(1/4)</h4><p>安装[tpext.manager]，完成！</p><script>setTimeout(function(){location.href='{$next}'},1000);</script>";
        } else if ($step == 1) {
            LightyearRes::getInstance()->install();
            $next = url('/admin/extension/prepare', ['step' => 2]);
            return "<h4>(2/4)</h4><p>安装[lightyear.admin]，完成！</p><script>setTimeout(function(){location.href='{$next}'},1000);</script>";
        } else if ($step == 2) {
            builderRes::getInstance()->install();
            $next = url('/admin/extension/prepare', ['step' => 3]);
            return "<h4>(3/4)</h4><p>安装[tpext.builder]，完成！</p><script>setTimeout(function(){location.href='{$next}'},1000);</script>";
        } else if ($step == 3) {
            MdeditorRes::getInstance()->install();
            $next = url('/admin/extension/prepare', ['step' => 4]);
            return "<h4>(4/4)</h4><p>安装[builder.mdeditor]，完成！</p><script>setTimeout(function(){location.href='{$next}'},1000);</script>";
        } else {
            $next = url('/admin/extension/index');
            return "<h4>(预安装完成)</h4><p>即将跳转[扩展管理]页面，继续安装其他扩展！</p><script>setTimeout(function(){location.href='{$next}'},1500);</script>";
        }
    }

    protected function buildDataList()
    {
        $page = input('__page__/d', 1);
        $page = $page < 1 ? 1 : $page;

        if ($this->isExporting) {
            $page = 1;
            $this->pagesize = PHP_INT_MAX;
        }

        $table = $this->table;

        $pagesize = input('__pagesize__/d', 0);

        $this->pagesize = $pagesize ?: $this->pagesize;

        $data = [];

        $this->remote = input('remote');

        if ($this->remote) {

            $data = file_get_contents($this->remoteUrl);

            $data = $data ? json_decode($data, 1) : [];

            $data = array_slice($data, ($page - 1) * $this->pagesize, $this->pagesize);

            foreach ($data as &$d) {

                $d['ext_type'] = $d['type'] == 'module' ? 1 : 2;

                $d['download'] = 0;

                foreach ($this->extensions as $key => $instance) {

                    if (!class_exists($key)) {
                        continue;
                    }

                    if ($instance->getName() == $d['name']) {
                        $d['download'] = 1;
                        break;
                    }
                }
            }

            $table->paginator(count($data), $this->pagesize);
        } else {

            $extensions = array_slice($this->extensions, ($page - 1) * $this->pagesize, $this->pagesize);

            $installed = ExtLoader::getInstalled(true);

            if (empty($installed)) {
                $this->builder()->notify('已安装扩展为空！请确保数据库连接正常，然后安装[tpext.manager]', 'warning', 2000);
            } else {
                if (!ExtensionModel::where('key', Module::class)->find()) {

                    return $this->redirect(url('/admin/extension/prepare', ['step' => 0]));
                }
            }


            $is_install = 0;
            $is_enable = 0;
            $is_latest = 0;
            $now_version = '';
            $k = 0;
            foreach ($extensions as $key => $instance) {
                if (!class_exists($key)) {
                    continue;
                }

                $is_install = 0;
                $is_enable = 0;
                $has_config = !empty($instance->defaultConfig());

                foreach ($installed as $ins) {
                    if ($ins['key'] == $key) {
                        $is_install = $ins['install'];
                        $is_enable = $ins['enable'];
                        $is_latest = $ins['version'] == $instance->getVersion();
                        $now_version = $ins['version'];
                        break;
                    }
                }

                $instance->copyAssets();

                $data[$k] = [
                    'id' => str_replace('\\', '-', $key),
                    'key' => $key,
                    'install' => $is_install,
                    'enable' => $is_enable,
                    'name' => $instance->getName(),
                    'title' => $instance->getTitle(),
                    'description' => $instance->getDescription(),
                    'version' => $now_version,
                    'ext_type' => $instance instanceof BaseModule ? 1 : 2,
                    'tags' => $instance->getTags(),
                    '__h_up__' => !$is_install || $is_latest,
                    '__h_in__' => $is_install,
                    '__h_un__' => !$is_install,
                    '__h_st__' => !$is_install || !$has_config,
                    '__h_cp__' => empty($instance->getAssets()),
                ];

                if ($key == Module::class) {
                    $data[$k]['__h_un__'] = 1;
                }

                $k += 1;
            }

            $table->paginator(count($this->extensions), $this->pagesize);
        }

        $this->buildTable($data);
        $table->fill($data);

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

        $table->show('title', '标题');
        $table->show('name', '标识');
        $table->match('ext_type', '扩展类型')->options(
            [
                1 => '<label class="label label-info">模块</label>',
                2 => '<label class="label label-success">资源</label>',
            ]
        );
        $table->show('tags', '分类');
        $table->show('description', '介绍')->getWrapper()->addStyle('width:40%;');

        if ($this->remote) {
            $table->match('download', '下载')->options([0 => '未下载', 1 => '已下载'])->mapClassGroup([[0, 'default'], [1, 'success']]);
            $table->show('composer', 'Composer');
            $table->show('platform', 'TP版本支持');
            $table->match('is_free', '免费')->options([1 => '是', 0 => '否']);
            $table->getActionbar()
                ->btnLink('view', '__data.website__', '主页', 'btn-primary', 'mdi-web', 'title="主页" target="_blank"')->getCurrent()->useLayer(false);
        } else {
            $table->show('version', '已安装版本号');
            $table->match('install', '安装')->options([0 => '未安装', 1 => '已安装'])->mapClassGroup([[0, 'default'], [1, 'success']]);

            $table->switchBtn('enable', '启用')->autoPost(url('enable'))
                ->mapClass(0, 'hidden', 'install') //未安装，隐藏[启用/禁用]
                ->mapClass([Module::class, TpextCore::class], 'hidden', 'key'); //特殊扩展，隐藏[启用/禁用]

            $table->getToolbar()
                ->btnRefresh();

            $table->getActionbar()
                ->btnLink('upgrade', url('upgrade', ['key' => '__data.id__']), '', 'btn-success', 'mdi-arrow-up-bold-circle', 'title="升级"')
                ->btnLink('install', url('install', ['key' => '__data.id__']), '', 'btn-primary', 'mdi-plus', 'title="安装"')
                ->btnLink('uninstall', url('uninstall', ['key' => '__data.id__']), '', 'btn-danger', 'mdi-delete', 'title="卸载"')
                ->btnLink('setting', url('/admin/config/edit', ['key' => '__data.id__']), '', 'btn-info', 'mdi-settings', 'title="设置" data-layer-size="98%,98%"')
                ->btnPostRowid(
                    'copyAssets',
                    url('copyAssets'),
                    '',
                    'btn-purple',
                    'mdi-redo',
                    'title="刷新资源"',
                    '刷新资源将清空并还原`/assets/`下对应扩展目录中的文件，原则上您不应该修改此目录中的任何文件或上传新文件到其中。若您这么做了，请备份到其他地方，然后再刷新资源。确定要刷新吗？'
                )
                ->mapClass([
                    'upgrade' => ['hidden' => '__h_up__'],
                    'install' => ['hidden' => '__h_in__'],
                    'uninstall' => ['hidden' => '__h_un__'],
                    'setting' => ['hidden' => '__h_st__'],
                    'copyAssets' => ['hidden' => '__h_cp__'],
                ]);
        }

        $table->useCheckbox(false);
    }

    public function install($key = '')
    {
        if (empty($key)) {
            return Builder::getInstance()->layer()->close(0, '参数有误！');
        }

        $id = str_replace('-', '\\', $key);

        if (!isset($this->extensions[$id])) {
            return Builder::getInstance()->layer()->close(0, '扩展不存在！');
        }

        $installed = ExtLoader::getInstalled();

        if (empty($installed) && $id != Module::class) {
            return Builder::getInstance()->layer()->close(0, '已安装扩展为空！请确保数据库连接正常，然后安装[tpext.manager]');
        }

        $instance = $this->extensions[$id];

        $builder = Builder::getInstance('扩展管理', '安装-' . $instance->getTitle());

        if (request()->isPost()) {

            $this->checkToken();

            $res = $instance->install();
            $errors = $instance->getErrors();

            if ($res) {
                if (empty($errors)) {
                    return $builder->layer()->closeRefresh(1, '安装成功');
                } else {
                    return $builder->layer()->closeRefresh(0, '安装成功，但可能有些错误');
                }
            } else {

                $text = [];
                foreach ($errors as $err) {
                    $text[] = $err->getMessage();
                }

                $builder->content()->display('<h5>执行出错：</h5>:' . implode('<br>', $text));

                return $builder->render();
            }
        } else {
            $form = $builder->form();
            $this->detail($form, $instance, 1);
            return $builder->render();
        }
    }

    public function uninstall($key = '')
    {
        if (empty($key)) {
            return Builder::getInstance()->layer()->close(0, '参数有误！');
        }

        $id = str_replace('-', '\\', $key);

        if (!isset($this->extensions[$id])) {
            return Builder::getInstance()->layer()->close(0, '扩展不存在！');
        }

        $instance = $this->extensions[$id];

        $builder = Builder::getInstance('扩展管理', '卸载-' . $instance->getTitle());

        if (request()->isPost()) {

            $this->checkToken();

            $sql = input('post.sql/a', []);
            $res = $instance->uninstall(!empty($sql));
            $errors = $instance->getErrors();

            if ($res) {
                if (empty($errors)) {
                    return $builder->layer()->closeRefresh(1, '卸载成功');
                } else {
                    return $builder->layer()->closeRefresh(0, '卸载成功，但可能有些错误');
                }
            } else {

                $text = [];
                foreach ($errors as $err) {
                    $text[] = $err->getMessage();
                }

                $builder->content()->display('<h5>执行出错：</h5>:' . implode('<br>', $text));

                return $builder->render();
            }
        } else {

            $form = $builder->form();
            $this->detail($form, $instance, 2);
            return $builder->render();
        }
    }

    public function upgrade($key = '')
    {
        if (empty($key)) {
            return Builder::getInstance()->layer()->close(0, '参数有误！');
        }

        $id = str_replace('-', '\\', $key);

        if (!isset($this->extensions[$id])) {
            return Builder::getInstance()->layer()->close(0, '扩展不存在！');
        }

        $instance = $this->extensions[$id];

        $builder = Builder::getInstance('扩展管理', '升级-' . $instance->getTitle());

        if (request()->isPost()) {

            $this->checkToken();

            $res = $instance->upgrade();
            $errors = $instance->getErrors();

            if ($res) {
                if (empty($errors)) {
                    return $builder->layer()->closeRefresh(1, '升级成功');
                } else {
                    return $builder->layer()->closeRefresh(0, '升级成功，但可能有些错误');
                }
            } else {

                $text = [];
                foreach ($errors as $err) {
                    $text[] = $err->getMessage();
                }

                $builder->content()->display('<h5>执行出错：</h5>:' . implode('<br>', $text));

                return $builder->render();
            }
        } else {
            $form = $builder->form();
            $this->detail($form, $instance, 3);
            return $builder->render();
        }
    }

    /**
     * Undocumented function
     *
     * @param \tpext\builder\common\Form $form
     * @param \tpext\common\Module $instance
     * @param integer $type
     * @return void
     */
    private function detail($form, $instance, $type = 0)
    {
        $isModule = $instance instanceof BaseModule;
        $modules = $isModule ? $instance->getModules() : [];
        $menus = $isModule ? $instance->getMenus() : [];

        $bindModules = [];
        foreach ($modules as $module => $controlers) {
            foreach ($controlers as $controler) {
                $bindModules[] = '/' . $module . '/' . $controler . '/*';
            }
        }

        $form->tab('基本信息');
        $form->raw('name', '标识')->value($instance->getName());
        $form->raw('title', '标题')->value($instance->getTitle());
        $form->raw('tags', '类型')->value($instance->getTags());
        $form->raw('desc', '介绍')->value($instance->getDescription());
        $form->raw('version', '版本号')->value($instance->getVersion());
        if ($type == 1) {
            if (is_file($instance->getRoot() . 'data' . DIRECTORY_SEPARATOR . 'install.sql')) {
                $form->show('sql', '安装脚本')->value('安装将运行SQL脚本');
            } else {
                $form->show('sql', '安装脚本')->value('无');
            }
        } else if ($type == 2) {
            if (is_file($instance->getRoot() . 'data' . DIRECTORY_SEPARATOR . 'uninstall.sql')) {

                $app_debug = config('app_debug');

                $form->checkbox('sql', '卸载脚本')->options([1 => '卸载将运行SQL脚本'])->value($app_debug ? 1 : 0)->help($app_debug ? '<label class="label label-default">当前为调试模式</label>' : '<label class="label label-danger">当前为非调试模式，谨慎操作</label>');
            } else {
                $form->show('uninstall', '卸载脚本')->value('无');
            }
        }

        if ($isModule) {
            $form->tab('模块&菜单');
            $form->raw('modules', '提供模块')->value(!empty($bindModules) ? '<pre>' . implode("\n", $bindModules) . '</pre>' : '无');
            $form->raw('menus', '提供菜单')->value(!empty($menus) ? '<pre>' . implode("\n", $this->menusTree($menus)) . '</pre>' : '无');
        }

        $form->tab('README.md');
        $README = '暂无';

        if (is_file($instance->getRoot() . 'README.md')) {
            $README = file_get_contents($instance->getRoot() . 'README.md');
        }
        $form->mdreader('README')->jsOptions(['readOnly' => true, 'width' => 1200])->size(0, 12)->showLabel(false)->value($README);

        $form->tab('CHANGELOG.md', $type == 3);
        $README = '暂无';
        if (is_file($instance->getRoot() . 'CHANGELOG.md')) {
            $README = file_get_contents($instance->getRoot() . 'CHANGELOG.md');
        }
        $form->mdreader('CHANGELOG')->jsOptions(['readOnly' => true, 'width' => 1200])->size(0, 12)->showLabel(false)->value($README);

        $form->tab('LICENSE.txt');
        $LICENSE = '暂无';
        if (is_file($instance->getRoot() . 'LICENSE.txt')) {
            $LICENSE = '<pre>' . htmlspecialchars(file_get_contents($instance->getRoot() . 'LICENSE.txt')) . '</pre>';
        } else if (is_file($instance->getRoot() . 'LICENSE')) {
            $LICENSE = '<pre>' . htmlspecialchars(file_get_contents($instance->getRoot() . 'LICENSE')) . '</pre>';
        }

        $form->raw('LICENSE')->size(0, 12)->showLabel(false)->value($LICENSE);

        $form->ajax(false);

        if ($type == 1) {
            $form->btnSubmit('安&nbsp;&nbsp;装', '6 col-lg-6 col-sm-6 col-xs-6', 'btn-success btn-loading');
            $form->btnLayerClose('返&nbsp;&nbsp;回', '6 col-lg-6 col-sm-6 col-xs-6');

        } else if ($type == 2) {
            $form->btnSubmit('卸&nbsp;&nbsp;载', '6 col-lg-6 col-sm-6 col-xs-6', 'btn-danger btn-loading');
            $form->btnLayerClose('返&nbsp;&nbsp;回', '6 col-lg-6 col-sm-6 col-xs-6');
            
        } else if ($type == 3) {
            $form->btnSubmit('升&nbsp;&nbsp;级', '6 col-lg-6 col-sm-6 col-xs-6', 'btn-warning btn-loading');
            $form->btnLayerClose('返&nbsp;&nbsp;回', '6 col-lg-6 col-sm-6 col-xs-6');
        }
    }

    /**
     * Undocumented function
     *
     * @param array $meuns
     * @return array
     */
    private function menusTree($meuns, $deep = 0)
    {
        $data = [];

        $deep += 1;

        foreach ($meuns as $menu) {

            if ($deep == 1) {
                $data[] = $menu['title'] . ' - ' . $menu['url'];
            } else {
                $data[] = str_repeat(' ', ($deep - 1) * 3) . '├─' . $menu['title'] . ' - ' . $menu['url'];
            }

            if (isset($menu['children']) && !empty($menu['children'])) {
                $data = array_merge($data, $this->menusTree($menu['children'], $deep));
            }
        }

        return $data;
    }

    /**
     * Undocumented function
     *
     * @title 刷新资源
     * @return mixed
     */
    public function copyAssets()
    {
        $ids = input('ids', '');
        $ids = str_replace('-', '\\', $ids);

        $ids = array_filter(explode(',', $ids), 'strlen');

        if (empty($ids)) {
            $this->error('参数有误');
        }

        $instance = $this->extensions[$ids[0]];

        $instance->copyAssets(true);

        $this->success('刷新成功');
    }

    public function enable()
    {
        $id = input('post.id', '');
        $value = input('post.value', '0');

        if (empty($id)) {
            $this->error('参数有误');
        }

        $id = str_replace('-', '\\', $id);

        if (!isset($this->extensions[$id])) {
            $this->error('扩展不存在！');
        }

        $res = $this->dataModel->update(['enable' => $value], ['key' => $id]);

        if ($res) {
            $instance = $this->extensions[$id];
            $instance->enabled($value);

            $this->success(($value == 1 ? '启用' : '禁用') . '成功');
        } else {
            $this->error('修改失败，或无更改');
        }
    }

    protected function checkToken()
    {
        $token = session('_csrf_token_');

        if (empty($token) || $token != input('__token__')) {
            $this->error('token错误');
        }
    }
}
