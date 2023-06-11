<?php

	/**
	 * This logging trait might come in handy if you want to make your classes 
	 * perform any kind of internal logging.
	 */

	namespace stange\logging\slog\traits{

		use \stange\logging\slog\iface\Log as LogObjectInterface;	

		trait Loggable{

			private	$log						=	NULL;

			public function setLog(LogObjectInterface $log){

				$this->log	=	$log;

				return $this;

			}

			public function getLog(){

				return $this->log;

			}

			public function log($msg,$type=NULL){

				if(!$this->log){

					return;

				}

				$prep	=	$this->log->getPrepend();

				$type = $type ? $type : 'log';

				switch($type){

					case 'info':
					case 'error':
					case 'emergency':
					case 'debug':
					case 'success':
					case 'log':

						$this->log->setPrepend(sprintf('{%s} ',__CLASS__));
						$result	=	$this->log->$type($msg);
						$this->log->setPrepend(NULL);

						return $result;

					break;

					default:
						throw new \BadMethodCallException("Invalid logging method called: \"$type\"");
					break;

				}

			}

		}

	}
