<?php

// xuite oauth
//

class xuiteAuth {
	var $data;
	var $api_key;
	var $secret;
	var $debug; 
	var $curlinfo;
	function __construct($o) {
		$this->data = $_GET + $_POST;
		$this->api_key = $o['api_key'];
		$this->secret = $o['secret'];
		$this->debug = 0;
	}
	function setData($data) {
		$this->data = $data;
	}
	function debuglog($str){
		error_log($str);
		if ($this->debug==2) {
			printf("%s %s\n",date('Y-m-d H:i:s'),$str);
		}
	}
	function getLoginURL($redir_url="") {
		if (empty($redir_url)){
			if (!isset($_SERVER['SCRIPT_URI']))
				$redir_url = urlencode($_SERVER['REQUEST_URI']);
			else
				$redir_url = urlencode($_SERVER['SCRIPT_URI']);
		}

		$url=sprintf("http://my.xuite.net/service/account/authorize.php?response_type=code_and_token&client_id=%s&redirect_uri=%s",$this->api_key,$redir_url);
		//$this->debuglog("getLoginURL($redir_url)=$url");
		return $url;
	}
	// todo: maybe buggy
	function getRefreshURL($redir_url="") {
		if (empty($redir_url))
			$redir_url = urlencode($_SERVER['SCRIPT_URI']);
		$url=sprintf("http://my.xuite.net/service/account/token.php?grant_type=refresh_token&client_id=%s&client_secret=%s&redirect_uri=%s&refresh_token=%s",$this->api_key,$this->secret,$redir_url,$this->data['access_token']);
		//$this->debuglog("getRefreshURL($redir_url)=$url");
		return $url;

	}
	function session() {
		if (isset($this->data['expire_in']) && time() < $this->data['expire_in']) return $this->data;
		return false;
	}
	function getAPIURL($method,$param=array()) {
		$token = $this->secret;
		$param['api_key'] = $this->api_key;
		$param['method'] = $method;
		ksort($param);
		foreach($param as $val) {
			$token .= $val;
		}
		$this->debuglog("token=[$token]");
		$api_sig = md5($token);
		$url = sprintf("http://api.xuite.net/api.php?api_sig=%s&%s",$api_sig,http_build_query($param, '', '&'));
		$this->debuglog("url=$url");
		return $url;

	}
	function dump() {
		print_r($this->data);
	}
	function getMe() {
		$url = $this->getAPIURL("xuite.my.private.getMe",array("auth"=>$this->data['access_token']));
		return json_decode($this->request_curl($url),true);
	}
	function getVlog($id) {
		$url = $this->getAPIURL("xuite.vlog.public.getVlog",array("vlog_id" => $id));
		return json_decode($this->request_curl($url),true);

	}
	function request_curl($url, $method='GET', $params=array(), &$info="") {
		$params_line = http_build_query($params, '', '&');
		$curl = curl_init($url . ($method == 'GET' && $params_line ? '?' . $params_line : ''));
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		//curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_DNS_USE_GLOBAL_CACHE, false );
		curl_setopt($curl, CURLOPT_DNS_CACHE_TIMEOUT, 1 );

		if ($method == 'POST') {
			curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $params_line);

		} elseif ($method == 'POSTFILE') {
			curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
			curl_setopt($curl, CURLINFO_HEADER_OUT, true);
		} elseif ($method == "GETFILE") {
			curl_setopt($curl,CURLOPT_FILE, $params['fd']);
		} elseif ($method == 'HEAD') {
			curl_setopt($curl, CURLOPT_HEADER, true);
			curl_setopt($curl, CURLOPT_NOBODY, true);
		} else {
			curl_setopt($curl, CURLOPT_HTTPGET, true);
		}
		$response = curl_exec($curl);

		if($method == 'HEAD') {
			$headers = array();
			foreach(explode("\n", $response) as $header) {
				$pos = strpos($header,':');
				$name = strtolower(trim(substr($header, 0, $pos)));
				$headers[$name] = trim(substr($header, $pos+1));
			}
			return $headers;
		}

		if (curl_errno($curl)) {
			throw new ErrorException(curl_error($curl), curl_errno($curl));
		}
		$info = curl_getinfo($curl);

		return $response;
	}
	/**
	 * getMetadata 
	 * 取得 metadata 用來轉換 path => key
	 * @param mixed $path 
	 * @param mixed $type 
	 * @access public
	 * @return void
	 */
	function getMetadata($path, $type) {
		$url = $this->getAPIURL("xuite.webhd.private.cloudbox.getMetadata", array("auth"=>$this->data['access_token'],"path"=>$path,"type"=>$type));
		return json_decode($this->request_curl($url, "GET", array(), $this->curlinfo),true);
	}
	/**
	 * mkdir_p 
	 * 建立目錄
	 * @param mixed $path 
	 * @access public
	 * @return void
	 */
	function mkdir_p($path) {
		$url = $this->getAPIURL("xuite.webhd.private.cloudbox.mkdir_p",array("auth"=>$this->data['access_token'],"path"=>$path));
		return json_decode($this->request_curl($url),true);
	}
	/**
	 * prepare_upload 
	 * 上傳準備
	 * @param mixed $parent_key 
	 * @access public
	 * @return void
	 */
	function prepare_upload($parent_key) {
		$url =  $this->getAPIURL("xuite.webhd.prepare.cloudbox.putFile",array("auth" => $this->data['access_token'], "parent"=>$parent_key));
		return json_decode($this->request_curl($url, "GET", array(), $this->curlinfo),true);
	}
	/**
	 * upload 
	 * 真正上傳, 將 prepare 結果 feed 進來
	 * @param mixed $prepared 
	 * @param mixed $file_path 
	 * @access public
	 * @return void
	 */
	function upload($prepared, $file_path) {
		$result = $prepared['rsp'];
		$params = array("api_key"=> $this->api_key, "api_otp" => $result['otp'], "auth_key" => $result['auth_key'], "checksum" => $result['checksum'],
			"upload_file" => '@' . realpath($file_path));
		$result = $this->request_curl($result['url2'], "POSTFILE", $params, $myinfo);
		if ($myinfo[1]['http_code'] != 200 ) {
			return array(false, $result, $myinfo);
		}
		return array(true,"ok",$myinfo);
	}
	/**
	 * print_code_get 
	 * 取得 ibon 列印碼
	 * @param mixed $key 
	 * @access public
	 * @return void
	 */
	function print_code_get($key) {
		$url =  $this->getAPIURL("xuite.webhd.private.cloudbox.printcode.get",array("auth" => $this->data['access_token'],"key"=>$key, "kiosk_type"=>'ibon'));
		return json_decode($this->request_curl($url),true);
	}
}
