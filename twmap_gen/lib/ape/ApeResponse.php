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
 * Represents a response from an APE server.
 *
 * @author     	Timo Michna <timomichna@yahoo.de>
 * @copyright  	Copyright (c) 2010, Timo Michna 
 * @version    	@package_version@
 * @package 	APE_PHP 
 */

class ApeResponse {

	protected 
		$response = NULL,
		$rawResponse,
		$success = false;

	/**
	 * Constructor.
	 *
	 * @access public
	 * @param  $rawResponse string raw response from APE server
	 * @return 
	 * @since  Available since release 0.1.0.
	 */		
	public function __construct($rawResponse){
		$this->rawResponse = (string)$rawResponse;
		try {
			$this->response = $this->decodeResponse($rawResponse);				
			if($this->response->raw && $this->response->raw == 'ERR'){
				throw new ApeException('ERROR from APE Response');	
			}
			$this->setSuccess(true);
		}	
		catch(ApeException $e){
			$this->setSuccess(false);	
		}
	}

	/**
	 * Sets success of request.
	 *
	 * @access protected
	 * @param  $success bool wether request was successful
	 * @return void
	 * @since  Available since release 0.1.0.
	 */		
	protected function setSuccess($success){
		$this->success = (bool)$success;
	}

	/**
	 * returns success of request.
	 *
	 * @access public
	 * @return bool wether request was successful
	 * @since  Available since release 0.1.0.
	 */		
	public function isSuccess(){
		return $this->success;
	}		
	
	/**
	 * JSON decodes the raw APE response.
	 *
	 * @access protected
	 * @static
	 * @return mixed decoded JSON response
	 * @throws ApeException
	 * @since  Available since release 0.1.0.
	 */		
	protected static function decodeResponse($rawResponse){
		$response = "";
		if(trim((string)$rawResponse) != ''){
			try{
				//$response = array_shift(json_decode($rawResponse));
				$r = json_decode($rawResponse);
				if ( $r != null ) {
					$response = array_shift($r);
				}
			}			
			catch(Exception $e){
				throw new ApeException('could not decode raw response'); 
			}		
		}else{
			throw new ApeException('raw response not found'); 
		}	
		return $response;	
	}

	/**
	 * returns decoded response from APE server.
	 *
	 * @access public
	 * @return mixed decoded JSON response
	 * @since  Available since release 0.1.0.
	 */			
	public function getResult(){
		return $this->response;
	}

	/**
	 * returns raw JSON response from APE server.
	 *
	 * @access public
	 * @return string raw JSON response
	 * @since  Available since release 0.1.0.
	 */		
	public function getRawResult(){
		return $this->rawResponse;
	}
}
