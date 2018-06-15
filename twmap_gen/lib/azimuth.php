<?php
# --------------------------------------------------------------------------------------------
#                  __                     /\ \__/\ \
#    __     ____  /\_\    ___ ___   __  __\ \ ,_\ \ \___
#  /'__`\  /\_ ,`\\/\ \ /' __` __`\/\ \/\ \\ \ \/\ \  _ `\
# /\ \L\.\_\/_/  /_\ \ \/\ \/\ \/\ \ \ \_\ \\ \ \_\ \ \ \ \
# \ \__/.\_\ /\____\\ \_\ \_\ \_\ \_\ \____/ \ \__\\ \_\ \_\
#  \/__/\/_/ \/____/ \/_/\/_/\/_/\/_/\/___/   \/__/ \/_/\/_/
#
#               Azimuth : Simple PHP library to compute azimuth (°), distance (km) & sight altitude (°)
#               GNU GPL v3
#               Gautier Michelin, 2015
#               based on Don Cross work, http://cosinekitty.com/compass.html
#
# -------------------------------------------------------------------------------------------

    /**
     * ParseAngle : test if an angle is valid and between -limit to +limit]
     *
     * @param [float]  $angle value of the angle to check
     * @param [float] $limit value to use as a positive or negative boundary
     */
    function ParseAngle($angle, $limit = 360) {
        if (is_nan($angle) || ($angle < -$limit) || ($angle > $limit)) {
            return null;
        } else {
            return $angle;
        }
    }

    /**
     * ParseElevation : test if an elevation is valid (check only if is a number for now)
     *
     * @param [float] $angle value of the angle to check
     */
    function ParseElevation($angle)
    {
        if (is_nan($angle)) {
            return null;
        } else {
            return $angle;
        }
    }

    /**
     * ParseLocation : test if coordinates are valid, it should an array with at least "lat" and "long"
     *
     * @param array $coordinates an array containing at least "lon" and "lat" values
     */
    function ParseLocation(array $coordinates) {
				//if (!isset($coordinates["lat"])) $coordinates["lat"] = 0;
				//if (!isset($coordinates["lon"])) $coordinates["lon"] = 0;
				if (!isset($coordinates["elv"])) $coordinates["elv"] = 0;

        $lat = ParseAngle($coordinates["lat"], 90.0);
        $location = null;
        if ($lat != null) {
            $lon = ParseAngle ($coordinates["lon"], 180.0);
            if ($lon != null) {
                $elv = ParseElevation($coordinates["elv"]);
                if ($elv != null) {
                    $location = array('lat'=>$lat, 'lon'=>$lon, 'elv'=>$elv);
                }
            }
        }
        return $location;
    }

    /**
     * [EarthRadiusInMeters description]
     * @param [type] $latitudeRadians [description]
     */
    function EarthRadiusInMeters($latitudeRadians) {
        // http://en.wikipedia.org/wiki/Earth_radius
        $a = 6378137.0;  // equatorial radius in meters
        $b = 6356752.3;  // polar radius in meters
        $cos = cos($latitudeRadians);
        $sin = sin($latitudeRadians);
        $t1 = $a * $a * $cos;
        $t2 = $b * $b * $sin;
        $t3 = $a * $cos;
        $t4 = $b * $sin;
        return sqrt(($t1*$t1 + $t2*$t2) / ($t3*$t3 + $t4*$t4));
    }

    /**
     * [LocationToPoint description]
     * @param array $c [description]
     */
    function LocationToPoint(array $c) {
        // Convert (lat, lon, elv) to (x, y, z).
        $lat = $c["lat"] * pi() / 180.0;
        $lon = $c["lon"] * pi() / 180.0;
        $radius = $c["elv"] + EarthRadiusInMeters($lat);
        $cosLon = cos($lon);
        $sinLon = sin($lon);
        $cosLat = cos($lat);
        $sinLat = sin($lat);
        $x = $cosLon * $cosLat * $radius;
        $y = $sinLon * $cosLat * $radius;
        $z = $sinLat * $radius;
        return array('x'=>$x, 'y'=>$y, 'z'=>$z, 'radius'=>$radius);
    }

		/**
		 * [Distance description]
		 * @param array $ap [description]
		 * @param array $bp [description]
		 */
    function Distance (array $ap, array $bp) {
        $dx = $ap["x"] - $bp["x"];
        $dy = $ap["y"] - $bp["y"];
        $dz = $ap["z"] - $bp["z"];
        return sqrt($dx*$dx + $dy*$dy + $dz*$dz);
    }

    /**
     * [RotateGlobe description]
     * @param array  $b       [description]
     * @param array  $a       [description]
     * @param [type] $bradius [description]
     * @param [type] $aradius [description]
     */
    function RotateGlobe(array $b, array $a, $bradius, $aradius) {
        // Get modified coordinates of 'b' by rotating the globe so that 'a' is at lat=0, lon=0.
        $br = array('lat'=> $b["lat"], 'lon'=> ($b["lon"] - $a["lon"]), 'elv'=>$b["elv"]);
        $brp = LocationToPoint($br);

        // scale all the coordinates based on the original, correct geoid radius...
        $brp["x"] *= ($bradius / $brp["radius"]);
        $brp["y"] *= ($bradius / $brp["radius"]);
        $brp["z"] *= ($bradius / $brp["radius"]);
        $brp["radius"] = $bradius;   // restore actual geoid-based radius calculation

		    // Rotate brp cartesian coordinates around the z-axis by a.lon degrees,
        // then around the y-axis by a.lat degrees.
        // Though we are decreasing by a.lat degrees, as seen above the y-axis,
        // this is a positive (counterclockwise) rotation (if B's longitude is east of A's).
        // However, from this point of view the x-axis is pointing left.
        // So we will look the other way making the x-axis pointing right, the z-axis
        // pointing up, and the rotation treated as negative.

        $alat = -$a["lat"] * pi() / 180.0;
        $acos = cos($alat);
        $asin = sin($alat);

        $bx = ($brp["x"] * $acos) - ($brp["z"] * $asin);
        $by = $brp["y"];
        $bz = ($brp["x"] * $asin) + ($brp["z"] * $acos);

        return array('x'=>$bx, 'y'=>$by, 'z'=>$bz);
    }

    /**
     * Calculate
     * ------------------
     * This function returns the azimuth, the distance between two points on the globe and the altitude
     * between 2 points on the globe, based on their latitude (°N), longitude (°E), and elevation (meters).
     *
     * @param array $origin containing at least "lat" for latitude as a float, "lon" for longitude as a float,
     *                      can contains "elv" for elevation in meters from the sea, default elevation fixed to
     *                      0 if not set
     * @param array $target containing at least "lat" for latitude as a float, "lon" for longitude as a float,
     *                      can contains "elv" for elevation in meters from the sea, default elevation fixed to
     *                      0 if not set
     */
    function Calculate(array $origin, array $target) {
        $a = ParseLocation($origin);
        if ($a != null) {
            $b = ParseLocation($target);
            if ($b != null) {
                $ap = LocationToPoint($a);
                $bp = LocationToPoint($b);
                $distKm = 0.001 * round(Distance($ap, $bp));

                // Let's use a trick to calculate azimuth:
                // Rotate the globe so that point A looks like latitude 0, longitude 0.
                // We keep the actual radii calculated based on the oblate geoid,
                // but use angles based on subtraction.
                // Point A will be at x=radius, y=0, z=0.
                // Vector difference B-A will have dz = N/S component, dy = E/W component.

                $br = RotateGlobe($b, $a, $bp["radius"], $ap["radius"]);
                $theta = atan2($br["z"], $br["y"]) * 180.0 / pi();
                $azimuth = 90.0 - $theta;
                if ($azimuth < 0.0) {
                    $azimuth += 360.0;
                }
                if ($azimuth > 360.0) {
                    $azimuth -= 360.0;
                }
								// Return rounded azimuth
								//$azimuth = round(($azimuth*10)/10);

                // Calculate altitude, which is the angle above the horizon of B as seen from A.
                // Almost always, B will actually be below the horizon, so the altitude will be negative.
                $shadow = sqrt(($br["y"] * $br["y"]) + ($br["z"] * $br["z"]));
                $altitude = atan2 ($br["x"] - $ap["radius"], $shadow) * 180.0 / pi();
                // Returns rounded altitude
								///$altitude = round(($altitude*100)/100);

								return array("distKm"=>$distKm, "azimuth"=>$azimuth, "altitude"=>$altitude);
            }
        }
    }

		// Code sample : remove the next comments /* and */ to try.
		/*

		var_dump(
			Calculate(
				// Tour Eiffel
				array("lat"=> 48.85825, "lon"=>2.2945, "elv"=>357.5),
				// Le Mans
				array("lat"=> 48.006110000000010000, "lon"=>0.199556000000029600, "elv"=>134)
			)
		);

		*/
