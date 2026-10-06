<?php

// phpunit bootstrap: 載入主設定 (CLI 模式, 不會碰 smarty/session)
require __DIR__ . '/../config.inc.php';

/*
 * PHPUnit 可能在函式範圍載入 bootstrap, 這時 config.inc.php 定義的變數會落在
 * 該函式的 local scope, 變不成真正的 global, 於是 map_roots() / map_fs_root()
 * 這類 `global $x` 讀不到。這裡把它們明確提昇到 $GLOBALS。
 */
$__vars = get_defined_vars();
unset($__vars['__vars']);
$__skip = ['GLOBALS', '_SERVER', '_GET', '_POST', '_FILES', '_COOKIE', '_SESSION', '_REQUEST', '_ENV'];
foreach ($__vars as $__k => $__v) {
	if (strncmp($__k, '_', 1) === 0) continue;
	if (in_array($__k, $__skip, true)) continue;
	$GLOBALS[$__k] = $__v;
}
unset($__vars, $__k, $__v, $__skip);
