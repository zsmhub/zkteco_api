<?php
/**
* 中控考勤机接口开发
*
* @author Insomnia
* @date 2018/02/7
*/
date_default_timezone_set('PRC');  //设置时区

// Include the client classes
include "./jsonwsp-php/jsonwspclient.php";

//通讯密钥
$public_key = '9dbbada61d4811dz896dc88ad261a170';

/**
* 中控考勤机接口类
*/
class Attendance {
    //考勤机访问ip和端口号
    private $ip = '192.168.1.x:8000';
    /**
    * 数据库访问配置
    */
    //数据库ip
    private $serverName = '192.168.1.x';
    //数据库名
    private $database = "zkteco";
    //登陆账号
    private $uid = "login_reader";
    //登陆密码
    private $pwd = "ab@1#2";

    //考勤机接口执行结果字典：接口名对应字典信息
    private $config_dict = array(
        'setEmp' => array(
            '0' => '成功',
            '-91' => '人员编号只支持数字',
            '-92' => '人员编号只支持数字或字母',
            '-93' => '人员编号不能为0',
            '-94' => '人员编号的长度不能大于指定长度',
            '-95' => '卡号只支持数字',
            '-96' => '卡号不能为0',
            '-98' => '部门编号不存在',
            '-99' => '职位编号不存在',
            '-100' => '部门编号对应的职位编号不一致',
            '-101' => '卡号已被使用',
            '-102' => '区域编号不存在',
            '-103' => '设备权限必须为4,8,10',
            '-104' => '该卡号有注册了消费卡账号',
            '-105' => '已有一张ID有效卡，请先挂失，在换卡号',
            '-106' => '人员密码必须是整数',
            '-107' => '出生日期格式错误',
            '-108' => '邮箱格式不正确',
            '-109' => '手机号码格式不正确',
            '-110' => '雇佣日期格式错误',
            '-111' => '雇佣类型只能是1,2',
            '-112' => '员工类型只能是1,2',
            '-113' => '身份证号码格式错误'
        ),
    );

    /**
    * 获取接口调用执行结果
    */
    private function get_result($response) {
        if($response->getJsonWspType() == JsonWspType::Response && $response->getCallResult() == JsonWspCallResult::Success) {
            // Get the result data
            $responseJson = $response->getJsonResponse();
            $result = $responseJson["result"];

            // Output succes and the contents of the result
            return array('success' => true, 'ret' => $result);
        } elseif($response->getJsonWspType() == JsonWspType::Fault){  // Check the response type is a fault
            // Handle service fault here
            return array('success' => false, 'ret' => "Service fault: ".$response->getServiceFault()->getString());
        } else {  // Other reasons that it is not a valid method response
            // Other error, check callresult and jsonwsp type
            return array('success' => false, 'ret' => "Other service error.");
        }
    }

    /**
    * 日期时间格式判断
    */
    private function is_date($date,$fmt='Y-m-d'){
        if(empty($date)) return false;
        return date($fmt,strtotime($date))== $date;
    }

