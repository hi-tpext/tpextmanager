<?php

namespace tpext\manager\common\logic;

class ExtensionLogic
{
    /**
     * 获取通过extend目录方式自定义的扩展
     *
     * @param boolean $reget 是否强制重新扫描文件
     * @return array
     */
    public function getExtendExtensions($reget = false)
    {
        $data = cache('extend_extensions');

        if (!$reget && $data) {
            return $data;
        }

        $data = $this->scanExtends(app()->getRootPath() . 'extend');

        if (empty($data)) {
            $data = ['empty'];
        }

        cache('extend_extensions', $data);

        return $data;
    }

    protected function scanExtends($path, $extends = [])
    {
        if (!is_dir($path)) {
            return [];
        }
        $dir = opendir($path);

        $reflectionClass = null;

        $sonDir = null;

        while (false !== ($file = readdir($dir))) {

            if (($file != '.') && ($file != '..')) {

                $sonDir = $path . DIRECTORY_SEPARATOR . $file;

                if (is_dir($sonDir)) {
                    $extends = array_merge($extends, $this->scanExtends($sonDir));
                } else {
                    $sonDir = str_replace('/', '\\', $sonDir);

                    if (preg_match('/.+?\\\extend\\\(.+?)\.php$/i', $sonDir, $mtches)) {

                        $content = file_get_contents($sonDir); //通过文件内容判断是否为扩展。class_exists方式的$autoload有点问题

                        if (
                            preg_match('/\$version\s*=/i', $content)
                            && preg_match('/\$name\s*=/i', $content)
                            && preg_match('/\$title\s*=/i', $content)
                            && preg_match('/\$description\s*=/i', $content)
                            && preg_match('/\$root\s*=/i', $content)
                        ) {
                            $extends[] = $mtches[1];
                        }
                    }
                }
            }
        }

        unset($reflectionClass, $sonDir);

        return $extends;
    }
}
