<?php
//http://abhinavsingh.com/blog/2010/02/memq-fast-queue-implementation-using-memcached-and-php-only/

// 
require_once( dirname(__FILE__) . "/../config.inc.php");
// move the following line to config
//define('MEMQ_POOL', '127.0.0.1:11211');
//define('MEMQ_TTL', 0);

class MEMQ {

	private static $mem = NULL;

	private function __construct() {}

	private function __clone() {}

	private static function getInstance() {
		if(!self::$mem) self::init();
		return self::$mem;
	}

	private static function init() {
		$mem = new Memcached;
		$servers = explode(",", MEMQ_POOL);
		foreach($servers as $server) {
			list($host, $port) = explode(":", $server);
			$mem->addServer($host, $port);
		}
		self::$mem = $mem;
	}

	public static function is_empty($queue) {
		$mem = self::getInstance();
		$head = $mem->get($queue."_head");
		$tail = $mem->get($queue."_tail");

		if($head >= $tail || $head === FALSE || $tail === FALSE)
			return TRUE;
		else
			return FALSE;
	}

	public static function dequeue($queue, $after_id=FALSE, $till_id=FALSE) {
		$mem = self::getInstance();

		if($after_id === FALSE && $till_id === FALSE) {
			$tail = $mem->get($queue."_tail");
			if(($id = $mem->increment($queue."_head")) === FALSE)
				return FALSE;

			if($id <= $tail) {
				return $mem->get($queue."_".($id-1));
			}
			else {
				$mem->decrement($queue."_head");
				return FALSE;
			}
		}
		else if($after_id !== FALSE && $till_id === FALSE) {
			$till_id = $mem->get($queue."_tail");
		}

		$item_keys = array();
		for($i=$after_id+1; $i<=$till_id; $i++)
			$item_keys[] = $queue."_".$i;
		$null = NULL;

		return $mem->getMulti($item_keys, $null, Memcached::GET_PRESERVE_ORDER);
	}

	public static function listqueue($queue) {
		$mem = self::getInstance();
		$tail = $mem->get($queue."_tail");
		$head = $mem->get($queue."_head");
		//return self::dequeue($queue, $head, $tail);
		$item_keys = array();
		for($i=$head+1; $i<=$tail; $i++)
			$item_keys[] = $queue."_".$i;
		$null = NULL;

		return $mem->getMulti($item_keys, $null, Memcached::GET_PRESERVE_ORDER);

	}
	public static function enqueue($queue, $item) {
		$mem = self::getInstance();

		$id = $mem->increment($queue."_tail");
		if($id === FALSE) {
			if($mem->add($queue."_tail", 1, MEMQ_TTL) === FALSE) {
				$id = $mem->increment($queue."_tail");
				if($id === FALSE)
					return FALSE;
			}
			else {
				$id = 1;
				$mem->add($queue."_head", $id, MEMQ_TTL);
			}
		}

		if($mem->add($queue."_".$id, $item, MEMQ_TTL) === FALSE)
			return FALSE;

		return $id;
	}

}