    /**
    * 新增或编辑人员基本信息
    *
    * @param array $param 参数
    */
    public function employee_service($param=array()) {
        /*** 参数判断begin ***/
        if(empty($param['DeptID'])) return array('success' => false, 'msg' => '部门编号不能为空');
        if(empty($param['EName'])) return array('success' => false, 'msg' => '员工姓名不能为空');

        //新增或编辑操作判断
        $operation = isset($param['operation']) ? $param['operation'] : 'add';
        $operation_text = ($operation=='add' ? '新增' : '编辑');

        //编辑操作时需要传参数：人员编号
        if($operation == 'edit' && empty($param['pin'])) return array('success' => false, 'msg' => '人员编号不能为空');
        /*** 参数判断begin ***/

        /*** 人员操作begin ***/
        $client = new JsonWspClient("http://{$this->ip}/webservice/rpc/EmployeeService/jsonwsp/description");

        // Use a proxy instead of the native service url (from the description)
        $client->setViaProxy(true);

        //获取已有的人员编号列表
        $response = $client->CallMethod("GetPinList", array(
            'key' => ''
        ));
        $ret_pin_list = $this->get_result($response);

        if($ret_pin_list['success']) {
            $emp_info = new Employee();
            //部门编号
            $emp_info->DeptID = $param['DeptID'];
            //员工姓名
            $emp_info->EName = $param['EName'];

            //add或edit方法
            $flag = FALSE;
            if($operation == 'add') {
                //获取缓存累计的人员编号
                $path_pin = './cache/pin.log';
                $cache_pin = file_get_contents($path_pin);
                if(!empty($cache_pin)) $emp_info->pin = $cache_pin;

                //人员编号:代码自动设置, 查看人员编号是否重复, 公司默认一开始从8000开始编号，后续则按累计数据开始编号
                $i = 0;
                while(in_array($emp_info->pin, $ret_pin_list['ret'])) {
                    $emp_info->pin += 1;
                    $i++;

                    //循环次数超过1000次，返回错误，避免死循环
                    if($i>1000) {
                        $flag = TRUE;
                        break;
                    }
                }

                if($flag === FALSE) {
                    $ret_put = file_put_contents($path_pin, $emp_info->pin);
                    if($ret_put === FALSE) {
                        return array('success' => false, 'msg' => '人员编号累计数保存失败！请联系管理员！');
                    }
                }
            } else {  //edit
                $emp_info->pin = $param['pin'];
            }

            if($flag) return array('success' => false, 'msg' => '考勤机公司人员编号范围大部分已经被占用，请联系hr修改公司人员编号范围');

            //新增人员基本信息的接口调用
            $response = $client->CallMethod("setEmp", array(
                'emp_info' => $emp_info
            ));
            $ret_emp_info = $this->get_result($response);
            $ret_emp_info_result = json_decode($ret_emp_info['ret'], true);  //返回结果
            if($ret_emp_info['success'] && $ret_emp_info_result['ret']==0) {
                return array('success' => true, 'msg' => $operation_text . '人员成功', 'emp_info_pin' => $emp_info->pin);
            } else {
                return array('success' => false, 'msg' => $operation_text . '人员失败：' . $this->config_dict['setEmp'][$ret_emp_info_result['ret']]);
            }
        } else {
            return array('success' => false, 'msg' => '考勤机人员编号列表接口获取失败');
        }
        /*** 人员操作end ***/
    }

    /**
    * 人员基本信息查询
    *
    * @param array $param 参数
    */
    public function employee_query($param=array()) {
        //人员编号
        if(empty($param['pin'])) return array('success' => false, 'msg' => '人员编号不能为空');
        $pin = $param['pin'];

        /*** 人员信息查询begin ***/
        $client = new JsonWspClient("http://{$this->ip}/webservice/rpc/EmployeeService/jsonwsp/description");

        // Use a proxy instead of the native service url (from the description)
        $client->setViaProxy(true);

        //获取已有的人员编号列表
        $response = $client->CallMethod("GetPinList", array(
            'key' => ''
        ));
        $ret_pin_list = $this->get_result($response);

        if($ret_pin_list['success']) {
            if(in_array($pin, $ret_pin_list['ret'])) {
                return array('success' => true, 'msg' => '人员信息查询成功', 'data' => array(
                    'pin' => $pin,
                    'query_time' => date('Y-m-d H:i:s', time())
                ));
            } else {
                return array('success' => false, 'msg' => '该人员编号不存在');
            }
        } else {
            return array('success' => false, 'msg' => '接口获取异常');
        }
        /*** 人员信息查询end ***/
    }

    /**
    * 人员考勤查询(返回人员在某个时段的打卡记录)
    *
    * @param array $param 参数
    */
    public function attendance_query($param=array()) {
        //人员编号
        if(empty($param['pin'])) return array('success' => false, 'msg' => '人员编号不能为空');
        $pin = $param['pin'];

        //打卡开始时间
        if(!$this->is_date($param['begin_date'], 'Y-m-d H:i:s')) return array('success' => false, 'msg' => '打卡开始时间格式不正确,请精确到时分秒');
        $begin_date = $param['begin_date'];

        //打卡结束时间
        if(!$this->is_date($param['end_date'], 'Y-m-d H:i:s')) return array('success' => false, 'msg' => '打卡结束时间格式不正确,请精确到时分秒');
        $end_date = $param['end_date'];

        //查询sql
        $sql = "SELECT
                id as ID,
                -- 设备序列号
                sn_name,
                -- 人员编号
                pin,
                -- 打卡时间戳
                DATEDIFF(S,'1970-01-01 00:00:00', checktime) timestamp,
                -- 打卡日期
                convert(date, checktime) date
                from checkinout
                WHERE pin='{$pin}' and checktime between '{$begin_date}' and '{$end_date}'
                ";
        //数据库ip
        $serverName = $this->serverName;
        //数据库名
        $database = $this->database;
        //登陆账号
        $uid = $this->uid;
        //登陆密码
        $pwd = $this->pwd;
        //Establishes the connection
        $conn = new PDO("sqlsrv:server = {$serverName}; Database = {$database}", $uid, $pwd);
        //Executes the query
        $get_data = $conn->query($sql);
        //Error handling
        $ret_query = $this->FormatErrors($conn->errorInfo());

        //执行状态判断
        if($ret_query['SQLSTATE'] == '00000') {
            $ret = array();
            while($row = $get_data->fetch(PDO::FETCH_ASSOC)) {
                $ret[] = $row;
            }
            return array('success' => true, 'msg' => '人员考勤查询接口调用成功', 'data' => $ret);
        } else {
            return array('success' => false, 'msg' => '状态码：' . $ret_query['SQLSTATE'] . ', Message:' . $ret_query['Message']);
        }
    }

