<?php

namespace tpext\manager\common\logic;

class ExtensionLogic
{
    protected $errors = [];

    /**
     * Undocumented function
     *
     * @return array
     */
    final public function getErrors()
    {
        return $this->errors;
    }

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

    /**
     * Undocumented function
     *
     * @param string $url
     * @param int $install 安装：1，升级：2
     * @return boolean
     */
    public function download($url, $install = 1)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0");
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
        curl_setopt($ch, CURLOPT_NOBODY, FALSE);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 2);/*指定最多的HTTP重定向的数量，这个选项是和CURLOPT_FOLLOWLOCATION一起使用的*/

        $ssl = preg_match('/^https:\/\//i', $url);
        if ($ssl) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // https请求 不验证证书和hosts
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); // 不从证书中检查SSL加密算法是否存在
        }

        $response = curl_exec($ch);

        $body = '';
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == '200') {
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE); //头信息size
            $body = substr($response, $headerSize);
        }

        curl_close($ch);

        $file = time() . '_' . mt_rand(100, 999) . '.zip';

        $dir = app()->getRuntimePath() . 'extend' . DIRECTORY_SEPARATOR;

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        if (file_put_contents($dir . $file, $body)) {
            $installRes = $this->installExtend($dir . $file, $install);
            @unlink($dir . $file);
            return $installRes;
        }

        return false;
    }

    /**
     * Undocumented function
     *
     * @param string $zipFilePath
     * @param int $install 安装：1，升级：2
     * @return boolean
     */
    public function installExtend($zipFilePath, $install)
    {
        if (!preg_match('/.+?\.zip$/i', $zipFilePath)) {
            trace('不是zip文件：' . $zipFilePath);
            return false;
        }

        try {
            $zip = new \ZipArchive();

            if ($zip->open($zipFilePath) === true) {
                $extendName = $zip->getNameIndex(0);
                if (!preg_match('/^\w+\/$/', $extendName)) {
                    trace('压缩包格式有误，外层至少有一层目录');
                    $this->errors[] = '压缩包格式有误，外层至少有一层目录';

                    return false;
                }

                $dir = app()->getRootPath() . 'extend' . DIRECTORY_SEPARATOR;

                if (!file_exists($dir)) {
                    mkdir($dir, 0775, true);
                }

                if ($install == 1 && is_dir($dir . $extendName)) {
                    trace('扩展目录已存在');
                    $this->errors[] = '扩展目录已存在';

                    return false;
                }

                $res =  $zip->extractTo($dir);
                $zip->close();
                return $res;
            } else {
                trace('打开zip文件失败！' . $zipFilePath);
                $this->errors[] = '打开zip文件失败！' . $zipFilePath;
                return false;
            }
        } catch (\Throwable $e) {
            trace($e->__toString());
            $this->errors[] = '系统错误-' . $e->getMessage();
            return false;
        }
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

                    if (preg_match('/.+?\\\extend\\\(.+?)\.php$/i', str_replace('/', '\\', $sonDir), $mtches)) {

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
