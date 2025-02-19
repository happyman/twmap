<?php


$csv = $argv[1];
$rpb_type = "警察駐在所";
$comment='1913-1941年';

$fp=fopen($argv[1],"r");
$row = 1;
$pb=array();
if (($handle = fopen($csv, "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if (count($data) < 10) continue;
        // convert to sql
        // Feature,1,九芎湖駐在所（九芎湖駐在所）,宜蘭縣三星鄉,121.606386,24.65635,150,Point,121.606386,24.65635
        // x      ,x, 2 要, x, 4, 5, 6
        if (preg_match("/(\S+)（(\S+)\）/",$data[2],$mat)){
            if ($data[6] == '' ) $data[6] = 'NULL';
            // dedup
            if (isset($pb[$mat[1]])){
                printf("-- %s DUP->",$mat[1]);
                $mat[1].= "(". $data[3] . ")";
                printf("%s \n",$mat[1]);
            }
            else
                $pb[$mat[1]] = 1;
            if ($mat[1] == $mat[2]) {
                $name = str_replace("駐在所",$rpb_type,$mat[1]);
                printf("INSERT INTO point3 (name,type,class,coord,ele,comment) VALUES ( '%s', '%s','0', ST_GeomFromText('POINT(%f %f)', 4326), %s,'%s');\n", $name,$rpb_type, $data[4],$data[5],$data[6],$comment);
            } else{
                $name = str_replace("駐在所",$rpb_type,$mat[1]);
                $alias = str_replace("駐在所",$rpb_type,$mat[2]);
                 //printf("%s %s %f %f %d\n",$mat[1],$mat[2],$data[4],$data[5],$data[6]);
                printf("INSERT INTO point3 (name,alias,type,class,coord,ele,comment) VALUES ( '%s','%s', '%s','0', ST_GeomFromText('POINT(%f %f)', 4326), %s, '%s');\n", $name,$alias, $rpb_type,$data[4],$data[5],$data[6],$comment);
            }
        }
        
        $row++;
    }
    fclose($handle);
}