    /**
    * 返回sqlserver执行状态
    */
    private function FormatErrors($error) {
        return array(
            'SQLSTATE' => $error[0],
            'Code' => $error[1],
            'Message' => $error[2]
        );
   }
}

//员工基本信息类
class Employee {
    //人员编号,公司的人员编号开始数字
    public $pin = 61000;

    //部门编号
    public $DeptID = 'test';

    //员工姓名
    public $EName = 'test';

    //forgame区域编号
    public $area = '1';

    //其他字段
    public $FPHONE = '';
    public $TimeZones = '';
    public $Political = '';
    public $selfpassword = '';
    public $Tele = '';
    public $PostCode = '';
    public $Privilege = '';
    public $education = '';
    public $homeaddress = '';
    public $city = '';
    public $birthplace = '';
    public $Hiredday = '';
    public $state = '';
    public $Birthday = '';
    public $identitycard = '';
    public $lastname = '';
    public $Address = '';
    public $AccGroup = '';
    public $Password = '';
    public $email = '';
    public $Card = '';
    public $emptype = '';
    public $Mobile = '';
    public $Gender = '';
    public $country = '';
    public $hiretype = '';
    public $position = '';
}

/**
* 过滤sql与php文件操作的关键字
* @param string $string
* @return string
*/
function filter_keyword($string) {
     $keyword = 'select|insert|update|delete|truncate|\/\*|\*|\.\.\/|\.\/|union|into|load_file|outfile';
     $arr = explode('|', $keyword);
     $result = str_ireplace($arr, '', $string);
     return $result;
}

/**
* 写文件日志
*/
function writelog($logname,$content){
    $LogFile = './cache/logs/'.$logname.'.log';
    if( $fp = fopen($LogFile,'a') ){
        if (flock($fp, LOCK_EX)) { // 进行排它型锁定
            fwrite($fp, $content);
            flock($fp, LOCK_UN); // 释放锁定
        }
    }
    fclose($fp);
}

//调用考勤接口类
$attendance = new Attendance();

$method = filter_keyword($_POST["method"]);  //调用方法名
$sign   = filter_keyword($_POST["sign"]);  //认证签名
$unixtime = filter_keyword($_POST["unixtime"]);  //当前时间戳
$param = json_decode($_POST['param'], true);  //方法调用参数数组json格式
if(empty($method)) exit(json_encode(array('success' => false, 'msg' => 'method参数不能为空')));
if(empty($sign)) exit(json_encode(array('success' => false, 'msg' => 'sign参数不能为空')));
if(empty($unixtime)) exit(json_encode(array('success' => false, 'msg' => 'unixtime参数不能为空')));
if(empty($param) || !is_array($param)) exit(json_encode(array('success' => false, 'msg' => 'param参数格式为json且不能为空')));

//param参数过滤
$temp = array();
foreach($param as $key => $value) {
    $temp[$key] = filter_keyword($value);
}
$param = $temp;

//参数签名方式：签名认证有效性判断
$local_sign = md5($public_key.$unixtime.$method);
if($local_sign != $sign){
    exit(json_encode(array('success' => false, 'msg' => "Signature Invalid")));
}

//访问方法判断
if(!method_exists($attendance, $method)) {
    exit(json_encode(array('success' => false, 'msg' => "The method is not found")));
}

//有效时间判断,10分钟后失效
$now_time = time();
if(abs(($now_time-$unixtime)/60) > 10) {
    exit(json_encode(array('success' => false, 'msg' => "The time is invalid")));
}

$log = "操作时间：" . date('Y-m-d H:i:s', time()) . ", method：{$method}，sign：{$sign}, unixtime: {$unixtime} \r\n";
writelog('operation' . date('Ymd'), $log);

//返回json结果
$ret = $attendance->$method($param);
exit(json_encode($ret));
