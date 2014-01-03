<?php

// xuite oauth
//

class xuiteAuth {
	var $data;
	var $api_key;
	var $secret;
	var $debug; 
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
		if (empty($redir_url))
			$redir_url = urlencode($_SERVER['SCRIPT_URI']);

		$url=sprintf("http://my.xuite.net/service/account/authorize.php?response_type=code_and_token&client_id=%s&redirect_uri=%s",$this->api_key,$redir_url);
		$this->debuglog("getLoginURL($redir_url)=$url");
		return $url;
	}
	// todo: maybe buggy
	function getRefreshURL($redir_url="") {
		if (empty($redir_url))
			$redir_url = urlencode($_SERVER['SCRIPT_URI']);
		$url=sprintf("http://my.xuite.net/service/account/token.php?grant_type=refresh_token&client_id=%s&client_secret=%s&redirect_uri=%s&refresh_token=%s",$this->api_key,$this->secret,$redir_url,$this->data['access_token']);
		$this->debuglog("getRefreshURL($redir_url)=$url");
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
	function request_curl($url, $method='GET', $params=array()) {
		$params = http_build_query($params, '', '&');
		$curl = curl_init($url . ($method == 'GET' && $params ? '?' . $params : ''));
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Accept: application/xrds+xml, */*'));

		//	if($this->verify_perr !== null) {
		//		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $this->verify_peer);
		//if($this->capath) {
		//curl_setopt($curl, CURLOPT_CAPATH, $this->capath);
		//}
		//}

		if ($method == 'POST') {
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
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

			# Updating claimed_id in case of redirections.
			$effective_url = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
			return $headers;
		}

		if (curl_errno($curl)) {
			throw new ErrorException(curl_error($curl), curl_errno($curl));
		}

		return $response;
	}
	function getMe() {
		$url = $this->getAPIURL("xuite.my.private.getMe",array("auth"=>$this->data['access_token']));
		return json_decode($this->request_curl($url),true);
	}
	function getVlog($id) {
		$url = $this->getAPIURL("xuite.vlog.public.getVlog",array("vlog_id" => $id));
		return json_decode($this->request_curl($url),true);

	}
}
