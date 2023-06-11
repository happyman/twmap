<?php

	namespace stange\logging\slog\iface{

		interface Log{

			public function info($msg);
			public function warning($msg);
			public function error($msg);
			public function success($msg);
			public function emergency($msg);
			public function debug($msg);

			public function setPrepend($prepend=NULL);
			public function getPrepend();


		}

	}
