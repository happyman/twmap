<?php

	namespace stange\logging\slog\iface{

		use \stange\logging\slog\iface\Log	as	LogObjectInterface;

		interface Loggable{

			public function setLog(LogObjectInterface $log);
			public function getLog();
			public function log($msg,$type=NULL);

		}

	}
