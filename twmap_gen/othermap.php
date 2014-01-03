<?php
require_once("config.inc.php");
$align=(isset($_GET['align']))?$_GET['align']: "center";
$target=(isset($_GET['target']))?$_GET['target']: "_blank";
?>
	<div align="<?php echo $align; ?>">
<table width=600px>
<tr><td valign=top>
<?php
  echo hot_block(2,$target);
?>
<td valign=top>
<?php
  echo hot_block(1,$target);
?>
</table>
</div>
