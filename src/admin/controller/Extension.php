<?php
namespace tpext\manager\admin\controller;

use think\Controller;
use tpext\builder\common\Builder;
use tpext\common\ExtLoader;
use tpext\common\model\Extension as ExtensionModel;
use tpext\common\TpextCore;
use tpext\manager\common\Module;

class Extension extends Controller
{
    protected $extensions = [];

    protected $dataModel;

    protected function initialize()
    {
        $this->extensions = ExtLoader::getModules();

        $this->extensions[TpextCore::class] = TpextCore::getInstance();

        ksort($this->extensions);

        $this->dataModel = new ExtensionModel;
    }

    public function index()
    {
        $builder = Builder::getInstance('扩展管理', '列表');

        $pagezise = 10;

        $table = $builder->table();

        $page = input('__page__/d', 1);

        if ($page < 1) {
            $page = 1;
        }

        $table->paginator(count($this->extensions), $pagezise);

        $extensions = array_slice($this->extensions, ($page - 1) * $pagezise, $pagezise);

        $data = [];

        $installed = ExtLoader::getInstalled();

        if (empty($installed)) {
            $builder->notify('已安装扩展为空！请确保数据库连接正常，然后安装[tpext.manager]', 'warning', 2000);
        }

        if (!empty($installed)) {
            if (!ExtensionModel::where('key', Module::class)->find()) {
                Module::getInstance()->install();
                $installed = ExtLoader::getInstalled();
            }
        }

        foreach ($extensions as $key => $instance) {
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

            $data[$key] = [
                'id' => str_replace('\\', '.', $key),
                'install' => $is_install,
                'enable' => $is_enable,
                'name' => $instance->getName(),
                'title' => $instance->getTitle(),
                'description' => $instance->getDescription(),
                'version' => $instance->getVersion(),
                'tags' => $instance->getTags(),
                '__h_in__' => $is_install,
                '__h_un__' => !$is_install,
                '__h_st__' => !$is_install || !$has_config,
                '__h_en__' => !$is_install || $is_enable,
                '__h_dis__' => !$is_install || !$is_enable,
                '__h_cp__' => empty($instance->getAssets()),
                '__d_un__' => 0,
            ];

            if ($key == Module::class || $key == TpextCore::class) {
                $data[$key]['__h_un__'] = 0;
                $data[$key]['__h_st__'] = 1;
                $data[$key]['__h_en__'] = 1;
                $data[$key]['__h_dis__'] = 1;
                $data[$key]['__h_cp__'] = 1;
                $data[$key]['__d_un__'] = 1;
            }
        }

        $table->field('name', '标识');
        $table->field('title', '标题');
        $table->field('tags', '类型');
        $table->field('description', '介绍');
        $table->field('version', '版本号');
        $table->match('install', '安装')->options(
            [
                0 => '<label class="label label-secondary">未安装</label>',
                1 => '<label class="label label-success">已安装</label>',
            ]);

        $table->match('enable', '启用')->options(
            [
                0 => '<label class="label label-secondary">未启用</label>',
                1 => '<label class="label label-success">已启用</label>',
            ]);

        $table->getToolbar()
            ->btnEnable()
            ->btnDisable()
            ->btnRefresh()
            ->html('<label class="label label-secondary">系统会缓存扩展信息，若你新安装的扩展不在下面的列表中，请清除缓存，然后刷新。</label>');

        $table->getActionbar()
            ->btnLink('install', url('install', ['key' => '__data.id__']), '', 'btn-primary', 'mdi-plus', 'title="安装"')
            ->btnLink('uninstall', url('uninstall', ['key' => '__data.id__']), '', 'btn-danger', 'mdi-delete', 'title="卸载"')
            ->btnLink('setting', url('config/extConfig', ['key' => '__data.id__']), '', 'btn-info', 'mdi-settings', 'title="设置"')
            ->btnEnable()
            ->btnDisable()
            ->btnPostRowid('copyAssets', url('copyAssets'), '', 'btn-cyan', 'mdi-refresh', 'title="刷新资源"')
            ->mapClass([
                'install' => ['hidden' => '__h_in__'],
                'uninstall' => ['hidden' => '__h_un__', 'disabled' => '__d_un__'],
                'setting' => ['hidden' => '__h_st__'],
                'enable' => ['hidden' => '__h_en__'],
                'disable' => ['hidden' => '__h_dis__'],
                'copyAssets' => ['hidden' => '__h_cp__'],
            ]);

        $table->data($data);

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

        $id = str_replace('.', '\\', $key);

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

        $id = str_replace('.', '\\', $key);

        if (!isset($this->extensions[$id])) {
            return Builder::getInstance('扩展管理')->layer()->close(0, '扩展不存在！');
        }

        $instance = $this->extensions[$id];

        $builder = Builder::getInstance('扩展管理', '安装-' . $instance->getTitle());

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
        $modules = $instance->getModules();
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
        $ids = str_replace('.', '\\', $ids);

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
        $keys = input('ids', '');
        $ids = str_replace('.', '\\', $keys);

        $ids = array_filter(explode(',', $ids), 'strlen');

        if (empty($ids)) {
            $this->error('参数有误');
        }

        $installed = ExtLoader::getInstalled();

        if (empty($installed)) {
            $this->error('已安装扩展为空！请确保数据库连接正常，然后安装[tpext.manager]');
        }

        foreach ($ids as $id) {
            ExtensionModel::where(['key' => $id])->update(['enable' => 1]);
        }

        ExtLoader::getInstalled(true);

        $this->success('启用成功');
    }

    public function disable()
    {
        $keys = input('ids', '');
        $ids = str_replace('.', '\\', $keys);

        $ids = array_filter(explode(',', $ids), 'strlen');

        if (empty($ids)) {
            $this->error('参数有误');
        }

        $installed = ExtLoader::getInstalled();

        if (empty($installed)) {
            $this->error('已安装扩展为空！请确保数据库连接正常，然后安装[tpext.manager]');
        }

        foreach ($ids as $id) {
            if ($id == Module::class || $id == TpextCore::class) {
                continue;
            }
            ExtensionModel::where(['key' => $id])->update(['enable' => 0]);
        }

        ExtLoader::getInstalled(true);

        $this->success('禁用成功');
    }
}
