<?php
namespace tpext\manager\admin\controller;

use think\Controller;
use tpext\builder\common\Builder;
use tpext\common\ExtLoader;
use tpext\common\model\Extension as ExtensionModel;
use tpext\common\Module as BaseModule;
use tpext\common\TpextCore;
use tpext\manager\common\Module;

class Extension extends Controller
{
    protected $extensions = [];

    protected $dataModel;

    protected function initialize()
    {
        ExtLoader::clearCache();

        $this->extensions = ExtLoader::getExtensions();

        $this->extensions[TpextCore::class] = TpextCore::getInstance();

        ksort($this->extensions);

        $this->dataModel = new ExtensionModel;
    }

    public function index()
    {
        $builder = Builder::getInstance('扩展管理', '列表');

        $table = $builder->table();

        $page = input('__page__/d', 1);

        if ($page < 1) {
            $page = 1;
        }

        $pagesize = input('__pagesize__/d', 0);

        $pagesize = $pagesize ?: 14;

        $extensions = array_slice($this->extensions, ($page - 1) * $pagesize, $pagesize);

        $data = [];

        $installed = ExtLoader::getInstalled(true);

        if (empty($installed)) {
            $builder->notify('已安装扩展为空！请确保数据库连接正常，然后安装[tpext.manager]', 'warning', 2000);
        }

        if (!empty($installed)) {
            if (!ExtensionModel::where('key', Module::class)->find()) {
                Module::getInstance()->install();
                $installed = ExtLoader::getInstalled(true);
            }
        }

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
                    break;
                }
            }

            $instance->copyAssets();

            $data[] = [
                'id' => str_replace('\\', '-', $key),
                'install' => $is_install,
                'enable' => $is_enable,
                'name' => $instance->getName(),
                'title' => $instance->getTitle(),
                'description' => $instance->getDescription(),
                'version' => $instance->getVersion(),
                'ext_type' => $instance instanceof BaseModule ? 1 : 2,
                'tags' => $instance->getTags(),
                '__h_in__' => $is_install,
                '__h_un__' => !$is_install,
                '__h_st__' => !$is_install || !$has_config,
                '__h_cp__' => empty($instance->getAssets()),
                '__d_un__' => 0,
            ];

            if ($key == Module::class || $key == TpextCore::class) {
                $data[$key]['__h_un__'] = 0;
                $data[$key]['__h_st__'] = 1;
                $data[$key]['__h_cp__'] = 1;
            }
        }

        $table->show('title', '标题');
        $table->show('name', '标识');
        $table->match('ext_type', '扩展类型')->options(
            [
                1 => '<label class="label label-info">模块</label>',
                2 => '<label class="label label-success">资源</label>',
            ]);
        $table->show('tags', '分类');
        $table->show('description', '介绍')->getWapper()->addStyle('width:40%;');
        $table->show('version', '版本号');
        $table->match('install', '安装')->options(
            [
                0 => '<label class="label label-secondary">未安装</label>',
                1 => '<label class="label label-success">已安装</label>',
            ]);

        $table->switchBtn('enable', '启用')->autoPost(url('enable'));

        $table->getToolbar()
            ->btnRefresh();

        $table->getActionbar()
            ->btnLink('install', url('install', ['key' => '__data.id__']), '', 'btn-primary', 'mdi-plus', 'title="安装"')
            ->btnLink('uninstall', url('uninstall', ['key' => '__data.id__']), '', 'btn-danger', 'mdi-delete', 'title="卸载"')
            ->btnLink('setting', url('config/extConfig', ['key' => '__data.id__']), '', 'btn-info', 'mdi-settings', 'title="设置"')
            ->btnPostRowid('copyAssets', url('copyAssets'), '', 'btn-purple', 'mdi-redo', 'title="刷新资源"'
                , '刷新资源将清空并还原`/assets/`下对应扩展目录中的文件，原则上您不应该修改此目录中的任何文件或上传新文件到其中。若您这么做了，请备份到其他地方，然后再刷新资源。确定要刷新吗？')
            ->mapClass([
                'install' => ['hidden' => '__h_in__'],
                'uninstall' => ['hidden' => '__h_un__'],
                'setting' => ['hidden' => '__h_st__'],
                'copyAssets' => ['hidden' => '__h_cp__'],
            ]);

        $table->data($data);

        $table->paginator(count($this->extensions), $pagesize);

        if (request()->isAjax()) {

            return $table->partial()->render();
        }

        return $builder->render();
    }

    public function install($key = '')
    {
        if (empty($key)) {
            return Builder::getInstance()->layer()->close(0, '参数有误！');
        }

        $id = str_replace('-', '\\', $key);

        if (!isset($this->extensions[$id])) {
            return Builder::getInstance('扩展管理')->layer()->close(0, '扩展不存在！');
        }

        $installed = ExtLoader::getInstalled();

        if (empty($installed) && $id != Module::class) {
            return Builder::getInstance('扩展管理')->layer()->close(0, '已安装扩展为空！请确保数据库连接正常，然后安装[tpext.manager]');
        }

        $instance = $this->extensions[$id];

        $builder = Builder::getInstance('扩展管理', '安装-' . $instance->getTitle());

        if (request()->isPost()) {
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
            $this->detail($form, $instance, true);
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
            return Builder::getInstance('扩展管理')->layer()->close(0, '扩展不存在！');
        }

        $instance = $this->extensions[$id];

        $builder = Builder::getInstance('扩展管理', '卸载-' . $instance->getTitle());

        if (request()->isPost()) {
            $res = $instance->uninstall();
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
            $this->detail($form, $instance, false);
            return $builder->render();
        }
    }

    /**
     * Undocumented function
     *
     * @param \tpext\builder\common\Form $form
     * @param \tpext\common\Module $instance
     * @return void
     */
    public function detail($form, $instance, $isInstall = true)
    {
        $modules = $instance instanceof BaseModule ? $instance->getModules() : [];
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
        $form->raw('modules', '提供模块')->value(!empty($bindModules) ? implode('<br>', $bindModules) : '无');
        $form->raw('desc', '介绍')->value($instance->getDescription());
        $form->raw('version', '版本号')->value($instance->getVersion());

        if ($isInstall) {
            if (is_file($instance->getRoot() . 'data' . DIRECTORY_SEPARATOR . 'install.sql')) {
                $form->show('install', '安装脚本')->value('安装将运行SQL脚本');
            } else {
                $form->show('install', '安装脚本')->value('无');
            }
            $form->btnSubmit('安&nbsp;&nbsp;装', 1, 'btn-success btn-loading');
            $form->html('', '', 3)->showLabel(false);
            $form->btnLayerClose();
        } else {
            if (is_file($instance->getRoot() . 'data' . DIRECTORY_SEPARATOR . 'uninstall.sql')) {
                $form->show('uninstall', '卸载脚本')->value('卸载将运行SQL脚本');
            } else {
                $form->show('uninstall', '卸载脚本')->value('无');
            }
            $form->btnSubmit('卸&nbsp;&nbsp;载', 1, 'btn-danger btn-loading');
            $form->html('', '', 3)->showLabel(false);
            $form->btnLayerClose();
        }

        $form->tab('README.md');
        $README = '暂无';
        if (is_file($instance->getRoot() . 'README.md')) {
            $README = file_get_contents($instance->getRoot() . 'README.md');
        }
        $form->mdreader('README')->jsOptions(['readOnly' => true, 'width' => 1200])->size(0, 12)->showLabel(false)->value($README);

        $form->tab('CHANGELOG.md');
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
    }

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

            ExtLoader::getInstalled(true);

            $this->success(($value == 1 ? '启用' : '禁用') . '成功');
        } else {
            $this->error('修改失败，或无更改');
        }
    }
}
