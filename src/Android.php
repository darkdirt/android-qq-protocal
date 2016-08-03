<?php 

require_once 'Coder.php';
require_once 'HexPacket.php';
require_once 'Tea.php';
require_once 'Tlv.php';

class Android {

	public static function now()
	{
		return Coder::num2Hexstr(time(), 4);
	}

	const IP = '113.108.90.53';
	const PORT = 8080;

	const HEART_INTERVAL = 8 * 60;

	const SEQ = '';
	const APPID = '20029f54'; // num2Hexstr(537042772, 4);
	const EXT_BIN = '';
	const MSGCOOKIES = 'f9838d80'; // trim('F9 83 8D 80')
	const IMEI = '383634313136313935373937393232'; // str2Hexstr('864116195797922')
	const KSID = '';
	const VER = '7c3436303030363230323231373439317c41352e382e302e313537313538'; // str2Hexstr('|460006202217491|A5.8.0.157158')
	const OS_TYPE = '616e64726f6964'; // str2Hexstr('android')
	const OS_VERSION = '342e322e32'; // str2Hexstr('4.2.2')
	const NETWORK_TYPE = '';
	const SIM_OPERATOR_NAME = '434d4343'; // str2Hexstr('CMCC')
	const APN = '77696669'; // str2Hexstr('wifi')

	const DEVICE = '4c656e6f766f204138323074'; // str2Hexstr('Lenovo A820t')
	const DEVICE_PRODUCT = '4c656e6f766f'; // str2Hexstr('Lenovo')
	const PACKAGE_NAME = '636f6d2e74656e63656e742e6d6f62696c657171'; // str2Hexstr('com.tencent.mobileqq')
	const WIFINAME = '4f4f4f4f4f4f4f4f4f'; // str2Hexstr('OOOOOOOOO')

	const CMD_LOGIN = '77746c6f67696e2e6c6f67696e'; // str2Hexstr('wtlogin.login')

	protected $number;
	protected $password;

	protected $qqHexstr;
	protected $pwdMd5;
	protected $uin;
	protected $server_time;
	protected $alive = false;
	protected $verify = false;
	protected $verifyToken1 = null;
	protected $verifyToken2 = null;
	protected $vcode = '';

	protected $keys = [
		'default' => null,
		'random' => null,
		'id' => null,
		'pub' => null,
		'share' => null,
		'pwd' => null,
		'tgt' => null,
		'session' => null,
	];

	public $client = null;

	public function __construct($number, $password)
	{
		$this->number = $number;
		$this->password = $password;
		$this->init();
		$this->client = new swoole_client(SWOOLE_SOCK_TCP);
		if (!$this->client->connect(self::IP, self::PORT, -1))
		{
		    exit("connect failed. Error: {$client->errCode}\n");
		}
	}

	public function __destruct()
	{
		$this->client->close();
	}

	protected function init()
	{
		$this->qqHexstr = Coder::str2Hexstr($this->number);
		$this->pwdMd5 = md5($this->password);
		$this->uin = Coder::qqNumber2Hexstr($this->number);
		$this->server_time = self::now();
		$keys = require_once 'keys.php';
		$this->keys['default'] = '00000000000000000000000000000000';
		$this->keys['random'] = Coder::genBytesHexstr(16);
		$this->keys['id'] = mt_rand(0, count($keys['pubKeys']) - 1);
		$this->keys['pub'] = $keys['pubKeys'][$this->keys['id']];
		$this->keys['share'] = $keys['shareKeys'][$this->keys['id']];
		$this->keys['pwd'] = Coder::hashQqPasswordHexstr($this->number, $this->password);
		$this->keys['tgt'] = Coder::genBytesHexstr(16);
		$this->keys['session'] = '';
		var_dump('uin:'.$this->uin);
		var_dump('pwdMd5:'.$this->pwdMd5);
		var_dump('randomKey:'.$this->keys['random']);
		var_dump('pubKey:'.$this->keys['pub']);
		var_dump('shareKey:'.$this->keys['share']);
		var_dump('pwdKey:'. $this->keys['pwd']);
		var_dump('tgtKey:'.$this->keys['tgt']);

	}

	public function login($verifyCode = null) {
		echo "login\n";
		# 包头
		$packet = Coder::trim('00 00 00 08 02 00 00 00 04 00').Coder::num2Hexstr(strlen($this->qqHexstr)/2+4, 4).$this->qqHexstr;
		# tea包体
		$packet .= $this->packSendLoginMessage($verifyCode);
        #总包长
        $packet = Coder::num2Hexstr(strlen($packet)/2+4, 4).$packet;
        #发送请求
        $packet = Coder::hexstr2Str($packet);
        echo "待发送字节数:".strlen($packet)."\n";
        echo "发送字节数:";
        var_dump($this->client->send($packet));
        # 接收
        $ret = $this->client->recv(10240);
        
        echo "接收字节数:".strlen($ret);
        $pack = new HexPacket(Coder::str2Hexstr($ret));
        #返回包头
        $pack->shr(4);
        $pack->shr(8);
        $pack->shr(2 + strlen($this->qqHexstr)/2);
        #返回包体
        $this->unpackRecvLoginMessage($pack->remain());
        if($this->alive) {
        	echo "登录成功";
        } elseif($this->verify) {
        	echo "需要验证码";
        } else {
        	echo "登录失败";
        }

	}

