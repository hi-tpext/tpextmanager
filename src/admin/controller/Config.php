<?php
namespace tpext\manager\admin\controller;

use think\Controller;
use tpext\common\model\WebConfig;
use tpext\builder\common\Builder;
use tpext\builder\Common\Form;
use tpext\builder\common\Table;
use tpext\common\ExtLoader;

class Config extends Controller
{
    protected $extensions = [];

    protected $dataModel;

    protected function initialize()
    {
        $this->extensions = ExtLoader::getModules();
        ksort($this->extensions);

        $this->dataModel = new WebConfig;
    }

    public function index($confkey = '')
    {
        $builder = Builder::getInstance('配置管理', '配置修改');

        $installed = ExtLoader::getInstalled();

        if (request()->isPost()) {
            $data = request()->post();

            if (!isset($data['config_key'])) {
                $this->success('重新加载配置...', url('index'));
            }

            $config_key = $data['config_key'];

            $theConfig = $this->dataModel->where('key', $config_key)->find();
            if (!$theConfig) {
                $this->success('不存在，重新加载配置...', url('index'));
            }

            $rootPath = app()->getRootPath();

            $filePath = $rootPath . $theConfig['file'];
            if (!is_file($filePath)) {
                $this->error('原始配置文件不存在：' . $theConfig['file']);
            }

            $default = include $filePath;

            unset($data['__config__']);
            unset($data['config_key']);

            $res = $this->seveConfig($default, $data, $config_key);

            if ($res) {
                $this->success('修改成功，页面即将刷新~', url('index', ['confkey' => $config_key]));
            } else {
                $this->error('修改失败，或无变化');
            }
        } else {
            $tab = $builder->tab();
            $extensionsKeys = [];
            foreach ($this->extensions as $key => $instance) {
                $is_install = 0;
                $has_config = !empty($instance->defaultConfig());

                foreach ($installed as $ins) {
                    if ($ins['key'] == $key) {
                        $is_install = $ins['install'];
                        break;
                    }
                }

                $config_key = $instance->getId();
                $extensionsKeys[] = $config_key;

                if (!$is_install || !$has_config) {
                    continue;
                }

                $default = $instance->defaultConfig();

                if (empty($default)) {
                    continue;
                }

                $configData = $this->dataModel->where(['key' => $config_key])->find();

                if (!$configData) {
                    $this->dataModel->create(
                        [
                            'key' => $config_key,
                            'title' => $instance->getTitle(),
                            'file' => str_replace(app()->getRootPath(), '', $instance->configPath()),
                            'config' => json_encode($default),
                        ]);
                }

                $saved = $configData ? json_decode($configData['config'], 1) : [];
                $form = $tab->add($instance->getTitle(), $confkey == $config_key)->form();
                $form->formId('the-from' . $config_key);
                $form->hidden('config_key')->value($config_key);
                $this->buildConfig($form, $default, $saved);
            }

            $orhers = $this->dataModel->where('key', 'not in', $extensionsKeys)->select();

            $rootPath = app()->getRootPath();

            foreach ($orhers as $oth) {
                $filePath = $rootPath . $oth['file'];
                if (!is_file($filePath)) {
                    continue;
                }

                $default = include $filePath;

                $saved = json_decode($oth['config'], 1);
                $form = $tab->add($oth['title'], $confkey == $oth['key'])->form();
                $form->formId('the-from' . $oth['key']);
                $form->hidden('config_key')->value($oth['key']);
                $this->buildConfig($form, $default, $saved);
            }

            $table = $tab->add('更多设置', $confkey == '__config_list__')->table();

            $this->buildList($table);

            return $builder->render();
        }
    }

