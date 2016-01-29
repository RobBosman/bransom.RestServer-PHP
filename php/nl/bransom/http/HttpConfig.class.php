<?php

Bootstrap::import('nl.bransom.Config');

/**
 * Description of HttpConfig
 *
 * @author Rob Bosman
 */
class HttpConfig {

    public static function setHttpProxy($curl, $url) {
        $config = Config::getInstance();
        $configHttp = $config->getSection('http');
        if ((!isset($configHttp['proxy.hostName'])) or (!isset($configHttp['proxy.port']))
                or (!isset($configHttp['proxy.userName'])) or (!isset($configHttp['proxy.password']))
                or (!isset($configHttp['proxy.except-for-hosts']))) {
            throw new Exception("Config error: please specify properties 'proxy.hostName', 'proxy.port',"
                    . " 'proxy.userName', 'proxy.password' and 'proxy.except-for-hosts' in section [http].");
        }
        $proxyHost = $configHttp['proxy.hostName'];
        $noProxyHosts = $configHttp['proxy.except-for-hosts'];
        if (self::requiresHttpProxy($url, $proxyHost, $noProxyHosts)) {
            $proxyPort = $configHttp['proxy.port'];
            if ((strlen($proxyPort) == 0) or ($proxyPort <= 0)) {
                throw new Exception("Invalid proxy port specified in configuration: '$proxyPort'.");
            }
            curl_setopt($curl, CURLOPT_HTTPPROXYTUNNEL, 1);
            curl_setopt($curl, CURLOPT_PROXY, "$proxyHost:$proxyPort");

            $proxyUser = $configHttp['proxy.userName'];
            $proxyPass = $configHttp['proxy.password'];
            if (strlen($proxyUser) > 0) {
                curl_setopt($curl, CURLOPT_PROXYUSERPWD, "$proxyUser:$proxyPass");
            }
        }
        return $curl;
    }
    
    private static function requiresHttpProxy($url, $proxyHost, $noProxyHosts) {
        if (strlen($proxyHost) == 0) {
            return FALSE;
        }
        $urlHost = preg_filter('|https?://([^:/]*).*|i', '${1}', $url);
        foreach (explode(',', $noProxyHosts) as $noProxyHostMask) {
            if (stripos($noProxyHostMask, $urlHost) !== FALSE) {
                return FALSE;
            }
            $noProxyHostMask = str_replace('*', '', $noProxyHostMask);
            if (stripos($urlHost, $noProxyHostMask) !== FALSE) {
                return FALSE;
            }
        }
        return TRUE;
    }
}

?>
