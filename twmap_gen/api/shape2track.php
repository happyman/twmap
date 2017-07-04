<?php

// Usage:
//     shape2track.php?type=kml
//
// Method: HTTP POST
//
// Post data:
//     JSON string of shapes created with shape-save by Mac Craven:
//     http://expertsoftwareengineer.com/includes/google-maps/shape-save-demo-code.php
//
//     Please refer to variable $input in this file for correct format
//
// Arguments:
//     type=[gpx|kml] (optional, default=gpx)
//     dev=[0|1] (Dor development only. When dev=1, xml is printed instead of downloaded.)

header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time() + (60 * 60 * 24 * 1))); // 1 day browser cache

// $type = 'gpx'; // Supports [ gpx | kml ]
$dev = isset($_REQUEST['dev']) ? $_REQUEST['dev'] : 0;
$type = isset($_REQUEST['type']) ? $_REQUEST['type'] : 'gpx';

if ( $dev ) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    header("Content-Type: application/json; charset=utf-8");
}

define('ROOT3HALF', 0.86602540378);

// ---------- Codes for proj4php; remove if other lib is used
// Use a PSR-4 autoloader for the `proj4php` root namespace.
include("./proj4php-master/vendor/autoload.php");
use proj4php\Proj4php;
use proj4php\Proj;
use proj4php\Point;
// Initialise Proj4
$proj4 = new Proj4php();
// Create two different projections.
$projLatLon = new Proj('EPSG:4326', $proj4);
$projUTM    = new Proj('EPSG:3857', $proj4);
// ---------- End of proj4php codes

// Default value for $input is for testing purpose
$input = '{
            "shapes": [
                {
                    "type": "rectangle",
                    "color": "undefined",
                    "bounds": {
                        "northEast": {
                            "lat": "23.886033898811526",
                            "lon": "121.48956298828125"
                        },
                        "southWest": {
                            "lat": "23.879833868168703",
                            "lon": "121.48754596710205"
                        }
                    }
                },
                {
                    "type": "polyline",
                    "color": "#000000",
                    "path": [
                        {
                            "lat": "23.8858769396757",
                            "lon": "121.51381015777588"
                        },
                        {
                            "lat": "23.886661733450623",
                            "lon": "121.5111494064331"
                        },
                        {
                            "lat": "23.88642629581799",
                            "lon": "121.50866031646729"
                        },
                        {
                            "lat": "23.885837699862",
                            "lon": "121.50612831115723"
                        },
                        {
                            "lat": "23.884856700651575",
                            "lon": "121.50359630584717"
                        },
                        {
                            "lat": "23.881168077062338",
                            "lon": "121.49951934814453"
                        },
                        {
                            "lat": "23.87955917639489",
                            "lon": "121.49810314178467"
                        },
                        {
                            "lat": "23.876694498867913",
                            "lon": "121.49655818939209"
                        },
                        {
                            "lat": "23.874418408510614",
                            "lon": "121.49346828460693"
                        },
                        {
                            "lat": "23.870572509729143",
                            "lon": "121.49145126342773"
                        },
                        {
                            "lat": "23.868963477363206",
                            "lon": "121.48921966552734"
                        },
                        {
                            "lat": "23.867903859763896",
                            "lon": "121.48557186126709"
                        },
                        {
                            "lat": "23.867629142674588",
                            "lon": "121.48261070251465"
                        },
                        {
                            "lat": "23.86959139481181",
                            "lon": "121.48205280303955"
                        },
                        {
                            "lat": "23.87261320494276",
                            "lon": "121.48205280303955"
                        },
                        {
                            "lat": "23.875713430345595",
                            "lon": "121.48153781890869"
                        },
                        {
                            "lat": "23.87704768171564",
                            "lon": "121.48106575012207"
                        },
                        {
                            "lat": "23.8796769015122",
                            "lon": "121.47707462310791"
                        },
                        {
                            "lat": "23.880422491435013",
                            "lon": "121.47171020507812"
                        },
                        {
                            "lat": "23.880108559359492",
                            "lon": "121.46810531616211"
                        },
                        {
                            "lat": "23.878264193036777",
                            "lon": "121.46544456481934"
                        },
                        {
                            "lat": "23.87787177127924",
                            "lon": "121.46063804626465"
                        },
                        {
                            "lat": "23.879755384864254",
                            "lon": "121.45763397216797"
                        },
                        {
                            "lat": "23.882109863293692",
                            "lon": "121.45561695098877"
                        },
                        {
                            "lat": "23.88360101080893",
                            "lon": "121.45471572875977"
                        }
                    ]
                },
                {
                    "type": "polygon",
                    "color": "undefined",
                    "paths": [
                        {
                            "path": [
                                {
                                    "lat": "23.888270545806524",
                                    "lon": "121.46814823150635"
                                },
                                {
                                    "lat": "23.882816198469186",
                                    "lon": "121.45668983459473"
                                },
                                {
                                    "lat": "23.880265525492447",
                                    "lon": "121.46407127380371"
                                },
                                {
                                    "lat": "23.882109863293692",
                                    "lon": "121.46853446960449"
                                }
                            ]
                        }
                    ]
                },
                {
                    "type": "circle",
                    "color": "undefined",
                    "center": [
                        {
                            "lat": "23.86983",
                            "lon": "121.49164",
                            "radius": "2405.94200738791307"
                        },
                        {
                            "lat": "23.88775",
                            "lon": "121.48337",
                            "radius": "605.94200738791307"
                        }
                    ]
                }
            ]
        }';
        