    public function add()
    {
        if (request()->isPost()) {

            $data = request()->only([
                'title',
                'key',
                'file',
            ], 'post');

            $result = $this->validate($data, [
                'title|名称' => 'require',
                'file|文件路径' => 'require',
            ]);

            if (true !== $result) {
                $this->error($result);
            }

            $filePath = app()->getRootPath() . $data['file'];

            if (!is_file($filePath)) {
                $this->error('文件不存在，请核查');
            }

            if (!preg_match('/.+?(\w+)\.php$/', $data['file'], $matches)) {
                $this->error('不是php文件，请核查');
            }

            if (empty($data['key'])) {
                $data['key'] = $matches[1];
            }

            if ($this->dataModel->where(['key' => $data['key']])->find()) {
                $this->error('key已存在，请核查：' . $data['key']);
            }

            $config = include $filePath;

            $res = $this->dataModel->create(
                [
                    'key' => $data['key'],
                    'title' => $data['title'],
                    'file' => $data['file'],
                    'config' => json_encode($config),
                ]
            );

            if ($res) {
                return Builder::getInstance()->layer()->closeGo(1, '创建成功', url('index', ['confkey' => $data['key']]));
            } else {
                $this->error('创建失败');
            }
        } else {

            $template = <<<EOT
            <pre>
            &lt?php
            return [
                'allowSuffix' => 'jpg,jpeg,gif,wbmp,webpg,png,bmp',
                'maxSize' => 20,
                'isRandName' => 1,
                //配置描述 ,若无则默认为text
                '__config__' => [
                    'allowSuffix' => ['type' => 'textarea', 'label' => '允许上传的文件后缀', 'size' => [2, 10], 'help' => '以英文,号分割'],
                    'maxSize' => ['type' => 'number', 'label' => '上传文件大小限制(MB)', 'col_size' => 6, 'size' => [3, 8], 'required' => 1],
                    'isRandName' => ['type' => 'radio', 'label' => '随机文件名', 'options' => [0 => '否', 1 => '是'], 'col_size' => 6, 'size' => [3, 8]],
                ], //支持【tpext-builder】表单元素 ，不是太复杂的大多能满足。配置的值尽量为常规类型，如果是数组则会转换成json。
            ];
            //使用 \\tpext\admin\model\config('myconfig');
            </code>
EOT;
            $builder = Builder::getInstance('配置管理', '添加');
            $form = $builder->form();
            $form->text('title', '名称')->required()->help('给配置取个名字，如: 商城');
            $form->text('key', '配置键')->help('不填则以文件名为键');
            $form->text('file', '文件路径')->required()->beforSymbol('<code>RootPath .</code>')
                ->help('文件路径，从网站根目录开始，如 <code>config/myconfig.php</code>');
            $form->raw('template', '示例')->value($template)->size(2, 10);

            return $builder->render();
        }
    }

    public function extConfig($key = '')
    {
        if (empty($key)) {
            return Builder::getInstance()->layer()->close(0, '参数有误！');
        }

        $id = str_replace('.', '\\', $key);

        if (!isset($this->extensions[$id])) {
            return Builder::getInstance()->layer()->close(0, '扩展不存在！');
        }

        $instance = $this->extensions[$id];

        $default = $instance->defaultConfig();

        if (empty($default)) {
            return Builder::getInstance()->layer()->close(0, '原始配置不存在');
        }

        $builder = Builder::getInstance('扩展管理', '配置-' . $instance->getTitle());

        if (request()->isPost()) {
            $data = request()->post();

            $res = $this->seveConfig($default, $data, $instance->getId());

            if ($res) {
                return $builder->layer()->closeRefresh(1, '修改成功，页面即将刷新~');
            } else {
                return $builder->layer()->closeRefresh(0, '修改失败，或无变化');
            }

        } else {

            $form = $builder->form();

            $configData = $this->dataModel->where(['key' => $instance->getId()])->find();

            if (!$configData) {
                $this->dataModel->create(
                    [
                        'key' => $instance->getId(),
                        'title' => $instance->getTitle(),
                        'file' => str_replace(app()->getRootPath(), '', $instance->configPath()),
                        'config' => json_encode($default),
                    ]);
            }

            $saved = $configData ? json_decode($configData['config'], 1) : [];

            $this->buildConfig($form, $default, $saved);

            return $builder->render();
        }
    }

