<?php

$debug = 0;

$xmlstring = file_get_contents("onlinemapsources.xml.UNSORT");
$xml=simplexml_load_string($xmlstring, "SimpleXMLElement", LIBXML_NOCDATA);

$json = json_encode($xml);
$array = json_decode($json,TRUE);

$urls=array();
$i=100;
$tw=6000;
foreach($array['onlinemapsource'] as $data) {
	$data['url'] = trim($data['url']);
	if (isset($urls[$data['url']])) {
	//	echo "dup! ".$data['url']. "=>" . $urls[$data['url']] . "\n";
	if ($debug)	printf("<!-- dup %s %s %d and %d-->\n",$data['url'],$data['name'],$urls[$data['url']], $data['@attributes']['uid'][0]);


	} else {
		
		$urls[$data['url']] = $data['@attributes']['uid'][0];
		if (strstr($data['name'],'(TW)'))
			$data['@attributes']['new_uid'] = $tw++;
		else
			$data['@attributes']['new_uid'] = $i++;
		$result[] = $data;
	}
    
}

if ($debug) print_r($result);
$line='<?xml version="1.0" encoding="utf-8"?>'."\n<onlinemapsources>\n";
$line.="<!-- 製造日期: " . date('Y-m-d H:i:s') . " by 地圖產生器 -->\n";

foreach($result as $data) {
	foreach($data as $k => $v) {
		if ($k == '@attributes') 
			$line .= sprintf('<onlinemapsource uid="%d">%s',$v['new_uid'],"\n");
		else if ($k == 'httpparam') {
			// skip
		} else {
			if ($v === 0 || !empty($v)){
				if (is_array($v)) {
						// httparam skip
				} else {
					$line.= sprintf('%s<%s>%s</%s>%s',"\t",$k,escapexml($v),$k,"\n");
				}
			}
		}
	}
	$line.="</onlinemapsource>\n";
	
}
$line .= "</onlinemapsources>";
echo $line;

function escapexml($string){
	return htmlspecialchars($string, ENT_XML1);
}
