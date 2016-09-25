<?php
class Integral 
{
    private $addr, $accessToken, $referer, $cookie, $reward, $preScore, $count, $score, $err, $errCount, $file, $user, $passwd;
    public function __construct($addr, $file, $user, $passwd)
    {
        for($i = 0; $i < 9; $i++) $this->reward[$i] = 0;
        $this->addr = $addr;
        $this->err = array("");
        $this->errCount = 0;
        $this->preSocre = 0;
        $this->count = 0;
        $this->score = 0;
        $this->file = $file;
        $this->user = $user;
        $this->passwd = $passwd;     
    }

    public function curl_request($page, $postData=array())
    {
        $url = "http://".$this->addr.$page;
        if($postData)
            $headers = array("POST ".$page." HTTP/1.1");
        else
            $headers = array("GET ".$page." HTTP/1.1");
        array_push($headers, "Host: ".$this->addr);
        array_push($headers, "accessToken: ".$this->accessToken);
        array_push($headers, "User-Agent: Dalvik/1.6.0 (Linux; U; Android 4.4.2)");
        array_push($headers, "Accept-Encoding: gzip");
        if($postData)
            array_push($headers, "Content-Type: application/json; charset=utf-8");
        $ch = curl_init();
        if(!$this->cookie)
            curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_REFERER, $this->referer);
        if($postData)
        {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }
        if($this->cookie)
            curl_setopt($ch, CURLOPT_COOKIE, $this->cookie);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $data = curl_exec($ch);
        if(curl_errno($ch))
            return curl_error($ch);
        curl_close($ch);
        list($header, $body) = explode("\r\n\r\n", $data, 2);
        if(!$this->cookie)
        {
            preg_match("/Set\-Cookie: ([^;]*);/", $header, $matches);
            if(isset($matches[1]))
                $this->cookie = $matches[1];
        }
        //抓取Referer
        {
            preg_match_all("/Location: (.*?)\r\n/is", $header, $matches);
            if(isset($matches[1][0]))
            {
                $this->referer = $this->addr.substr($matches[1][0], 1);
            }
        }
        return $body;
    }

    public function login()
    {
        echo "正在从文件".$this->file."中获取accessToken...\n";
        if(file_exists($this->file))
        {
            $tmpAccessToken = file_get_contents($this->file);
            echo "已获取从文件中获取acckessToken...\n";
        }
        else
            echo "读取文件失败！...\n";
        if(isset($tmpAccessToken) && strlen($tmpAccessToken) == 32)
        {
            $this->accessToken = $tmpAccessToken;
            echo "设置当前accessToken为：".$this->accessToken."\n";
            $testPage = "/v1/account/profile?time=".time();
            echo "正在尝试登录验证accessToken...\n";
            $tmpjson = $this->curl_request($testPage);
            $obj = json_decode($tmpjson);
            if(isset($obj->resultData))
            {
                echo "验证成功！...\n";
                return true;
            }
            else
            {
                echo "验证失败！...\n";
                echo "errorCode: ".$obj->errorCode."\n";
                echo "errorMessage: ".$obj->errorMessage."\n";
            }
        }
        $page = "/v1/account/login";
        $jsonArray = array(
            "pushId"        =>  "",
            "osVersion"     =>  "4.4.2",
            "pushChannel"   =>  "XG",
            "phoneNumber"   =>  $this->user,
            "deviceType"    =>  "Android",
            "gpsLatitude"   =>  0,
            "versionCode"   =>  "1.2.0",
            "uuid"          =>  "0",
            "gpsLangitude"  =>  0,
            "password"      =>  md5($this->passwd),
            "channel"       =>  "XG",
            "clientType"    =>  "ANDROID",
            );
        $json = json_encode($jsonArray);
        echo "正在尝试远程获取accessToken...\n";
        $resJson = $this->curl_request($page, $json);
        if($resJson)
        {
            $obj= json_decode($resJson);
            if(!$obj->resultStatus)
            {
                echo "获取失败！...\n";
                echo "errorCode: ".$obj->errorCode."\n";
                echo "errorMessage: ".$obj->errorMessage."\n";
                return false;
            }
            else
            {
                echo "获取成功：\n";
                $this->accessToken = $obj->resultData;
                echo "当前accessToken: ".$this->accessToken."\n";
                file_put_contents($this->file, $obj->resultData, LOCK_EX);
                return true;
            }
        }
        
    }

    public function lottery()
    {
        $page = "/v1/score/bonus";
        $json = $this->curl_request($page);
        if($json)
        {
            $obj = json_decode($json);
            if(isset($obj->resultData) && isset($obj->resultData->reward))
                 return $obj->resultData->reward;
            else return "";
        }
    }

    public function doLottery($cnt = 0)
    {
        if(!$this->login()) {echo "已失败！...\n"; exit; }
        $this->preScore = $this->getIntegral();
        echo "开始抽奖...\n";
        $this->count = $cnt;
        for($i = 0; $i < $cnt; $i++)
        {
            $result = $this->lottery();
            if(!$result) 
			{
				echo "第".$i."次抽奖失败...\n正在尝试重新登录\n"; 
				if(!$this->login()) { echo "已失败！...\n"; exit; } 
			}
            else $this->stat($result);
        }
        echo "抽奖完成！...\n";
        $this->score = $this->getIntegral();
        echo "正在统计结果...\n\n";
        $this->showSata();
    }

    public function getIntegral()
    {
        $page = "/v1/account/profile?time=".time();
        echo "正在获取积分信息...\n";
        $json = $this->curl_request($page);
        $obj = json_decode($json);
        if(isset($obj->resultData) && isset($obj->resultData->score))
            return $obj->resultData->score;
        else
            return '0';
    }

    public function stat($string)
    {
        switch($string)
        {
            case "谢谢惠顾": $this->reward[0]++; break;
            case "1倍积分":  $this->reward[1]++; break;
            case "2倍积分":  $this->reward[2]++; break;
            case "5倍积分":  $this->reward[3]++; break;
            case "10倍积分":  $this->reward[4]++; break;
            case "30倍积分":  $this->reward[5]++; break;
            case "50倍积分":  $this->reward[6]++; break;
            case "100倍积分":  $this->reward[7]++; break;
            default : $this->reward[8]++; $this->err[$this->errCount] = $string; break;
        }
    }

    public function showSata()
    {
        $sum = 0;
        $string = array("谢谢惠顾", "1倍积分", "2倍积分", "5倍积分", "10倍积分", "30倍积分", "50倍积分", "100倍积分", "未定义");
        foreach ($this->reward as $key => $value) 
        {

            switch($key)
            {
                case 1:  $sum += $value * 10; break;
                case 2:  $sum += $value * 20; break;
                case 3:  $sum += $value * 50; break;
                case 4:  $sum += $value * 100; break;
                case 5:  $sum += $value * 300; break;
                case 6:  $sum += $value * 500; break;
                case 7:  $sum += $value * 1000; break;
                default :  break;
            }
        }
        $sum -= $this->count * 10;
        $profit = intval($this->score) - intval($this->preScore);
        if($profit == $sum) $status = "成功"; else $status = "失败";
        echo "抽奖次数：".$this->count."\t抽奖前积分：".$this->preScore."\t当前积分：".$this->score."\t校验：".$status."\n";
        echo "收益：".$profit."\n";
        if($status == "失败")
        {
            echo "Sum = ".$sum."\n";
            if($this->errCount != 0)
            {
                echo "未定义事件统计：\n";
                foreach($this->err as $key => $value)
                    echo $key.": ".$value."\n";
            }
		}
        echo "次数统计：\n";
        for($i = 0; $i < 9; $i++)
            echo $string[$i].": ".$this->reward[$i]."\n";
    }
}

set_time_limit(1800);
$user = ""; //这里填用户名
$passwd = ""; //这里填密码
$saveFile = "MiFeng_accessToken.data";
$freq = 100; //抽奖次数，建议不要超过500次，容易超时
//使用GET的方式，从地址传参, http://xxx/integral.php?user=xxx&passwd=xxx&freq=xxx
if(isset($_GET['user']) && isset($_GET['passwd']))
{
    $user = $_GET['user'];
    $passwd = $_GET['passwd'];
    if(isset($_GET['freq']))
        $freq = intval($_GET['freq']);
}
if(!$user || !$passwd)
{
    echo "你没有设置好用户名和密码, 请编辑本文件\n";
	exit;
}
$obj = new Integral("campus.30days-tech.com:21310", $saveFile , $user, $passwd);
$obj->doLottery($freq);