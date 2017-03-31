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

$dev = 0;
$type = 'gpx'; // Supports [ gpx | kml ]
if ( array_key_exists('dev', $_GET) ) $dev = $_GET['dev'];
if ( array_key_exists('type', $_GET) ) $type = $_GET['type'];

if ( $dev ) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

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
                }
            ]
        }';
        
if ( isset($_POST) && sizeof($_POST) > 0 ) {
    $input = $_POST[0];
} else {
    // Get posted data
    $reset_json = file_get_contents( 'php://input' );
    if ( isset($reset_json) && sizeof($reset_json) > 1 ) {
        $input = $reset_json;
        if ( $dev ) echo 'POST'.'<br/>';
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
        if ( $dev ) echo $shape->type.'<br/>';
        $shapename = $index.': '.$shape->type;
        if ( $shape->type==='rectangle' ) {
            if ( property_exists($shape, 'bounds') && property_exists($shape->bounds, 'northEast') && property_exists($shape->bounds, 'southWest') ) {
                if ( $dev ) echo var_dump($shape->bounds).'<br/>';
                
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
                        if ( $dev ) echo var_dump($pt).'<br/>';
                        $trkpt = $trkseg->addChild('trkpt');
                        $trkpt->addAttribute('lat', $pt->lat);
                        $trkpt->addAttribute('lon', $pt->lon);
                        $valid = true;
                    }
                }
            } else {
                // Invalid data
            }
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
                                if ( $dev ) echo var_dump($pt).'<br/>';
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
        }
    }
} 

if ( $valid && $xml ) {
    if ( $dev ) {
        echo '<br/>'.htmlspecialchars($xml->asXML()).'<br/>';
    } else {
        header("Content-Type: application/xml; charset=utf-8");
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        echo $xml->asXML();
    }
} else {
    http_response_code(404);
}

?>
