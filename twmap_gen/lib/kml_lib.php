<?
// $Id: kml_lib.php 125 2010-12-24 03:19:07Z happyman $
//function t67to97($x,$y) {
//  $A= 0.00001549; 
//  $B= 0.000006521;
//  return array($x + 807.8 + $A * $x + $B * $y, $y - 248.6 + $A * $y + $B * $y);
//}

function Map2GE($x,$y) {

if (0) {
  $SHM_KEY = ftok(__FILE__, chr( 4 ) );
  $data =  shm_attach($SHM_KEY, 102400, 0666);
  $result=shm_get_var($data, 1);
  $r=$result[$x][$y];
  if (isset($r)) {
   shm_detach($data);
  return $r;
  }
}

// ­×¥¿
$r=t67to97($x,$y);
$x=$r[0]; $y=$r[1];

$proj="proj -I +proj=tmerc +ellps=aust_SA +lon_0=121 +x_0=250000 +k=0.9999";
$ret=shell_exec("echo $x $y | $proj");

if (preg_match("/(\d+)d(\d+)'([\d.]+)\"E\s+(\d+)d(\d+)'([\d.]+)\"N/", $ret, $matches)) {
  list ($junk, $ed, $em, $es, $nd, $nm, $ns) = $matches;
  $r[0] = $ed + $em / 60 + $es / 3600;
  $r[1] = $nd + $nm / 60 + $ns / 3600;
if (0) {
  $result[$x][$y]=$r;
  shm_put_var($data,1,$result);
  shm_detach($data);
}
return $r;
}
return FALSE;
// exit;
}

function Placemark_points($name,$r,$cdata) {
global $cdata;
?>
<Placemark>
  <description>
<![CDATA[ <?=$cdata ?> ]]> 
  </description>
  <name><?=$name?></name>
  <LookAt>
    <longitude><?=$r[0]?></longitude>
    <latitude><?=$r[1]?></latitude>
    <range>6000</range>
    <tilt>60</tilt>
    <heading>0</heading>
  </LookAt>
<Point>
<coordinates><?=$r[0]?>,<?=$r[1]?>,3000</coordinates>
</Point>
</Placemark>
<? 
} 
?>
