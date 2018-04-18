<?php

/**
* 调用考勤接口api示例
*/
function get_api() {
    $method = 'attendance_query';  //方法名
    $unixtime = time();  //当前时间戳
    $sign = md5('9dbbada61d4811dz896dc88ad261a170' . $unixtime . $method);  //认证签名
    $url = 'http://127.0.0.1:8080/';
    $poststr = array(
        'method' => $method,
        'unixtime' => $unixtime,
        'sign' => $sign,
        'param' => json_encode(array('pin' =>'000000161', 'begin_date' => '2018-01-16 00:00:00', 'end_date' => '2018-01-16 23:59:59'))
    );
    $result = false;
    if(($str = curl($url, $poststr)) && ($json = json_decode($str, true))) {
        $result = $json;
    }
    return $result;
}

/**
 * curl请求
 */
function curl($url,$poststr='',$httpheader=array(),$return_header=FALSE){
    $ch = curl_init();
    $SSL = substr($url, 0, 8) == "https://" ? true : false;
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, $return_header);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    if ( $SSL ) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    }
    if( $poststr!='' ){
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $poststr);
    }
    if( $httpheader ){
        curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
    }

    $data = curl_exec($ch);
    curl_close($ch);
    return  $data;
}

$ret = get_api();
echo json_encode($ret);