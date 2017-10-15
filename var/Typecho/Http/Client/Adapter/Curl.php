<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * CURL适配器
 *
 * @author qining
 * @category typecho
 * @package Http
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 * @version $Id$
 */

/**
 * CURL适配器
 *
 * @author qining
 * @category typecho
 * @package Http
 */
class Typecho_Http_Client_Adapter_Curl extends Typecho_Http_Client_Adapter
{
    /**
     * 判断适配器是否可用
     *
     * @access public
     * @return boolean
     */
    public static function isAvailable()
    {
        return function_exists('curl_version');
    }

    /**
     * 判断请求的URL是否是内网IP
     * @author JoyChou
     * @param string $url 请求地址
     * @return bool
     */
    public function isInnerIP($url)
    {
        // 限制为HTTP/HTTPS协议，防止其他协议攻击
        $params = parse_url($url);
        if ($params['scheme'] != 'http' && $params['scheme'] != 'https'){
            return True;
        }

        $host = $params['host'];
        $ip = gethostbyname($host);

        // IP转换整数型
        $longIP = ip2long($ip);

        /*
            内网IP：
            10.0.0.1 - 10.255.255.254       (10.0.0.0/8)
            192.168.0.1 - 192.168.255.254   (192.168.0.0/16)
            127.0.0.1 - 127.255.255.254     (127.0.0.0/8)
            172.16.0.1 - 172.31.255.254     (172.16.0.0/12)
        */
        return ip2long('127.0.0.0')>>24 == $longIP>>24 ||
            ip2long('10.0.0.0')>>24 == $longIP>>24 ||
            ip2long('172.16.0.0')>>20 == $longIP>>20 ||
            ip2long('192.168.0.0')>>16 == $longIP>>16;
    }

    /**
     * 发送请求
     *
     * @access public
     * @param string $url 请求地址
     * @return string
     */
    public function httpSend($url)
    {
        if ($this->isInnerIP($url)) {
            throw new Typecho_Http_Client_Exception('Scheme is not https/http or IP is inner ip', 500);
        }
        $ch = curl_init();

        if ($this->ip) {
            $url = $this->scheme . '://' . $this->ip . $this->path;
            $this->headers['Rfc'] = $this->method . ' ' . $this->path . ' ' . $this->rfc;
            $this->headers['Host'] = $this->host;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_PORT, $this->port);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        /** 设置HTTP版本 */
        switch ($this->rfc) {
            case 'HTTP/1.0':
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
                break;
            case 'HTTP/1.1':
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
                break;
            default:
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_NONE);
                break;
        }

        /** 设置header信息 */
        if (!empty($this->headers)) {
            if (isset($this->headers['User-Agent'])) {
                curl_setopt($ch, CURLOPT_USERAGENT, $this->headers['User-Agent']);
                unset($this->headers['User-Agent']);
            }

            $headers = array();

            if (isset($this->headers['Rfc'])) {
                $headers[] = $this->headers['Rfc'];
                unset($this->headers['Rfc']);
            }

            foreach ($this->headers as $key => $val) {
                $headers[] = $key . ': ' . $val;
            }

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        /** POST模式 */
        if (Typecho_Http_Client::METHOD_POST == $this->method) {
            if (!isset($this->headers['content-type'])) {
                curl_setopt($ch, CURLOPT_POST, true);
            }

            if (!empty($this->data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($this->data) ? http_build_query($this->data) : $this->data);
            }

            if (!empty($this->files)) {
                foreach ($this->files as $key => &$file) {
                    $file = '@' . $file;
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->files);
            }
        }

        $response = curl_exec($ch);
        if (false === $response) {
            throw new Typecho_Http_Client_Exception(curl_error($ch), 500);
        }

        curl_close($ch);
        return $response;
    }
}