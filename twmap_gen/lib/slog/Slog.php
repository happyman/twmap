<?php

	/**
	 * A simple logging class 
	 * @author Federico Stange 
	 */

	namespace stange\logging{

		use \stange\logging\slog\iface\Log as LogObjectInterface;	

		class Slog implements LogObjectInterface{

			/**
			 * Contains a default ANSI set of terminal colors
			 * @var Array $colors
			 */

			private $colors = Array(
											"black"		=>	"\33[0;30m",
											"blue"		=>	"\33[0;34m",
											"lblue"		=>	"\33[1;34m",
											"green"		=>	"\33[0;32m",
											"lgreen"		=>	"\33[1;32m",
											"cyan"		=>	"\33[0;36m",
											"lcyan"		=>	"\33[1;36m",
											"red"			=>	"\33[0;31m",
											"lred"		=>	"\33[0;31m",
											"purple"		=>	"\33[0;35m",
											"lpurple"	=>	"\33[1;35m",
											"brown"		=>	"\33[0;33m",
											"gray"		=>	"\33[1;30m",
											"lgray"		=>	"\33[0;37m",
											"yellow"		=>	"\33[1;33m",
											"white"		=>	"\33[1;37m"
			);

			/**
			 * Prepend the date for every messaged logged
			 * @var $useDate
			 */
		
			private $useDate = False;

			/**
			 * Date format used when logging dates
			 * @var $dateFormat
			 */

			private $dateFormat	=	'[ d/m/Y H:i:s ]';

			 /**
			  * Variable that stores a "cached" PHP date time object
			  * @var objDate
			  */

			private $objDate	=	NULL;
	
			/**
			 * @var $file String name of log file
			 * @see self::setFile($filename)
			 * @see self::__construct
			 */
	
			private  $file		=	NULL;

			/**
			 * @var $fp File pointer
			 * @see self::setFile($filename)
			 * @see self::__construct
			 */

			private	$fp		=	NULL;

			/**
			 * @var $fp File pointer mode
			 * @see self::setFile($filename)
			 * @see self::__construct
			 */

			private	$fpMode	=	NULL;
	
			/**
			*
			* @var $echo print to stdout or not
			* @see self::setEcho()
			*
			*/
	
			private $echo		=	NULL;
	
			/**
			* @var $prepend Adds a static string to every message *before* the message
			* @see self::setPrepend()
			*/
	
			private $prepend	=	NULL;
	
			/**
			* @var $append Adds a static string to every message *after* the message
			* @see self::setAppend()
			*/
	
			private $append	=	NULL;

			/** 
			 * Colorize output 
			 * @var boolean colorize
			 */

			private $colorize	=	TRUE;

			/**
			 * @var string $lineCharacter it's the character that outputs at the end of a message, by default it's PHP_EOL
			 * @see self::setCarriageReturnChar
			 */

			private	$lineCharacter	=	\PHP_EOL;

			/**
			 * String delimiter which identifies if a message is associated to a certain tag or not
			 *
			 * @var string $tag
			 */

			private	$tag	=	NULL;

			/**
			 * Log Tags
			 * @var Array $tags
			 */

			private	$tags		=	Array();


			public function __construct(Array $params=Array()){

				if(isset($params['file'])){

					$this->setFile(
										$params['file'],
										isset($params['fmode']) ? $params['fmode'] : 'a+'
					);

				}

				//Set echo by default if no echo argument is passed in
				$this->setEcho(isset($params['echo']) ? (boolean)$params['echo'] : TRUE);

				if(isset($params['date'])){

					$this->useDate(TRUE,$params['date']);

				}

				if(isset($params['prepend'])){

					$this->setPrepend($params['prepend']);

				}

				if(isset($params['append'])){

					$this->setAppend($params['append']);

				}

				if(isset($params['colors'])){

					$this->useColors($params['colors']);

				}

				if(isset($params['crchar'])){

					$this->setCarriageReturnChar($params['crchar']);

				}

				if(isset($params['tagId'])){

					$this->setTagId($params['tagId']);

				}

				if(isset($params['tags'])){

					$this->addTag($params['tags']);

				}

			}

			public function addTag($tag){

				if(!is_array($tag)){

					$tag	=	Array($tag);

				}

				foreach($tag as $l){

					$this->tags[$l]	=	TRUE;

				}

				return $this;

			}

			public function unsetTags(){

				$this->tags	=	Array();
				return $this;

			}

			public function getTags(){

				return $this->tags;	

			}

			public function removeTag($tag){

				if(in_array($tag,array_keys($this->tags))){

					unset($this->tags[$tag]);

				}

				return $this;

			}

			public function setFile($name,$mode='a+'){

				$this->file		=	$name;
				$this->fpMode	=	$mode;
				return $this;

			}

			public function getFile(){

				return $this->file;

			}

			/**
			*Specifies the current logging date should be prepended in the log file
			*@param boolean $boolean TRUE prepend date
			*@param boolean $boolean FALSE do NOT prepend date
			*/

			public function useDate($boolean,$format='[d/m/Y H:i:s]'){

				$this->useDate		=	$boolean;
				$format				=	trim($format);

				if(!$this->useDate){

					$this->objDate	=	NULL;
					return;

				}

				$date = new \DateTime('now');

				if(!$date->format($format)){

					throw new \InvalidArgumentException("Wrong date format");

				}

				$this->dateFormat	=	$format;

				$this->objDate	=	$date;

				return $this;

			}

			/**
			 * Set the line terminator character, by default we use the platform independent
			 * constant \PHP_EOL, however if you are trying to make a log for a different platform
			 * you might change it to something else, i.e: \r\n
			 */

			public function setCarriageReturnChar($char=\PHP_EOL){

				$this->lineCharacter	=	$char;
				return $this;

			}

			/**
			 * Get a color from the color array
			 * @throws \InvalidArgumentException if the color is not found
			 * @return string ANSI color string
			 */

			private function getColor($color){

				$color	=	strtolower($color);

				if(!in_array(strtolower($color),array_keys($this->colors))) {

					throw new \Exception("Invalid color specified when trying to log \"$msg\"");

				}

				return $this->colors[$color];

			}

			/** Get the formatted date string **/

			private function getLogDateString(){

				if(!$this->objDate){

					return '';

				}

				return $this->objDate->format($this->dateFormat);

			}

			/** Create an undecorated logging message (no colors) **/

			private function createLogMessage($msg){

				if(!is_string($msg)){

					$msg	=	var_export($msg,TRUE);

				}

				$date	=	$this->getLogDateString();

				$msg	=	sprintf(
										'%s%s%s%s%s',
										$this->prepend,
										$date ? "$date " : '',
										$msg,
										$this->append,
										$this->lineCharacter
				);

				return $msg;

			}

			public function useColors($boolean){

				$this->colorize	=	(boolean)$boolean;
				return $this;

			}

			private function colorize($color,$msg){

				if(!$this->colorize){

					return $msg;

				}

				return sprintf('%s%s%s',$this->getColor($color),$msg,"\e[0m");

			}

			private function toFile($msg){

				if(!$this->file){

					return;

				}

				if(!$this->fp){

					$this->fp	=	fopen($this->file,$this->fpMode);

				}

				return fwrite($this->fp,$msg);

			}

			public function setTagId($tag){

				$this->tag	=	$tag;
				return $this;

			}

			public function getTagId(){

				return $this->tag;

			}

			private function parseTag($msg){

				if(!$this->tag){

					return $msg;

				}

				$tagPos	=	strpos($msg,$this->tag);

				if(empty($this->tags)){

					return $tagPos===FALSE ? $msg : substr($msg,$tagPos+strlen($this->tag));

				}

				//No tag found 

				if($tagPos === FALSE){

					return $msg;

				}

				$tag	=	substr($msg,0,$tagPos);

				if(isset($this->tags[$tag])){

					return substr($msg,$tagPos+strlen($this->tag));

				}

				return NULL;

			}

			public function log($text,$color=NULL){

				$msg	=	$this->parseTag($text);

				if($msg===NULL){

					return '';

				}

				$msg	=	$this->createLogMessage($msg);

				$this->toFile($msg);

				if($color){

					$msg	=	$this->colorize($color,$msg);

				}

				if($this->echo){

					echo $msg;

				}

				return $msg;

			}

			public function debug($text=NULL){

				return $this->log("[DD] $text",'lpurple');	

			}

			public function info($text=NULL){

				return $this->log("[II] $text",'lcyan');	

			}

			public function warning($text=NULL){

				return $this->log("[WW] $text",'yellow');	

			}

			public function error($text=NULL){

				return $this->log("[EE] $text",'red');	

			}

			public function emergency($text=NULL){

				return $this->log("[!!] $text",'red');	

			}

			public function success($text=NULL){

				return $this->log("[SS] $text",'lgreen');	
				
			}

			public function alert($text=NULL){

				return $this->log("[**] $text",'alert');	
			
			}
	
			/**
			 * Specifies that each logged message should be echoed to stdout.
			 * 
			 * @method setEcho() 
			 * @param $echo bool TRUE output to stdout
			 * @param $echo bool FALSE Do NOT output to stdout
			 */
	
			public function setEcho($echo=TRUE) {
	
				$this->echo = $echo;
				return $this;
	
			}

			/**
			 * @method getEcho() 
			 */

			public function getEcho(){

				return $this->echo;

			}
	
			/**
			 *@method setPrepend() Prepends a string to every logged message
			 *@param String The string to be prepend
			 */
	
			public function setPrepend($prepend=NULL) {
	
				$this->prepend = $prepend;
				return $this;
	
			}
	
			public function getPrepend(){
	
				return $this->prepend;
	
			}

			/**
			 * @method setAppend() Adds a string at the end of every logged message
			 * @param string El string a posponer en el mensaje log
			 *
			 */
	
			public function setAppend($append=NULL) {
	
				$this->append = $append;

				return $this;
	
			}
	
			public function getAppend(){
	
				return $this->append;
		
			}

			public function __destruct(){

				if($this->fp){

					fclose($this->fp);

				}

			}
	
		}

	}
