<?php
$protocol = (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on") ? "https://" : "http://";
$site_url = $protocol . "dev.happyman.idv.tw";

$site_html_root = "/map/";
$site_twmap_html_root = "/gen/";

$CONFIG = [];
$CONFIG['site_url'] = $site_url;
$CONFIG['site_html_root'] = $site_html_root;
$CONFIG['site_twmap_html_root'] = $site_twmap_html_root;
$CONFIG['getkmlfrombounds_url'] = $site_twmap_html_root . "api/getkmlfrombounds.php";
$CONFIG['geocodercache_url'] = $site_twmap_html_root . "api/geocoder.php";
$CONFIG['getkml_url'] = $site_twmap_html_root . "api/getkml.php";
$CONFIG['get_waypoints_url'] = $site_twmap_html_root . "api/waypoints.php";
$CONFIG['get_elev_url'] = $site_twmap_html_root . "api/getelev.php";
$CONFIG['pointdata_url'] = $site_twmap_html_root . "api/pointdata.php";
$CONFIG['rainkml_url'] = $site_html_root . "data/rainkml.php";
$CONFIG['viewshed_url'] = $site_twmap_html_root . "api/get_line_of_sight.php";
$CONFIG['pointdata_admin_url'] = $site_twmap_html_root . "admin/index.php";
$CONFIG['promlist_url'] = $site_twmap_html_root . "admin/promlist.php";
$CONFIG['exportkml_url'] = $site_twmap_html_root . "api/exportkml.php";
$CONFIG['poisearch_url'] = $site_twmap_html_root . "api/poi_search.php";
$CONFIG['shorten_url'] = $site_twmap_html_root . "api/shorten.php";

$CONFIG['default_center'] = [121.5654, 25.0330];
$CONFIG['default_zoom'] = 8;
