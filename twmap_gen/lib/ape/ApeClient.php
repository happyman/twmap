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

/**
 * ApeClient class.
 * 
 * Used to send requests to a APE server.
 *
 * @author     	Timo Michna <timomichna@yahoo.de>
 * @copyright  	Copyright (c) 2010, Timo Michna 
 * @version    	Release: @package_version@
 * @package 	Ape 
 */
 
class ApeClient {
	
	protected $connection;

	/**
	 * Constructor.
	 *
	 * @access public
	 * @param $serverUrl string URL to connect to the APE-server
	 */	
	public function __construct(ApeAbstractConnection $connection = NULL){
		$this->connection = $connection;	
	}

	/**
	 * Creates an ApeRequest and sends it to the APE-server.
	 *
	 * @access public
	 * @param $requestCommand string request command to send to the APE server
	 * @param $params array request parameters as key/value pairs 
	 * @return ApeResponse
	 */		
	public function dispatchRequest($requestCommand, array $requestParams = array()){
		$request = new ApeRequest($requestCommand, $requestParams);
		return $this->sendRequest($request); 
	}

	/**
	 * Sends an Request to the APE-server.
	 *
	 * @access public
	 * @param $request ApeRequest  Request Object to send data
	 * @return ApeResponse
	 */		
	public function sendRequest(ApeRequest $request){
		return $this->connection->sendRequest($request);
	}
}
