<?php
/**
 * Created by PhpStorm.
 * User: anhpha
 * Date: 10/07/2018
 * Time: 16:13
 */

namespace whitemerry\phpkin\Logger;


class SimpleHttpAsyncLogger extends SimpleHttpLogger
{
    /**
     * Overrides trace method to put data to remote server without wait
     */
    public function trace($spans)
    {
        @$this->fireAndForget($this->options['host'] . $this->options['endpoint'], 'POST', 'application/json', $spans);

    }
    /**
     * Use to POST to external server without caring about results
     * @param $url
     * @param string $method
     * @param string $contentType
     * @param array $params
     */
    private function fireAndForget($url ,string $method = "POST", string $contentType = 'application/json', $params = array())
    {
        $post_string = json_encode($params);
        if ("application/json" != $contentType){
            $post_string = self::createFormDataString($params);
        }

        // get URL segments
        $parts = parse_url($url);

        // workout port and open socket
        $port = isset($parts['port']) ? $parts['port'] : 80;
        $fp = fsockopen($parts['host'], $port, $errno, $errstr, 30);

        // create output string
        $output  = $method . " " . $parts['path'] . " HTTP/1.1\r\n";
        $output .= "Host: " . $parts['host'] . "\r\n";
        $output .= "Content-Type: " . $contentType ."\r\n";
        $output .= "Content-Length: " . strlen($post_string) . "\r\n";
        $output .= "Connection: Close\r\n\r\n";
        $output .= isset($post_string) ? $post_string : '';


        // send output to $url handle
        fwrite($fp, $output);
        fclose($fp);
    }

    private static function createFormDataString(array $params = array()): string {
        $post_params = array();
        foreach ($params as $key => &$val)
        {
            $post_params[] = $key . '=' . urlencode($val);
        }
        return implode('&', $post_params);
    }

}