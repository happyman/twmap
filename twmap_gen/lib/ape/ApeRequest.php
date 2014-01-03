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
 * @version    	@package_version@
 * @package 	APE_PHP 
 * @since       File available since Release 0.1.0
 */ 
?>
<?php  

/**
 * ApeResponse class.
 * 
 * Represents a response from a APE server.
 *
 * @author     	Timo Michna <timomichna@yahoo.de>
 * @copyright  	Copyright (c) 2010, Timo Michna 
 * @package 	APE_PHP
 * @version     @package_version@
 * @since       File available since Release 0.1.0 
 */
class ApeRequest {

	protected 	
		$requestCommand,
		$params = array(),
		$RawData;
	
	/**
	 * Constructor.
	 *
	 * @access public
	 * @param  $requestCommand string request command to send to the APE server
	 * @param  $params array request parameters as key/value pairs 
	 * @return 
	 * @since  Available since release 0.1.0.
	 */
	public function __construct($requestCommand, array $params = array()){
		$this->requestCommand = (string)$requestCommand;
		$this->setParams($params);		
	}

	/**
	 * Returns the request command to send to the APE server.
	 *
	 * @access public
	 * @return string
	 * @since  Available since release 0.1.0.
	 */	
	public function getRequestCommand(){
		return $this->requestCommand;
	}
	
	/**
	 * Sets a specific request parameter.
	 *
	 * @access public
	 * @param  $name string name of the parameter
	 * @param  $value mixed value of the parameter
	 * @return void
	 * @since  Available since release 0.1.0.
	 */		
	public function setParam($name, $value){
		$this->params[(string)$name] = $value;
	}

	/**
	 * Returns the value of a specific request parameter.
	 *
	 * @access public
	 * @param  $name name of the parameter
	 * @return mixed value of the parameter
	 * @since  Available since release 0.1.0.
	 */		
	public function getParam($name){
		return array_key_exists($name, $this->params) ? $this->params[$name] : NULL;
	}	

	/**
	 * Sets all request parameters as an array of key/value pairs.
	 *
	 * @access public
	 * @param  $params array request parameters as key/value pairs
	 * @return void
	 * @since  Available since release 0.1.0.
	 */		
	public function setParams(array $params){
		$this->params = $params;
	}

	/**
	 * Returns all request parameters as an array of key/value pairs.
	 *
	 * @access public
	 * @return array request parameters as key/value pairs
	 * @since  Available since release 0.1.0.
	 */		
	public function getParams(){
		return $this->params;
	}

	/**
	 * Returns the request data as an array.
	 *
	 * @access public
	 * @return array request data
	 * @since  Available since release 0.1.0.
	 */		
	public function getRequestData(){
		return array( 
  			'cmd' => $this->getRequestCommand(), 
  			'params' =>  $this->getParams() 
		); 
	}

	/**
	 * Returns request data as JSON string to use in a POST request.
	 *
	 * @access public
	 * @return string request data as JSON string
	 * @since  Available since release 0.1.0.
	 */		
	public function getJsonString(){
		if (!empty($this->RawData))
			return $this->RawData;
		return json_encode(array($this->getRequestData()));
	}

	/**
	 * Returns request data as urlencoded JSON string to use in a GET request.
	 *
	 * @access public
	 * @return string request data as JSON string
	 * @since  Available since release 0.1.0.
	 */		
	public function getUrlString(){
		return rawurlencode($this->getJsonString());	
	}
	public function setRawData($data){
		$this->RawData = $data;
	}
	public function getRawData() {
		return $this->RawData;
	}
}
