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
/** 
 * ApeAbstractConnection class.
 * 
 * abstract Base Class of all ApeConnection classes.
 *
 * @author     	Timo Michna <timomichna@yahoo.de>
 * @copyright  	Copyright (c) 2010, Timo Michna 
 * @version    	Release: @package_version@
 * @package 	Ape 
 * @abstract
 */

abstract class ApeAbstractConnection {
    
    protected 
    	$host,
    	$port,
    	$protocol = false;
    
	/**
	 * Constructor.
	 *
	 * @access public
	 * @param  $host APE server hostname
	 * @param  $port APE server port. defaults to 6969
	 * @param  $secure boolean wether to use a secure http connection.  defaults to false
	 * @return 
	 * @since  Available since release 0.1.0.
	 */	    
    public function __construct($host, $port = 6969, $protocol = 'http'){
    	$this->host = (string)$host;
        $this->port = $port;
        $this->protocol = $protocol;
    }

	/**
	 * returns the base URL to use for APE requests.
	 *
	 * @access public
	 * @return string base URL
	 * @since  Available since release 0.1.0.
	 */	    
    public function getBaseUrl(){
        return $this->protocol.'://'.$this->host;
    }  

	/**
	 * returns the request URL (including port) to use for APE requests.
	 *
	 * @access public
	 * @return string request URL
	 * @since  Available since release 0.1.0.
	 */	      
    public function getUrl(){ 
    	return $this->getBaseUrl().':'.$this->port.'/?';
    }

	/**
	 * sends a request to the APE server and returns result.
	 *
	 * @access public
	 * @param  $request ApeRequest the request to send to the APE server
	 * @return ApeResponse response Object
	 * @since  Available since release 0.1.0.
	 */	    
    public function sendRequest(ApeRequest $request){
    	try {
    		$res = $this->doSendRequest($request);
    	}
    	catch(Exception $e){
    		return NULL;
    	}   	
    	if($res){   		
    		return new ApeResponse($res);   		
    	}
    	return NULL;
    }
    
	/**
	 * Sends a request to the APE server and returns result.
	 * Must be implemented by concrete classes.
	 *
	 * @abstract
	 * @access public
	 * @param  $request ApeRequest the request to send to the APE server
	 * @return ApeResponse response Object
	 * @since  Available since release 0.1.0.
	 */	    
    abstract protected function doSendRequest(ApeRequest $request);

	/**
	 * Checks wether connection service is available.
	 * Must be implemented by concrete classes.
	 *
	 * @abstract
	 * @static
	 * @access public
	 * @return boolean connection service is available?
	 * @since  Available since release 0.1.0.
	 */	    
    abstract public static function available();
} 