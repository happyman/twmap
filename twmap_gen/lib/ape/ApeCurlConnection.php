<?php
/**
 * APE_PHP
 * 
 * Copyright (C) 2010,  Timo Michna <timomichna@yahoo.de>
 *
 * This file is part of APE_PHP.
 *
 * APE_PHP is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or any later version.
 *
 * APE_PHP is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with APE_PHP.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author     	Timo Michna <timomichna@yahoo.de>
 * @copyright  	Copyright (c) 2010, Timo Michna 
 * @version    	Release: @package_version@
 * @package 	APE_PHP 
 * @since       File available since Release 0.1.0
 */ 
?>
<?php


class ApeCurlConnection extends ApeAbstractConnection {

	protected $ch;
	protected $proxy;
	function setProxy($proxy,$port) {
		$this->proxy = sprintf("http://%s:%d",$proxy,$port);
	}
	protected function doSendRequest(ApeRequest $request){
		if (!$this->ch) {
			$this->ch = curl_init();
			// error_log("new curl");
		}
		$ch = $this->ch;
		//$rawData = $request->getRawData();
		//if (empty($rawData))
		$rawData = $request->getJsonString();
		if($ch){		
			$headers = array();
			$header[] = "Accept: text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
			$header[] = "Accept-Language: en-us,en;q=0.5";
			$header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
			curl_setopt($ch, CURLOPT_POST, 1 );
			curl_setopt($ch, CURLOPT_POSTFIELDS,$rawData);
			curl_setopt($ch, CURLOPT_URL, $this->getUrl()); // set url to post to    
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 1);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_ENCODING,"");          
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,1);
			curl_setopt($ch, CURLOPT_DNS_USE_GLOBAL_CACHE, false );
			curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 1 );
			if (isset($this->proxy)) {
				curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
			}

			$data = curl_exec($ch);
			$results=false;
			if (!curl_errno($ch)) {
				$results = $data;
			} else {
				error_log(print_r(curl_error($ch), 1));
				throw new ApeException('CURL ERROR '.print_r(curl_error($ch), 1));
			} 
			//curl_close($ch);
			return $results;	    
		}else {
			throw new ApeException('CURL error: Could not init CURL! ');
		} 

	}


	public static function available(){
		return extension_loaded('curl');
	}



}
