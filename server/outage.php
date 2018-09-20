<?php
session_start();

class Vaptcha
{
    private static $baseUrl = "http://d.vaptcha.com/";

    private static function getJson($url)
    {
        if (function_exists('curl_exec')) {
            $ch = curl_init();  
            curl_setopt($ch, CURLOPT_URL, $url); 
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);  
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5*1000);  
            $errno = curl_errno($ch);
            $response = curl_exec($ch);
            curl_close($ch);
            return $errno > 0 ? false : json_decode($response);
        } else {
            $opts = array(
                'http' => array(
                    'method' => 'GET',
                    'timeout' => 5*1000
                )
            );
            $context = stream_context_create($opts);
            $response = @file_get_contents($url, false, $context);
            return $response ? json_decode($response) : false;
        }
    }

    private static function getRandomStr() {
        $charArr = str_split('0123456789abcdef');
        $arr = array();
        for ($i = 0; $i < 4; $i++) {
            array_push($arr, $charArr[random_int(0, 15)]);
        }
        return join($arr);
    }

    public function getImage($challenge) {
        $config = self::getJson(self::$baseUrl.'config');
        $key = $config->key;
        $result = array();
        if($key) {
            $str = $key.self::getRandomStr();
            $url = md5($str);
            $result = array(
                "code" => "0103",
                "imgid" => $url,
                "challenge" => $challenge ? $challenge : uniqid().time()
            );
        } else {
             $result = array(
                "code" => "0104",
                "msg" => "宕机key获取失败"
            );
        }
        return $result;
    }

    public function verify($imgid, $v) {
        $url = md5($imgid.$v);
        $result = self::getJson(self::$baseUrl.$url);
        if ($result && $result->code == '200') {
            $result = array(
                "code" => "0103"
            );
        } else {
            $result = array(
                "code" => "0104",
                "msg" => '0104'
            );
        }
        return $result;
    }
}

$vp = new Vaptcha();

$action = $_GET['action'];
if($action == 'get') {
    $data = $vp->getImage($_GET['challenge']);
    $_SESSION[$data['challenge']] = $data['imgid'];
    echo $_GET['callback'].'('.json_encode($data).')';
} else {
    $v = $_GET['v'];
    $challenge = $_GET['challenge'];
    $imgid = $_SESSION[$challenge];
    $data = $vp->verify($imgid, $v);
    if ($data['code'] == "0103") {
        $_SESSION[$challenge] = uniqid().time();
        unset($_SESSION[$challenge]);
        $data['token'] = '01-'.$challenge.'-'.$_SESSION[$challenge];
    }
    echo $_GET['callback'].'('.json_encode($data).')';
}