	protected function packSendLoginMessage($verifyCode = null)
	{
		$msgHeader = Coder::num2Hexstr(self::SEQ + 1, 4);
		$msgHeader .= self::APPID;
		$msgHeader .= self::APPID;
		$msgHeader .= Coder::trim('01 00 00 00 00 00 00 00 00 00 00 00');
		$msgHeader .= Coder::num2Hexstr(strlen(self::EXT_BIN)/2+4, 4) . self::EXT_BIN;
		$msgHeader .= Coder::num2Hexstr(strlen(self::CMD_LOGIN)/2+4, 4) . self::CMD_LOGIN;
		$msgHeader .= Coder::num2Hexstr(strlen(self::MSGCOOKIES)/2+4, 4) . self::MSGCOOKIES;
		$msgHeader .= Coder::num2Hexstr(strlen(self::IMEI)/2+4, 4) . self::IMEI;
		$msgHeader .= Coder::num2Hexstr(strlen(self::KSID)/2+4, 4) . self::KSID;
		$msgHeader .= Coder::num2Hexstr(strlen(self::VER)/2+4, 2) . self::VER;
		$msgHeader = Coder::num2Hexstr(strlen($msgHeader)/2+4, 4) . $msgHeader;
        #Message
        $msg = Coder::trim('1F 41 08 10 00 01').$this->uin.Coder::trim('03 07 00 00 00 00 02 00 00 00 00 00 00 00 00 01 01').$this->keys['random'].Coder::trim('01 02').Coder::num2Hexstr(strlen($this->keys['pub'])/2, 2).$this->keys['pub'];
        #TEA加密的TLV
        $msg .= $this->packSendLoginTlv($verifyCode).Coder::trim('03');
		
        $msg = Coder::num2Hexstr(strlen($msg)/2 + 2 + 1, 2) . $msg;
        $msg = Coder::trim('02').$msg;
        $msg = Coder::num2Hexstr(strlen($msg)/2 + 4, 4) . $msg;

        $packet = $msgHeader . $msg;
        $packet = Tea::enteaHexstr($packet, $this->keys['default']);

        return $packet;
	}

	public function packSendLoginTlv($verifyCode = null)
	{
		if($verifyCode === null) {
			$tlv = Coder::trim('00 09 00 14');
			$tlv .= Tlv::tlv18($this->uin);
            $tlv .= Tlv::tlv1($this->uin, $this->server_time);
            $tlv .= Tlv::tlv106($this->uin, $this->server_time, $this->pwdMd5, $this->keys['tgt'], self::IMEI, self::APPID, $this->keys['pwd']);
            $tlv .= Tlv::tlv116();
            $tlv .= Tlv::tlv100();
            $tlv .= Tlv::tlv107();
            $tlv .= Tlv::tlv144($this->keys['tgt'], self::IMEI, self::OS_TYPE, self::OS_VERSION, self::NETWORK_TYPE, self::SIM_OPERATOR_NAME, self::APN, self::DEVICE, self::DEVICE_PRODUCT);
            $tlv .= Tlv::tlv142(self::PACKAGE_NAME);
            $tlv .= Tlv::tlv145(self::IMEI);
            $tlv .= Tlv::tlv154(self::SEQ);
            $tlv .= Tlv::tlv141(self::SIM_OPERATOR_NAME, self::NETWORK_TYPE, self::APN);
            $tlv .= Tlv::tlv8();
            $tlv .= Tlv::tlv16b();
            $tlv .= Tlv::tlv147();
            $tlv .= Tlv::tlv177();
            $tlv .= Tlv::tlv187();
            $tlv .= Tlv::tlv188();
            $tlv .= Tlv::tlv191();
            $tlv .= Tlv::tlv194();
            $tlv .= Tlv::tlv202(self::WIFINAME);
            $tlv = Tea::enteaHexstr($tlv, $this->keys['share']);
            return $tlv;
		} else {
			$tlv = Coder::trim('00 02 00 04');
            #tlv组包
            $tlv .= Tlv::tlv2($verifyCode, $this->verifyToken1);
            $tlv .= Tlv::tlv8();
            $tlv .= Tlv::tlv104($this->verifyToken2);
            $tlv .= Tlv::tlv116();
            return Tea::enteaHexstr($tlv, $this->keys['share']);
		}
	}
}


$android = new Android('37475756', 'zzw8311906');
$android->login();