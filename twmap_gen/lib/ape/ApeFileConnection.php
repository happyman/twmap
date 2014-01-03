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



class ApeFileConnection extends ApeAbstractConnection {


	
	protected function doSendRequest(ApeRequest $request){
		$data = $request->getUrlString();
		try {	 
			$res = @file_get_contents($this->getUrl().$data);
		}catch(Exception $e){ 
			throw new ApeException('Could not create Response: '.$e->getMessage());
		}
		return $res;		
	}

	/**
	 * Checks wether file (fopen) connection service is available.
	 * implementation for parent´s abstract method.
	 *
	 * @static
	 * @access public
	 * @return boolean connection service is available?
	 * @since  Available since release 0.1.0.
	 */	  	
	public static function available(){
		return (bool)ini_get('allow_url_fopen');
	}

}