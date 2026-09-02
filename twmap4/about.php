<?php
session_start(['read_and_close' => true]);
require_once(__DIR__ . "/lib/functions.inc.php");
require_once(__DIR__ . "/config.inc.php");
list($st, $info) = login_info();

if ($st === false) {
  $greetings = "歡迎光臨";
  $login_link = sprintf(
    '<a href="%s" target="_top">登入</a>',
    $CONFIG['site_twmap_html_root'] . "main.php?return=twmap4"
  );
  $role_text = "";
} else {
  $greetings = sprintf(
    "歡迎 %s <img src='%s' title='uid=%d' style='height:20px;vertical-align:middle;' />",
    $info['user_nickname'], $info['user_icon'], $_SESSION['uid']
  );
  $login_link = sprintf(
    '<a href="%s" target="_top">登出</a>',
    $CONFIG['site_twmap_html_root'] . "logout.php"
  );
  if (is_admin()) {
    $role_text = sprintf("管理者 (%d) (%s)", $_SESSION['uid'], php_uname('n'));
  } else {
    $role_text = sprintf("使用者 (%d)", $_SESSION['uid']);
  }
}

$ver = @trim(file_get_contents(__DIR__ . '/VERSION'));
$login_role = ($st === true) ? 1 : 0;
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <title>關於地圖瀏覽器 v<?= $ver ?></title>
  <style>
    body { font-family: Arial, "Microsoft JhengHei", sans-serif; max-width: 600px; margin: 2rem auto; line-height: 1.6; color: #0f172a; }
    h2 { text-align: center; }
    .user-bar { text-align: right; font-size: 13px; margin-bottom: 0.5rem; }
    .user-bar a { color: #0369a1; text-decoration: none; }
    .user-bar a:hover { text-decoration: underline; }
    .role { color: #475569; font-size: 12px; }
    .instructions { margin: 1rem 0; padding: 1rem; background: #f3f4f6; border-radius: 6px; }
    .instructions li { margin-bottom: 0.5rem; }
  </style>
  <script>
    var login_role = <?= $login_role ?>;
    <?php if ($st === true): ?>
    var login_uid = <?= $_SESSION['uid'] ?>;
    <?php endif; ?>
    document.addEventListener('DOMContentLoaded', function () {
      if (window.parent && window.parent !== window && typeof window.parent.toggle_user_role === 'function') {
        window.parent.toggle_user_role(login_role);
      }
    });
  </script>
</head>
<body>
  <div class="user-bar">
    <?= $login_link ?>
    <?php if ($role_text): ?>
      <span class="role"><?= $role_text ?></span>
    <?php endif; ?>
  </div>

  <h2><?= $greetings ?></h2>

  <div class="instructions">
    <strong>功能說明：</strong>
    <ul>
      <li>搜尋山頭、地標或座標：輸入關鍵字後點「到」或按 Enter</li>
      <li>繪製形狀：使用繪圖工具（多邊形/圓形/線段/矩形）</li>
      <li>全螢幕：點右上方全螢幕圖示進入/離開全螢幕模式</li>
      <li>右鍵功能：選取形狀後可右鍵匯出 KML</li>
      <li>地圖捲動：使用滑鼠捲輪或拖曳移動畫面</li>
      <li>測量：選取起點後選終點，顯示距離/方向角</li>
      <li>GPX：可拖曳 GPX 檔案到地圖上顯示</li>
    </ul>

    <strong>資訊：</strong>
    <ul>
      <li>使用 OpenLayers 10.3.1 + OpenStreetMap 圖層</li>
      <li>座標參考：TWD67 / TWD97</li>
      <li>版本：v<?= $ver ?></li>
      <?php if ($st === true): ?>
      <li>上載行跡：<a href="<?= $CONFIG['site_twmap_html_root'] ?>api/uploadpage.php">上載 GPX</a></li>
      <?php endif; ?>
    </ul>
  </div>
</body>
</html>