if ( isset($_POST) && sizeof($_POST) > 0 ) {
    $input = $_POST['data'];
} else {
    // Get posted data
    $reset_json = file_get_contents( 'php://input' );
    if ( isset($reset_json) && sizeof($reset_json) > 1 ) {
        $input = $reset_json;
        if ( $dev ) echo 'POST'.PHP_EOL;
    }
}

$valid = false;
$xml = null;
        
$jsonShapes = json_decode($input);
//echo var_dump( $jsonShapes );
if ( property_exists ($jsonShapes, 'shapes') && sizeof($jsonShapes->shapes) > 0 ) {
        
    // Create xml document object
    if ( $type==='kml' ) {
        $filename = 'shapes.kml';
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?>'
                              .'<kml xmlns="http://www.opengis.net/kml/2.2">'
                              .'</kml>');
        $meta = $xml->addChild('metadata');
    } else {
        $filename = 'shapes.gpx';
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" standalone="no" ?>'
                              .'<gpx xmlns="http://www.topografix.com/GPX/1/1">'
                              .'</gpx>');
        $meta = $xml->addChild('metadata');
    }
    
    // Convert shapes to xml...
    foreach ( $jsonShapes->shapes as $index => $shape ) {
        //echo var_dump( $shape );
        if ( $dev ) echo $shape->type.PHP_EOL;
        $shapename = $index.': '.$shape->type;
        if ( $shape->type==='rectangle' ) {
            if ( property_exists($shape, 'bounds') && property_exists($shape->bounds, 'northEast') && property_exists($shape->bounds, 'southWest') ) {
                //if ( $dev ) echo var_dump($shape->bounds).PHP_EOL;
                
                $minlat = $shape->bounds->northEast->lat;
                $minlon = $shape->bounds->northEast->lon;
                $maxlat = $shape->bounds->southWest->lat;
                $maxlon = $shape->bounds->southWest->lon;
                if ( $minlat > $maxlat ) {
                    $tmp = $minlat;
                    $minlat = $maxlat;
                    $maxlat = $tmp;
                }
                if ( $minlon > $maxlon ) {
                    $tmp = $minlon;
                    $minlon = $maxlon;
                    $maxlon = $tmp;
                }
                
                $valid = true;
                
                if ( $type==='kml' ) {
                    $Placemark = $xml->addChild('Placemark');
                    $name = $Placemark->addChild('name', $shapename);
                    $Polygon = $Placemark->addChild('Polygon');
                    $outerBoundaryIs = $Polygon->addChild('outerBoundaryIs');
                    $LinearRing = $outerBoundaryIs->addChild('LinearRing');
                    $coordinatesstr = '';
                    $coordinatesstr .= sprintf( '%f,%f,0 ', $minlon, $minlat );
                    $coordinatesstr .= sprintf( '%f,%f,0 ', $maxlon, $minlat );
                    $coordinatesstr .= sprintf( '%f,%f,0 ', $maxlon, $maxlat );
                    $coordinatesstr .= sprintf( '%f,%f,0 ', $minlon, $maxlat );
                    $coordinatesstr .= sprintf( '%f,%f,0 ', $minlon, $minlat );
                    $coordinates = $LinearRing->addChild('coordinates', $coordinatesstr);
                } else {
                    $trk = $xml->addChild('trk');
                    $name = $trk->addChild('name', $shapename);
                    $trkseg = $trk->addChild('trkseg');
                    $trkpt = $trkseg->addChild('trkpt');
                    $trkpt->addAttribute('lat', $minlat);
                    $trkpt->addAttribute('lon', $minlon);
                    $trkpt = $trkseg->addChild('trkpt');
                    $trkpt->addAttribute('lat', $minlat);
                    $trkpt->addAttribute('lon', $maxlon);
                    $trkpt = $trkseg->addChild('trkpt');
                    $trkpt->addAttribute('lat', $maxlat);
                    $trkpt->addAttribute('lon', $maxlon);
                    $trkpt = $trkseg->addChild('trkpt');
                    $trkpt->addAttribute('lat', $maxlat);
                    $trkpt->addAttribute('lon', $minlon);
                    $trkpt = $trkseg->addChild('trkpt');
                    $trkpt->addAttribute('lat', $minlat);
                    $trkpt->addAttribute('lon', $minlon);
                }
            } else {
                // Invalid data
            }
            // End of rectangle
        } else if ( $shape->type==='polyline' && sizeof($shape->path) > 0 ) {
            if ( property_exists($shape, 'path') ) {
                if ( $type==='kml' ) {
                    $Placemark = $xml->addChild('Placemark');
                    $name = $Placemark->addChild('name', $shapename);
                    $LineString = $Placemark->addChild('LineString');
                    $coordinatesstr = '';
                    foreach( $shape->path as $ptindex => $pt ) {
                        $coordinatesstr .= sprintf( '%f,%f,0 ', $pt->lon, $pt->lat );
                        $valid = true;
                    }
                    $coordinates = $LineString->addChild('coordinates', $coordinatesstr);
                } else {
                    $trk = $xml->addChild('trk');
                    $name = $trk->addChild('name', $shapename);
                    $trkseg = $trk->addChild('trkseg');
                    foreach( $shape->path as $ptindex => $pt ) {
                        //if ( $dev ) echo var_dump($pt).PHP_EOL;
                        $trkpt = $trkseg->addChild('trkpt');
                        $trkpt->addAttribute('lat', $pt->lat);
                        $trkpt->addAttribute('lon', $pt->lon);
                        $valid = true;
                    }
                }
            } else {
                // Invalid data
            }
            // End of polyline
        } else if ( $shape->type==='polygon' ) {
            if ( property_exists($shape, 'paths') && sizeof($shape->paths) > 0 ) {
                foreach ( $shape->paths as $pathindex => $path) {
                    if ( $type==='kml' ) {
                        $Placemark = $xml->addChild('Placemark');
                        $name = $Placemark->addChild('name', $shapename);
                        $Polygon = $Placemark->addChild('Polygon');
                        $outerBoundaryIs = $Polygon->addChild('outerBoundaryIs');
                        $LinearRing = $outerBoundaryIs->addChild('LinearRing');
                        $coordinatesstr = '';
                        if ( property_exists($path, 'path') && sizeof($path->path) > 0 ) {
                            foreach( $path->path as $ptindex => $pt ) {
                                $coordinatesstr .= sprintf( '%f,%f,0 ', $pt->lon, $pt->lat );
                                $valid = true;
                            }
                            $coordinatesstr .= sprintf( '%f,%f,0 ', $path->path[0]->lon, $path->path[0]->lat );
                        }
                        $coordinates = $LinearRing->addChild('coordinates', $coordinatesstr);
                    } else {
                        $trk = $xml->addChild('trk');
                        $name = $trk->addChild('name', $shapename);
                        $trkseg = $trk->addChild('trkseg');
                        if ( property_exists($path, 'path') && sizeof($path->path) > 0 ) {
                            foreach( $path->path as $ptindex => $pt ) {
                                //if ( $dev ) echo var_dump($pt).PHP_EOL;
                                $trkpt = $trkseg->addChild('trkpt');
                                $trkpt->addAttribute('lat', $pt->lat);
                                $trkpt->addAttribute('lon', $pt->lon);
                                $valid = true;
                            }
                            $trkpt = $trkseg->addChild('trkpt');
                            $trkpt->addAttribute('lat', $path->path[0]->lat);
                            $trkpt->addAttribute('lon', $path->path[0]->lon);
                        }
                    }
                }
            } else {
                // Invalid data
            }
            // End of polygon
        } else if ( $shape->type==='circle' ) {
            if ( property_exists($shape, 'center') && sizeof($shape->center) > 0 ) {
                
                // prepare matrices
                if ( !isset($trigonometry) ) {
                    /*
                    // prepare values for rotation matrices
                    $slicenum = array(24, 48, 96, 192, 384, 768, 1536, 3072);
                    foreach ( $slicenum as $slices ) {
                        $cossin = array(cos(M_PI/2/$slices), sin(M_PI*2/$slices));
                        echo $slices. ' => array('.$cossin[0].', '.$cossin[1].'),'.PHP_EOL;
                    }
                    echo PHP_EOL;
                    */
                    $trigonometry = array(
                        // slices => (cosine, sine)
                        24      => array(0.96592582628907, 0.25881904510252),
                        48      => array(0.99144486137381, 0.13052619222005),
                        96      => array(0.9978589232386, 0.065403129230143),
                        192     => array(0.99946458747637, 0.032719082821776),
                        384     => array(0.99986613790956, 0.016361731626487),
                        768     => array(0.9999665339174, 0.0081811396039371),
                        1536    => array(0.99999163344435, 0.0040906040262348),
                        3072    => array(0.9999979083589, 0.0020453062911641),
                    );
                }
                
                $pathlist = array();
                $i = 0;
                foreach ( $shape->center as $i => $center ) {

                    // Convert center polar coordinates (lat lon) to planar
                    // ---------- Replace with your coordinates conversion code ----------
                    $pointSrc = new Point($center->lon, $center->lat, $projLatLon);
                    $pointDest = $proj4->transform($projUTM, $pointSrc);
                    //echo "Conversion: " . $pointDest->toShortString() . " in UTM".PHP_EOL.PHP_EOL;
                    $shape->center[$i]->x = floatval($pointDest->x);
                    $shape->center[$i]->y = floatval($pointDest->y);
                    // ---------- End of coordinates conversion ----------
                
                    // Calculate slice number
                    $slices = intval($center->radius * M_PI * 2 / 20); // 20m resolution
                    //$slices = intval($center->radius * M_PI * 2 / 200); // 200m resolution, for testing
                    //if ( $dev ) echo $center->radius.': '.$slices.PHP_EOL;
                    if ( $slices <= 12 ) {
                        $slices = 12;
                    } else if ( $slices >= 3072 ) {
                        $slices = 3072;
                    } else {
                        foreach ( $trigonometry as $targetslices => $cossin ) {
                            if ( $targetslices >= $slices ) {
                                $slices = $targetslices;
                                break;
                            }
                        }
                    }
                    //if ( $dev ) echo $center->radius.': '.$slices.PHP_EOL;
                    
                    // Draw circles, in planar coordinates
                    // Bisect a circle instead of rotate initial vector to minimize precision error accumulation
                    $indexincrement = $slices / 12;
                    $cx = $center->x;
                    $cy = $center->y;
                    $r = $center->radius;
                    $rs = $r / 2;           // r short
                    $rl = $r * ROOT3HALF;   // r long
                    
                    // Pre-populate the array to make index ordered
                    for ( $j = 0; $j < $slices; $j++ ) {
                        $pathlist[$i][$j] = 0;
                    }
                    
                    // Add initial 12 points of the circle with high precision
                    $j = 0;
                    $pathlist[$i][$j] = array($cx      , $cy - $r ); $j += $indexincrement;
                    $pathlist[$i][$j] = array($cx + $rs, $cy - $rl); $j += $indexincrement;
                    $pathlist[$i][$j] = array($cx + $rl, $cy - $rs); $j += $indexincrement;
                    $pathlist[$i][$j] = array($cx + $r , $cy      ); $j += $indexincrement;
                    $pathlist[$i][$j] = array($cx + $rl, $cy + $rs); $j += $indexincrement;
                    $pathlist[$i][$j] = array($cx + $rs, $cy + $rl); $j += $indexincrement;
                    $pathlist[$i][$j] = array($cx      , $cy + $r ); $j += $indexincrement;
                    $pathlist[$i][$j] = array($cx - $rs, $cy + $rl); $j += $indexincrement;
                    $pathlist[$i][$j] = array($cx - $rl, $cy + $rs); $j += $indexincrement;
                    $pathlist[$i][$j] = array($cx - $r , $cy      ); $j += $indexincrement;
                    $pathlist[$i][$j] = array($cx - $rl, $cy - $rs); $j += $indexincrement;
                    $pathlist[$i][$j] = array($cx - $rs, $cy - $rl); $j += $indexincrement;
                    
                    // Iterate bisect to fill points in between
                    // This counter-intuitive approach is to minimize accumulated precision error
                    $curslice = 12;
                    while ( $indexincrement >= 2 ) {
                        $curslice *= 2;
                        $cos = $trigonometry[$curslice][0];
                        $sin = $trigonometry[$curslice][1];
                        $indexincrement /= 2;
                        $j = 0;
                        while ( $j < $slices ) {
                            $vsrc = $j;
                            $j += $indexincrement;
                            $vdest = $j;
                            $j += $indexincrement;
                            $x = $pathlist[$i][$vsrc][0] - $cx;
                            $y = $pathlist[$i][$vsrc][1] - $cy;
                            $pathlist[$i][$vdest] = array(
                                $x * $cos - $y * $sin + $cx,
                                $x * $sin + $y * $cos + $cy,
                            );
                            /*if ( $dev ) {
                                echo $vsrc.'-'.$vdest.', '.PHP_EOL;
                                echo $x.', '.$y.PHP_EOL;
                                echo $cos.', '.$sin.PHP_EOL;
                                echo $pathlist[$i][$vdest][0].', '.$pathlist[$i][$vdest][1].PHP_EOL;
                            }*/
                        }
                        //if ( $dev ) echo PHP_EOL;
                    }
                    //if ( $dev ) echo PHP_EOL;
                    $i++;
                }

                // Convert planar coordinates to polar (lat lon)
                // ---------- Replace with your coordinates conversion code ----------
                foreach ( $pathlist as $i => $path ) {
                    foreach ( $path as $j => $point ) {
                        $pointSrc = new Point($point[0], $point[1], $projUTM);
                        $pointDest = $proj4->transform($projLatLon, $pointSrc);
                        $pathlist[$i][$j] = array(floatval($pointDest->x), floatval($pointDest->y));
                    }
                }
                // ---------- End of coordinates conversion ----------
                /*if ( $dev ) {
                    echo 'draw: '.(microtime(TRUE)-$now).PHP_EOL;
                    $now = microtime(TRUE);
                }*/

                // Write xml
                if ( $type==='kml' ) {
                    $Placemark = $xml->addChild('Placemark');
                    $name = $Placemark->addChild('name', $shapename);
                    foreach ( $pathlist as $i => $path ) {
                        $Point = $Placemark->addChild('Point');
                        $coordinatesstr = sprintf( '%f,%f,0 ', $shape->center[$i]->lon, $shape->center[$i]->lat );
                        $coordinates = $Point->addChild('coordinates', $coordinatesstr);
                        
                        $Polygon = $Placemark->addChild('Polygon');
                        $outerBoundaryIs = $Polygon->addChild('outerBoundaryIs');
                        $LinearRing = $outerBoundaryIs->addChild('LinearRing');

                        $coordinatesstr = '';
                        foreach( $path as $ptindex => $pt ) {
                            $coordinatesstr .= sprintf( '%f,%f,0 ', $pt[0], $pt[1] );
                            $valid = true;
                        }
                        $coordinatesstr .= sprintf( '%f,%f,0 ', $path[0][0], $path[0][1] );
                        
                        $coordinates = $LinearRing->addChild('coordinates', $coordinatesstr);
                    }
                } else {
                    foreach ( $shape->center as $i => $center ) {
                        $wpt = $xml->addChild('wpt');
                        $wpt->addAttribute('lat', $center->lat);
                        $wpt->addAttribute('lon', $center->lon);
                        $name = $wpt->addChild('name', $shapename);
                    }
                    
                    $trk = $xml->addChild('trk');
                    $name = $trk->addChild('name', $shapename);
                    foreach ( $pathlist as $i => $path ) {
                        $trkseg = $trk->addChild('trkseg');
                        foreach( $path as $ptindex => $pt ) {
                            $trkpt = $trkseg->addChild('trkpt');
                            $trkpt->addAttribute('lat', $pt[1]);
                            $trkpt->addAttribute('lon', $pt[0]);
                            $valid = true;
                        }
                        $trkpt = $trkseg->addChild('trkpt');
                        $trkpt->addAttribute('lat', $path[0][1]);
                        $trkpt->addAttribute('lon', $path[0][0]);
                    }
                }
            }
            // End of circle
        }
    }
} 

if ( $valid && $xml ) {
    if ( $dev ) {
        echo $xml->asXML().PHP_EOL;
    } else {
        header("Content-Type: application/xml; charset=utf-8");
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        echo $xml->asXML();
    }
} else {
    http_response_code(500);
	print_r($_POST['data']);
}

?>