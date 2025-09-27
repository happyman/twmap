<?php
Namespace Happyman\Twmap\Export;


class Geotiff {
    var $startx, $starty; //輸入的參數
	var $shiftx, $shifty; //輸入的參數
	var $ph, $version;
    var $err;
	var $datum = 'TWD97';
    var $logger = null;
    static function check(){
		$req=[ 'gdal_translate' => ['package'=>'gdal-bin', 'test'=> '-h']];

		$err=0;
		$classname=get_called_class();
		foreach($req as $bin=>$meta){
			$cmd=sprintf("%s %s",$bin,$meta['test']);
			exec($cmd,$out,$ret);
			if ($ret!=0){
				printf("[%s] %s not installed, please install %s",$classname,$bin,$meta['package']);
				if (!isset($meta['optional']))
					$err++;
			}else{
				printf("[%s] %s installed %s\n",$classname,$bin,isset($meta['optional'])?"(optional)":"");
			}
		}
		if ($err>0)
			return false;
		else
			return true;
	}
    function __construct($options) {
		if (!isset($options['startx']) || !isset($options['starty']) ||!isset($options['shiftx']) ||!isset($options['starty'])){
			$this->err[] = "Not enough parameters";
			return false;
		}
		$this->startx = $options['startx'];
		$this->starty = $options['starty'];
		$this->shiftx = $options['shiftx'];
		$this->shifty = $options['shifty'];
		if (isset($options['ph']))
			$this->ph = $options['ph'];
		if (isset($options['datum']))
			$this->datum = $options['datum'];
		if (isset($options['version']))
			$this->version = $options['version'];
        if (isset($options['logger']))
			$this->logger = $options['logger'];
		
		return TRUE;
    }
    function out($input){
		list ($tl_lon,$tl_lat,$br_lon,$br_lat)=$this->get_bound();
		$outimg=str_replace(".png",".tiff",$input);
		$cmd=sprintf("gdal_translate -of GTiff -a_ullr %s %s %s %s -a_srs EPSG:4326 -co COMPRESS=LZW %s %s",
			$tl_lon, $tl_lat, $br_lon, $br_lat, $input,$outimg );
		if ($this->logger != null)
			$this->logger->info($cmd);
		exec($cmd,$out,$ret);
		if ($this->logger != null)
			$this->logger->info("create $outimg returns $ret");
		return $ret;
	}
    function get_bound(){
		$x = $this->startx * 1000;
		$y = $this->starty * 1000;

		$x1 = $x + $this->shiftx * 1000;
		$y1 = $y - $this->shifty * 1000;

		if ($this->datum == 'TWD97'){
			$proj_func = "Happyman\Twmap\Proj::proj_97toge2";
			$ph_proj_func = "Happyman\Twmap\Proj::ph_proj_97toge2";
		}	else {
			$proj_func = "Happyman\Twmap\Proj::proj_67toge2";
			$ph_proj_func = "Happyman\Twmap\Proj::ph_proj_67toge2";
		}
		
		if ($this->ph == 0 ) {
			// 台灣本島 proj_67toge2 使用 cs2cs 把 67 轉 97
			list ($tl_lon,$tl_lat) = $proj_func(array($x,$y));
			list ($br_lon,$br_lat) = $proj_func(array($x1,$y1));
		} else {
			list ($tl_lon,$tl_lat) = $ph_proj_func(array($x,$y));
			list ($br_lon,$br_lat) = $ph_proj_func(array($x1,$y1));
		}
		return array($tl_lon,$tl_lat,$br_lon,$br_lat);
	}
}