    public function autopost()
    {
        $id = input('id/d', '');
        $name = input('name', '');
        $value = input('value', '');

        if (empty($id) || empty($name)) {
            $this->error('参数有误');
        }

        $allow = ['title'];

        if (!in_array($name, $allow)) {
            $this->error('不允许的操作');
        }

        $res = $this->dataModel->where(['id' => $id])->update([$name => $value]);

        if ($res) {
            $this->success('修改成功');
        } else {
            $this->error('修改失败');
        }
    }

    public function delete()
    {
        $ids = input('ids');

        $ids = array_filter(explode(',', $ids), 'strlen');

        if (empty($ids)) {
            $this->error('参数有误');
        }

        $res = 0;

        foreach ($ids as $id) {
            if ($id == 1) {
                continue;
            }
            if ($this->dataModel->destroy($id)) {
                $res += 1;
            }
        }

        if ($res) {
            $this->success('成功删除' . $res . '条数据');
        } else {
            $this->error('删除失败');
        }
    }

    private function buildList(Table &$table)
    {
        $table->show('id', 'ID');
        $table->show('key', '配置键');
        $table->text('title', '名称')->autoPost()->getWapper()->addStyle('max-width:80px');
        $table->show('create_time', '添加时间')->getWapper()->addStyle('width:180px');
        $table->show('update_time', '修改时间')->getWapper()->addStyle('width:180px');
        $table->getToolbar()
            ->btnAdd()
            ->btnDelete();
        $table->getActionbar()
            ->btnDelete();
        $table->sortable([]);

        $data = $this->dataModel->order('key')->select();

        $table->data($data);

        $table->paginator(998, 999);
    }

    private function buildConfig(Form &$form, $default, $saved = [])
    {
        $savedKeys = array_keys($saved);

        $fiedTypes = [];

        if (isset($default['__config__'])) {
            $fiedTypes = $default['__config__'];
        }

        foreach ($default as $key => $val) {

            if ($key == '__config__') {
                continue;
            }

            if (is_array($val)) {
                $val = json_encode($val);
            }

            if (isset($fiedTypes[$key])) {
                $type = $fiedTypes[$key];

                $fieldType = $type['type'];
                $label = isset($type['label']) ? $type['label'] : '';
                $help = isset($type['help']) ? $type['help'] : '';
                $required = isset($type['required']) ? $type['required'] : false;
                $colSize = isset($type['col_size']) && is_numeric($type['col_size']) ? $type['col_size'] : 12;
                $size = isset($type['size']) && is_array($type['size']) && count($type['size']) == 2 ? $type['size'] : [2, 8];

                $field = $form->$fieldType($key, $label, $colSize)->required($required)->default($val)->help($help)->size($size[0], $size[1]);

                if (preg_match('/(radio|select|checkbox|multipleSelect)/i', $type['type'])) {

                    $field->options(isset($type['options']) ? $type['options'] : [0 => '为什么没有选项？', 1 => '？项选有没么什为']);
                }
            } else {

                $field = $form->text($key)->default($val);
            }

            if (in_array($key, $savedKeys)) {
                $field->value($saved[$key]);
            }
        }
    }

    private function seveConfig($default, $data, $config_key)
    {
        $values = [];
        foreach ($default as $key => $val) {

            if ($key == '__config__') {
                continue;
            }

            $values[$key] = $data[$key];

            if (is_array($val)) {
                $values[$key] = json_encode($data[$key]);
            }
        }
        $this->dataModel::clearCache($config_key);

        if ($this->dataModel->where(['key' => $config_key])->find()) {
            return $this->dataModel->where(['key' => $config_key])->update(['config' => json_encode($values)]);
        }

        return $this->dataModel->create(['key' => $config_key, 'config' => json_encode($values)]);
    }
}
