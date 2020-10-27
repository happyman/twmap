///////////////////////////////////////////////////////////////////////////////
// loadgpx.js
//
// MIT License
//
// Copyright (c) 2018 Kaz Okuda (http://notions.okuda.ca)
//
// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in all
// copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
// SOFTWARE.
//
///////////////////////////////////////////////////////////////////////////////
//
// Javascript object to load GPX-format GPS data into Google Maps.
//
// Usage:
//
// parser = new GPXParser(<gpxfiledata>, new google.maps.Map(...));
// parser.SetTrackColour("#ff0000");				// Set the track line colour
// parser.SetTrackWidth(5);							// Set the track line width
// parser.SetMinTrackPointDelta(0.001);				// Set the minimum distance between track points
// parser.CenterAndZoom(request.responseXML);		// Center and Zoom the map over all the points.
// parser.AddTrackpointsToMap();					// Add the trackpoints
// parser.AddWaypointsToMap();						// Add the waypoints
// 
// Code is hosted on GitHub https://github.com/kokuda/gpxviewer
//
// If you use this script or have any questions please leave a comment
// at http://notions.okuda.ca/geotagging/projects-im-working-on/gpx-viewer/
//
///////////////////////////////////////////////////////////////////////////////

function GPXParser(xmlDoc, map)
{
	this.xmlDoc = xmlDoc;
	this.map = map;
	this.trackcolour = "#ff00ff"; // red
	this.trackwidth = 5;
	this.mintrackpointdelta = 0.0000001
	this.infowindows = [];
	this.markers = [];
	this.polylines = [];
	// add shapes happyman
	this.shapes = [];
}

// Set the colour of the track line segements.
GPXParser.prototype.SetTrackColour = function(colour)
{
	this.trackcolour = colour;
}

// Set the width of the track line segements
GPXParser.prototype.SetTrackWidth = function(width)
{
	this.trackwidth = width;
}

// Set the minimum distance between trackpoints.
// Used to cull unneeded trackpoints from map.
GPXParser.prototype.SetMinTrackPointDelta = function(delta)
{
	this.mintrackpointdelta = delta;
}

GPXParser.prototype.TranslateName = function(name)
{
	if (name == "wpt")
	{
		return "Waypoint";
	}
	else if (name == "trkpt")
	{
		return "Track Point";
	}
}


GPXParser.prototype.getSymIcon = function(symbol) {
                 var symname =  symbol;
                                var id = symname.toLowerCase();
                                if (this.sym[id])
                                        icon = "https://dayanuyim.github.io/maps/images/sym/128/"+ this.sym[id].filename;
                                else
                                        icon =  "https://dayanuyim.github.io/maps/images/sym/128/" +this.sym['waypoint'].filename;

                return icon;

}
GPXParser.prototype.CreateMarker = function(point)
{
	var lon = parseFloat(point.getAttribute("lon")) || 0;
	var lat = parseFloat(point.getAttribute("lat")) || 0;
	var html = "";
	var icon = "";
	var htmlhead = "";

	if (point.getElementsByTagName("html").length > 0)
	{
		for (i=0; i<point.getElementsByTagName("html").item(0).childNodes.length; i++)
		{
			html += point.getElementsByTagName("html").item(0).childNodes[i].nodeValue;
		}
	}
	else
	{
		// Create the html if it does not exist in the point.
		htmlhead = "<b>" + this.TranslateName(point.nodeName) + "</b><br>";
		var attributes = point.attributes;
		var attrlen = attributes.length;
		for (i=0; i<attrlen; i++)
		{
			if (attributes.item(i).name == 'sym') {
				icon = this.getSymIcon(attributes.item(i).nodeValue);
			}
			if (attributes.item(i).name == 'name') {
				htmlhead = "<b>" + attributes.item(i).nodeValue + "</b><br>";
			} else {
				html += attributes.item(i).name + " = " + attributes.item(i).nodeValue + "<br>";
			}
		}

		if (point.hasChildNodes)
		{
			var children = point.childNodes;
			var childrenlen = children.length;
			for (i=0; i<childrenlen; i++)
			{
				// Ignore empty nodes
				if (children[i].nodeType != 1) continue;
				if (children[i].firstChild == null) continue;
				if ( children[i].nodeName == 'sym') {
					icon = this.getSymIcon( children[i].firstChild.nodeValue );
				}
				if ( children[i].nodeName  == 'name') {
					htmlhead = "<b>" +  children[i].firstChild.nodeValue + "</b><br>";
				} else {
					html += children[i].nodeName + " = " + children[i].firstChild.nodeValue + "<br>";
				}
			}
		}
	}

	var infowindow = new google.maps.InfoWindow({
		content: htmlhead + html
	});

	var marker = new google.maps.Marker({
		position: new google.maps.LatLng(lat,lon),
		icon: { url: icon, scaledSize : new google.maps.Size(32, 32) },
		map: this.map
	});
	this.infowindows.push(infowindow);
	this.markers.push(marker);

	marker.addListener("mouseover", function() { infowindow.open(marker.get('map'), marker); });
	marker.addListener("mouseout", function() { infowindow.close(); });
}


GPXParser.prototype.AddTrackSegmentToMap = function(trackSegment, colour, width)
{
	//var latlngbounds = new google.maps.LatLngBounds();

	var trackpoints = trackSegment.getElementsByTagName("trkpt");
	if (trackpoints.length == 0)
	{
		return;
	}

	var pointarray = [];
	var pointarray2 = [];
	// process first point
	var lastlon = parseFloat(trackpoints[0].getAttribute("lon")) || 0;
	var lastlat = parseFloat(trackpoints[0].getAttribute("lat")) || 0;
	var latlng = new google.maps.LatLng(lastlat,lastlon);
	pointarray.push(latlng);

	for (var i=1; i < trackpoints.length; i++)
	{
		var lon = parseFloat(trackpoints[i].getAttribute("lon")) || 0;
		var lat = parseFloat(trackpoints[i].getAttribute("lat")) || 0;

		// Verify that this is far enough away from the last point to be used.
		var latdiff = lat - lastlat;
		var londiff = lon - lastlon;
		if ( Math.sqrt(latdiff*latdiff + londiff*londiff) > this.mintrackpointdelta )
		{
			lastlon = lon;
			lastlat = lat;
			latlng = new google.maps.LatLng(lat,lon);
			pointarray.push(latlng);
			pointarray2.push( { "lat": lat, "lon": lon } );
		}

	}

	var polyline = new google.maps.Polyline({
		path: pointarray,
		strokeColor: colour,
		strokeWeight: width
	});

	this.shapes.push( { "type": "polyline", "color": "#0000FF", "path": pointarray2 });
	polyline.setMap(this.map);
	this.polylines.push(polyline);
}
// showShapes happyman
GPXParser.prototype.showShapes = function() {
	var myshapes = localStorage.getItem("shapes");
    /*jshint evil:true */
	var jsonObject = (myshapes) ? eval("(" + myshapes + ")") : {"shapes": []};
	var j = jsonObject.shapes.length;
	for(var i = 0; i < this.shapes.length; i++) {
		jsonObject.shapes[j++] = this.shapes[i];
	}
	shapesMap.shapesClearAll();
	localStorage.setItem("shapes", JSON.stringify(jsonObject));
	shapesMap.shapesLoad();
	shapesMap.lastshape_select_click();

}

GPXParser.prototype.AddTrackToMap = function(track, colour, width)
{
	var segments = track.getElementsByTagName("trkseg");

	for (var i=0; i < segments.length; i++)
	{
		var segmentlatlngbounds = this.AddTrackSegmentToMap(segments[i], colour, width);
	}
}

GPXParser.prototype.CenterAndZoom = function (trackSegment, maptype)
{

	var pointlist = new Array("trkpt", "wpt");
	var bounds = new google.maps.LatLngBounds();

	for (var pointtype=0; pointtype < pointlist.length; pointtype++)
	{
		var trackpoints = trackSegment.getElementsByTagName(pointlist[pointtype]);

		for (var i=0; i < trackpoints.length; i++)
		{
			var lon = parseFloat(trackpoints[i].getAttribute("lon")) || 0;
			var lat = parseFloat(trackpoints[i].getAttribute("lat")) || 0;

			bounds.extend(new google.maps.LatLng(lat, lon));
		}
	}

	this.map.fitBounds(bounds);
	this.map.setCenter(bounds.getCenter());

	// maptype is maintained for backward compatibility, but it should not be relied upon.
	// map.setMapTypeId can be called directly
	if (maptype !== undefined)
	{
		console.warn("WARNING: gpxviewer CenterAndZoom maptype argument is deprecated.")
		this.map.setMapTypeId(maptype);
	}
}

GPXParser.prototype.CenterAndZoomToLatLngBounds = function (latlngboundsarray)
{
	var boundingbox = new google.maps.LatLngBounds();
	for (var i=0; i<latlngboundsarray.length; i++)
	{
		if (!latlngboundsarray[i].isEmpty())
		{
			boundingbox.extend(latlngboundsarray[i].getSouthWest());
			boundingbox.extend(latlngboundsarray[i].getNorthEast());
		}
	}

	this.map.fitBounds(boundingbox);
	this.map.setCenter(boundingbox.getCenter());
}


GPXParser.prototype.AddTrackpointsToMap = function ()
{
	var tracks = this.xmlDoc.documentElement.getElementsByTagName("trk");

	for (var i=0; i < tracks.length; i++)
	{
		this.AddTrackToMap(tracks[i], this.trackcolour, this.trackwidth);
	}
}

GPXParser.prototype.AddWaypointsToMap = function ()
{
	var waypoints = this.xmlDoc.documentElement.getElementsByTagName("wpt");

	for (var i=0; i < waypoints.length; i++)
	{
		this.CreateMarker(waypoints[i]);
	}
}
GPXParser.prototype.Destroy = function() {
	var i = 0;
	console.log("destroy gpxparser obj");
	for(i=0;i<this.infowindows.length;i++) {
		this.infowindows[i].setMap(null);
		this.markers[i].setMap(null);
	}
	for(i=0;i<this.polylines.length;i++) {
		this.polylines[i].setMap(null);
	}
}

/*
from https://dayanuyim.github.io/maps/
https://github.com/dayanuyim/Trekkr/blob/dev/app/data/symbols.js
Thanks for the collection
*/


GPXParser.prototype.sym = {
  "atv": {
    "filename": "ATV.png",
    "name": "ATV",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "airport intersection": {
    "filename": "Airport Intersection.png",
    "name": "Airport Intersection",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "airport ndb": {
    "filename": "Airport NDB.png",
    "name": "Airport NDB",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "dayanuyim",
      "url": "https://github.com/dayanuyim"
    },
    "provider": {
      "title": "dayanuyim",
      "url": "https://github.com/dayanuyim"
    }
  },
  "airport": {
    "filename": "Airport.png",
    "name": "Airport",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "amusement park": {
    "filename": "Amusement Park.png",
    "name": "Amusement Park",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "anchor prohibited": {
    "filename": "Anchor Prohibited.png",
    "name": "Anchor Prohibited",
    "license": {
      "title": "Public Domain",
      "url": "https://en.wikipedia.org/wiki/Public_domain"
    },
    "maker": {
      "title": "Lokal Profil",
      "url": "https://commons.wikimedia.org/wiki/User:Lokal_Profil"
    },
    "provider": {
      "title": "Wikimedia",
      "url": "https://commons.wikimedia.org"
    }
  },
  "anchor": {
    "filename": "Anchor.png",
    "name": "Anchor",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "animal tracks": {
    "filename": "Animal Tracks.png",
    "name": "Animal Tracks",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "arbor": {
    "filename": "Arbor.png",
    "name": "Arbor",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik ",
      "url": "https://www.Freepik .com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "aviation intersection": {
    "filename": "Aviation Intersection.png",
    "name": "Aviation Intersection",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "itim2101",
      "url": "https://www.flaticon.com/authors/itim2101"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "bait and tackle": {
    "filename": "Bait And Tackle.png",
    "name": "Bait And Tackle",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "ball park": {
    "filename": "Ball Park.png",
    "name": "Ball Park",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Good Ware",
      "url": "https://www.flaticon.com/authors/good-ware"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "bank": {
    "filename": "Bank.png",
    "name": "Bank",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "bar": {
    "filename": "Bar.png",
    "name": "Bar",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "beach": {
    "filename": "Beach.png",
    "name": "Beach",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "beacon": {
    "filename": "Beacon.png",
    "name": "Beacon",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "bell": {
    "filename": "Bell.png",
    "name": "Bell",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "big game": {
    "filename": "Big Game.png",
    "name": "Big Game",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "bike trail": {
    "filename": "Bike Trail.png",
    "name": "Bike Trail",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "blind": {
    "filename": "Blind.png",
    "name": "Blind",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "block, blue": {
    "filename": "Block, Blue.png",
    "name": "Block, Blue",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "block, green": {
    "filename": "Block, Green.png",
    "name": "Block, Green",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "block, red": {
    "filename": "Block, Red.png",
    "name": "Block, Red",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "blood trail": {
    "filename": "Blood Trail.png",
    "name": "Blood Trail",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "boat ramp": {
    "filename": "Boat Ramp.png",
    "name": "Boat Ramp",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Scott de Jonge",
      "url": "https://www.flaticon.com/authors/scott-de-jonge"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "bowling": {
    "filename": "Bowling.png",
    "name": "Bowling",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "bridge": {
    "filename": "Bridge.png",
    "name": "Bridge",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "building": {
    "filename": "Building.png",
    "name": "Building",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "buoy, white": {
    "filename": "Buoy, White.png",
    "name": "Buoy, White",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Eucalyp",
      "url": "https://www.flaticon.com/authors/eucalyp"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "campground": {
    "filename": "Campground.png",
    "name": "Campground",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "car rental": {
    "filename": "Car Rental.png",
    "name": "Car Rental",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Those Icons",
      "url": "https://www.flaticon.com/authors/those-icons/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "car repair": {
    "filename": "Car Repair.png",
    "name": "Car Repair",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "monkik",
      "url": "https://www.flaticon.com/authors/monkik"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "car": {
    "filename": "Car.png",
    "name": "Car",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "srip",
      "url": "https://www.flaticon.com/authors/srip"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "cemetery": {
    "filename": "Cemetery.png",
    "name": "Cemetery",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "church": {
    "filename": "Church.png",
    "name": "Church",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "circle with x": {
    "filename": "Circle with X.png",
    "name": "Circle with X",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "circle, blue": {
    "filename": "Circle, Blue.png",
    "name": "Circle, Blue",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "circle, green": {
    "filename": "Circle, Green.png",
    "name": "Circle, Green",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "circle, red": {
    "filename": "Circle, Red.png",
    "name": "Circle, Red",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "city (capitol)": {
    "filename": "City (Capitol).png",
    "name": "City (Capitol)",
    "license": {
      "title": "Creative Commons BY-SA 4.0",
      "url": "https://creativecommons.org/licenses/by-sa/4.0/"
    },
    "maker": {
      "title": "Roman Poulvas",
      "url": "https://commons.wikimedia.org/wiki/User:Roman_Poulvas"
    },
    "provider": {
      "title": "Wikimedia",
      "url": "https://commons.wikimedia.org/wiki/File:Capital_mark.svg"
    }
  },
  "city (large)": {
    "filename": "City (Large).png",
    "name": "City (Large)",
    "license": {
      "title": "Creative Commons BY-SA 4.0",
      "url": "https://creativecommons.org/licenses/by-sa/4.0/"
    },
    "maker": {
      "title": "Roman Poulvas",
      "url": "https://commons.wikimedia.org/wiki/User:Roman_Poulvas"
    },
    "provider": {
      "title": "Wikimedia",
      "url": "https://commons.wikimedia.org/wiki/File:Centre_mark.svg"
    }
  },
  "city (medium)": {
    "filename": "City (Medium).png",
    "name": "City (Medium)",
    "license": {
      "title": "Public Domain",
      "url": "https://en.wikipedia.org/wiki/Public_domain"
    },
    "maker": {
      "title": "Wikisoft*",
      "url": "https://commons.wikimedia.org/wiki/User_talk:Wikisoft*"
    },
    "provider": {
      "title": "Wikimedia",
      "url": "https://commons.wikimedia.org/wiki/File:City_locator_13.svg"
    }
  },
  "city (small)": {
    "filename": "City (Small).png",
    "name": "City (Small)",
    "license": {
      "title": "Public Domain",
      "url": "https://en.wikipedia.org/wiki/Public_domain"
    },
    "maker": {
      "title": "Wikisoft*",
      "url": "https://commons.wikimedia.org/wiki/User_talk:Wikisoft*"
    },
    "provider": {
      "title": "Wikimedia",
      "url": "https://commons.wikimedia.org/wiki/File:City_locator_14.svg"
    }
  },
  "city hall": {
    "filename": "City Hall.png",
    "name": "City Hall",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "civil": {
    "filename": "Civil.png",
    "name": "Civil",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "phatplus",
      "url": "https://www.flaticon.com/authors/phatplus"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "coast guard": {
    "filename": "Coast Guard.png",
    "name": "Coast Guard",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "cone": {
    "filename": "Cone.png",
    "name": "Cone",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "contact, afro": {
    "filename": "Contact, Afro.png",
    "name": "Contact, Afro",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "contact, alien": {
    "filename": "Contact, Alien.png",
    "name": "Contact, Alien",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "contact, ball cap": {
    "filename": "Contact, Ball Cap.png",
    "name": "Contact, Ball Cap",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "contact, big ears": {
    "filename": "Contact, Big Ears.png",
    "name": "Contact, Big Ears",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "contact, biker": {
    "filename": "Contact, Biker.png",
    "name": "Contact, Biker",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "contact, blonde": {
    "filename": "Contact, Blonde.png",
    "name": "Contact, Blonde",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "contact, bug": {
    "filename": "Contact, Bug.png",
    "name": "Contact, Bug",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "contact, cat": {
    "filename": "Contact, Cat.png",
    "name": "Contact, Cat",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "contact, clown": {
    "filename": "Contact, Clown.png",
    "name": "Contact, Clown",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "contact, dog": {
    "filename": "Contact, Dog.png",
    "name": "Contact, Dog",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "contact, dreadlocks": {
    "filename": "Contact, Dreadlocks.png",
    "name": "Contact, Dreadlocks",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "contact, female1": {
    "filename": "Contact, Female1.png",
    "name": "Contact, Female1",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "contact, female2": {
    "filename": "Contact, Female2.png",
    "name": "Contact, Female2",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "contact, female3": {
    "filename": "Contact, Female3.png",
    "name": "Contact, Female3",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "contact, glasses": {
    "filename": "Contact, Glasses.png",
    "name": "Contact, Glasses",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "contact, goatee": {
    "filename": "Contact, Goatee.png",
    "name": "Contact, Goatee",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "contact, kung fu": {
    "filename": "Contact, Kung Fu.png",
    "name": "Contact, Kung Fu",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "contact, panda": {
    "filename": "Contact, Panda.png",
    "name": "Contact, Panda",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "contact, pig": {
    "filename": "Contact, Pig.png",
    "name": "Contact, Pig",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "contact, pirate": {
    "filename": "Contact, Pirate.png",
    "name": "Contact, Pirate",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "contact, ranger": {
    "filename": "Contact, Ranger.png",
    "name": "Contact, Ranger",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Good Ware",
      "url": "https://www.flaticon.com/authors/good-ware"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "contact, smiley": {
    "filename": "Contact, Smiley.png",
    "name": "Contact, Smiley",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "contact, spike": {
    "filename": "Contact, Spike.png",
    "name": "Contact, Spike",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "contact, sumo": {
    "filename": "Contact, Sumo.png",
    "name": "Contact, Sumo",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "controlled area": {
    "filename": "Controlled Area.png",
    "name": "Controlled Area",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "convenience store": {
    "filename": "Convenience Store.png",
    "name": "Convenience Store",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Google",
      "url": "https://www.Google.com/"
    },
    "provider": {
      "title": "Google",
      "url": "https://www.Google.com/"
    }
  },
  "cover": {
    "filename": "Cover.png",
    "name": "Cover",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "covey": {
    "filename": "Covey.png",
    "name": "Covey",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "pongsakornRed",
      "url": "https://www.flaticon.com/authors/pongsakornred"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "crosshair": {
    "filename": "Crosshair.png",
    "name": "Crosshair",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "crossing": {
    "filename": "Crossing.png",
    "name": "Crossing",
    "license": {
      "title": "Public Domain",
      "url": "https://en.wikipedia.org/wiki/Public_domain"
    },
    "maker": {
      "title": "Alkari",
      "url": "https://commons.wikimedia.org/wiki/User:Cmdrjameson"
    },
    "provider": {
      "title": "Wikimedia",
      "url": "https://commons.wikimedia.org"
    }
  },
  "dam": {
    "filename": "Dam.png",
    "name": "Dam",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Smashicons",
      "url": "https://www.flaticon.com/authors/smashicons"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "danger area": {
    "filename": "Danger Area.png",
    "name": "Danger Area",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "BoatSafe.com",
      "url": "https://boatsafe.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "department store": {
    "filename": "Department Store.png",
    "name": "Department Store",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Tippawan Sookruay",
      "url": "https://www.iconfinder.com/WANICON"
    },
    "provider": {
      "title": "ICONFINDER",
      "url": "https://www.iconfinder.com/icons/3943438/bag_christmas_gift_market_present_shopping_store_icon"
    }
  },
  "diamond, blue": {
    "filename": "Diamond, Blue.png",
    "name": "Diamond, Blue",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "diamond, green": {
    "filename": "Diamond, Green.png",
    "name": "Diamond, Green",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "diamond, red": {
    "filename": "Diamond, Red.png",
    "name": "Diamond, Red",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "direction": {
    "filename": "Direction.png",
    "name": "Direction",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "diver down flag 1": {
    "filename": "Diver Down Flag 1.png",
    "name": "Diver Down Flag 1",
    "license": {
      "title": "Public Domain",
      "url": "https://en.wikipedia.org/wiki/Public_domain"
    },
    "maker": {
      "title": "Alkari",
      "url": "https://commons.wikimedia.org/wiki/User:Alkari"
    },
    "provider": {
      "title": "Wikimedia",
      "url": "https://commons.wikimedia.org"
    }
  },
  "diver down flag 2": {
    "filename": "Diver Down Flag 2.png",
    "name": "Diver Down Flag 2",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "BoatSafe.com",
      "url": "https://boatsafe.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "dock": {
    "filename": "Dock.png",
    "name": "Dock",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "dot, white": {
    "filename": "Dot, White.png",
    "name": "Dot, White",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "drinking water": {
    "filename": "Drinking Water.png",
    "name": "Drinking Water",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "dropoff": {
    "filename": "Dropoff.png",
    "name": "Dropoff",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "earthcache": {
    "filename": "Earthcache.png",
    "name": "Earthcache",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Geocaching",
      "url": "https://www.geocaching.com/"
    },
    "provider": {
      "title": "Geocaching",
      "url": "https://www.geocaching.com/"
    }
  },
  "exit": {
    "filename": "Exit.png",
    "name": "Exit",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "fast food": {
    "filename": "Fast Food.png",
    "name": "Fast Food",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "fishing area": {
    "filename": "Fishing Area.png",
    "name": "Fishing Area",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "fishing hot spot facility": {
    "filename": "Fishing Hot Spot Facility.png",
    "name": "Fishing Hot Spot Facility",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "fitness center": {
    "filename": "Fitness Center.png",
    "name": "Fitness Center",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "flag, blue": {
    "filename": "Flag, Blue.png",
    "name": "Flag, Blue",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "flag, green": {
    "filename": "Flag, Green.png",
    "name": "Flag, Green",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "flag, red": {
    "filename": "Flag, Red.png",
    "name": "Flag, Red",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "flag": {
    "filename": "Flag.png",
    "name": "Flag",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "food source": {
    "filename": "Food Source.png",
    "name": "Food Source",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "forest": {
    "filename": "Forest.png",
    "name": "Forest",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "furbearer": {
    "filename": "Furbearer.png",
    "name": "Furbearer",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "gas station": {
    "filename": "Gas Station.png",
    "name": "Gas Station",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "monkik",
      "url": "https://www.flaticon.com/authors/monkik"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "geocache found": {
    "filename": "Geocache Found.png",
    "name": "Geocache Found",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "geocache": {
    "filename": "Geocache.png",
    "name": "Geocache",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "ghost town": {
    "filename": "Ghost Town.png",
    "name": "Ghost Town",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "glider area": {
    "filename": "Glider Area.png",
    "name": "Glider Area",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "golf course": {
    "filename": "Golf Course.png",
    "name": "Golf Course",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "ground transportation": {
    "filename": "Ground Transportation.png",
    "name": "Ground Transportation",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "srip",
      "url": "https://www.flaticon.com/authors/srip"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "heliport": {
    "filename": "Heliport.png",
    "name": "Heliport",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "phatplus",
      "url": "https://www.flaticon.com/authors/phatplus"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "horn": {
    "filename": "Horn.png",
    "name": "Horn",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "hunting area": {
    "filename": "Hunting Area.png",
    "name": "Hunting Area",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "ice skating": {
    "filename": "Ice Skating.png",
    "name": "Ice Skating",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "information": {
    "filename": "Information.png",
    "name": "Information",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "lab cache": {
    "filename": "Lab Cache.png",
    "name": "Lab Cache",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Geocaching",
      "url": "https://www.geocaching.com/"
    },
    "provider": {
      "title": "Geocaching",
      "url": "https://www.geocaching.com/"
    }
  },
  "letter a, blue": {
    "filename": "Letter A, Blue.png",
    "name": "Letter A, Blue",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "letter a, green": {
    "filename": "Letter A, Green.png",
    "name": "Letter A, Green",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "letter a, red": {
    "filename": "Letter A, Red.png",
    "name": "Letter A, Red",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "letter b, blue": {
    "filename": "Letter B, Blue.png",
    "name": "Letter B, Blue",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "letter b, green": {
    "filename": "Letter B, Green.png",
    "name": "Letter B, Green",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "letter b, red": {
    "filename": "Letter B, Red.png",
    "name": "Letter B, Red",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "letter c, blue": {
    "filename": "Letter C, Blue.png",
    "name": "Letter C, Blue",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "letter c, green": {
    "filename": "Letter C, Green.png",
    "name": "Letter C, Green",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "letter c, red": {
    "filename": "Letter C, Red.png",
    "name": "Letter C, Red",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "letter d, blue": {
    "filename": "Letter D, Blue.png",
    "name": "Letter D, Blue",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "letter d, green": {
    "filename": "Letter D, Green.png",
    "name": "Letter D, Green",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "letter d, red": {
    "filename": "Letter D, Red.png",
    "name": "Letter D, Red",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "letterbox cache": {
    "filename": "Letterbox Cache.png",
    "name": "Letterbox Cache",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Geocaching",
      "url": "https://www.geocaching.com/"
    },
    "provider": {
      "title": "Geocaching",
      "url": "https://www.geocaching.com/"
    }
  },
  "levee": {
    "filename": "Levee.png",
    "name": "Levee",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Eucalyp",
      "url": "https://www.flaticon.com/authors/eucalyp"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "library": {
    "filename": "Library.png",
    "name": "Library",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "light": {
    "filename": "Light.png",
    "name": "Light",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Good Ware",
      "url": "https://www.flaticon.com/authors/good-ware"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "live theater": {
    "filename": "Live Theater.png",
    "name": "Live Theater",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "lodge": {
    "filename": "Lodge.png",
    "name": "Lodge",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "lodging": {
    "filename": "Lodging.png",
    "name": "Lodging",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "man overboard": {
    "filename": "Man Overboard.png",
    "name": "Man Overboard",
    "license": {
      "title": "Public Domain",
      "url": "https://en.wikipedia.org/wiki/Public_domain"
    },
    "maker": {
      "title": "Denelson83",
      "url": "https://commons.wikimedia.org/wiki/User:Denelson83"
    },
    "provider": {
      "title": "Wikimedia",
      "url": "https://commons.wikimedia.org"
    }
  },
  "map address": {
    "filename": "Map Address.png",
    "name": "Map Address",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "srip",
      "url": "https://www.flaticon.com/authors/srip"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "map area": {
    "filename": "Map Area.png",
    "name": "Map Area",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "srip",
      "url": "https://www.flaticon.com/authors/srip"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "map intersection": {
    "filename": "Map Intersection.png",
    "name": "Map Intersection",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "srip",
      "url": "https://www.flaticon.com/authors/srip"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "map line": {
    "filename": "Map Line.png",
    "name": "Map Line",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "srip",
      "url": "https://www.flaticon.com/authors/srip"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "map point": {
    "filename": "Map Point.png",
    "name": "Map Point",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "marina": {
    "filename": "Marina.png",
    "name": "Marina",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "medical facility": {
    "filename": "Medical Facility.png",
    "name": "Medical Facility",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "smalllikeart",
      "url": "https://www.flaticon.com/authors/smalllikeart"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "mile marker": {
    "filename": "Mile Marker.png",
    "name": "Mile Marker",
    "license": {
      "title": "Public Domain",
      "url": "https://en.wikipedia.org/wiki/Public_domain"
    },
    "maker": {
      "title": "Unknown",
      "url": "#"
    },
    "provider": {
      "title": "Wikimedia",
      "url": "https://commons.wikimedia.org"
    }
  },
  "military": {
    "filename": "Military.png",
    "name": "Military",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Smashicons",
      "url": "https://www.flaticon.com/authors/smashicons"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "mine": {
    "filename": "Mine.png",
    "name": "Mine",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "movie theater": {
    "filename": "Movie Theater.png",
    "name": "Movie Theater",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "multi-cache": {
    "filename": "Multi-Cache.png",
    "name": "Multi-Cache",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Geocaching",
      "url": "https://www.geocaching.com/"
    },
    "provider": {
      "title": "Geocaching",
      "url": "https://www.geocaching.com/"
    }
  },
  "museum": {
    "filename": "Museum.png",
    "name": "Museum",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "ndb": {
    "filename": "NDB.png",
    "name": "NDB",
    "license": {
      "title": "Public Domain",
      "url": "https://en.wikipedia.org/wiki/Public_domain"
    },
    "maker": {
      "title": "Inductiveload",
      "url": "https://commons.wikimedia.org/wiki/User:Inductiveload"
    },
    "provider": {
      "title": "Wikimedia",
      "url": "https://commons.wikimedia.org"
    }
  },
  "navaid, amber": {
    "filename": "Navaid, Amber.png",
    "name": "Navaid, Amber",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Those Icons",
      "url": "https://www.flaticon.com/authors/those-icons/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "navaid, black": {
    "filename": "Navaid, Black.png",
    "name": "Navaid, Black",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Those Icons",
      "url": "https://www.flaticon.com/authors/those-icons/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "navaid, blue": {
    "filename": "Navaid, Blue.png",
    "name": "Navaid, Blue",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Those Icons",
      "url": "https://www.flaticon.com/authors/those-icons/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "navaid, green": {
    "filename": "Navaid, Green.png",
    "name": "Navaid, Green",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Those Icons",
      "url": "https://www.flaticon.com/authors/those-icons/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "navaid, green_red": {
    "filename": "Navaid, Green_Red.png",
    "name": "Navaid, Green_Red",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Those Icons",
      "url": "https://www.flaticon.com/authors/those-icons/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "navaid, green_white": {
    "filename": "Navaid, Green_White.png",
    "name": "Navaid, Green_White",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Those Icons",
      "url": "https://www.flaticon.com/authors/those-icons/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "navaid, orange": {
    "filename": "Navaid, Orange.png",
    "name": "Navaid, Orange",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Those Icons",
      "url": "https://www.flaticon.com/authors/those-icons/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "navaid, red": {
    "filename": "Navaid, Red.png",
    "name": "Navaid, Red",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Those Icons",
      "url": "https://www.flaticon.com/authors/those-icons/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "navaid, red_green": {
    "filename": "Navaid, Red_Green.png",
    "name": "Navaid, Red_Green",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Those Icons",
      "url": "https://www.flaticon.com/authors/those-icons/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "navaid, red_white": {
    "filename": "Navaid, Red_White.png",
    "name": "Navaid, Red_White",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Those Icons",
      "url": "https://www.flaticon.com/authors/those-icons/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "navaid, violet": {
    "filename": "Navaid, Violet.png",
    "name": "Navaid, Violet",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Those Icons",
      "url": "https://www.flaticon.com/authors/those-icons/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "navaid, white": {
    "filename": "Navaid, White.png",
    "name": "Navaid, White",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Those Icons",
      "url": "https://www.flaticon.com/authors/those-icons/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "navaid, white_green": {
    "filename": "Navaid, White_Green.png",
    "name": "Navaid, White_Green",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Those Icons",
      "url": "https://www.flaticon.com/authors/those-icons/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "navaid, white_red": {
    "filename": "Navaid, White_Red.png",
    "name": "Navaid, White_Red",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Those Icons",
      "url": "https://www.flaticon.com/authors/those-icons/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "no entry": {
    "filename": "No Entry.png",
    "name": "No Entry",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 0, blue": {
    "filename": "Number 0, Blue.png",
    "name": "Number 0, Blue",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 0, green": {
    "filename": "Number 0, Green.png",
    "name": "Number 0, Green",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 0, red": {
    "filename": "Number 0, Red.png",
    "name": "Number 0, Red",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 1, blue": {
    "filename": "Number 1, Blue.png",
    "name": "Number 1, Blue",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 1, green": {
    "filename": "Number 1, Green.png",
    "name": "Number 1, Green",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 1, red": {
    "filename": "Number 1, Red.png",
    "name": "Number 1, Red",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 10, blue": {
    "filename": "Number 10, Blue.png",
    "name": "Number 10, Blue",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 10, green": {
    "filename": "Number 10, Green.png",
    "name": "Number 10, Green",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 10, red": {
    "filename": "Number 10, Red.png",
    "name": "Number 10, Red",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 2, blue": {
    "filename": "Number 2, Blue.png",
    "name": "Number 2, Blue",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 2, green": {
    "filename": "Number 2, Green.png",
    "name": "Number 2, Green",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 2, red": {
    "filename": "Number 2, Red.png",
    "name": "Number 2, Red",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 3, blue": {
    "filename": "Number 3, Blue.png",
    "name": "Number 3, Blue",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 3, green": {
    "filename": "Number 3, Green.png",
    "name": "Number 3, Green",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 3, red": {
    "filename": "Number 3, Red.png",
    "name": "Number 3, Red",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 4, blue": {
    "filename": "Number 4, Blue.png",
    "name": "Number 4, Blue",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 4, green": {
    "filename": "Number 4, Green.png",
    "name": "Number 4, Green",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 4, red": {
    "filename": "Number 4, Red.png",
    "name": "Number 4, Red",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 5, blue": {
    "filename": "Number 5, Blue.png",
    "name": "Number 5, Blue",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 5, green": {
    "filename": "Number 5, Green.png",
    "name": "Number 5, Green",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 5, red": {
    "filename": "Number 5, Red.png",
    "name": "Number 5, Red",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 6, blue": {
    "filename": "Number 6, Blue.png",
    "name": "Number 6, Blue",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 6, green": {
    "filename": "Number 6, Green.png",
    "name": "Number 6, Green",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 6, red": {
    "filename": "Number 6, Red.png",
    "name": "Number 6, Red",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 7, blue": {
    "filename": "Number 7, Blue.png",
    "name": "Number 7, Blue",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 7, green": {
    "filename": "Number 7, Green.png",
    "name": "Number 7, Green",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 7, red": {
    "filename": "Number 7, Red.png",
    "name": "Number 7, Red",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 8, blue": {
    "filename": "Number 8, Blue.png",
    "name": "Number 8, Blue",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 8, green": {
    "filename": "Number 8, Green.png",
    "name": "Number 8, Green",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 8, red": {
    "filename": "Number 8, Red.png",
    "name": "Number 8, Red",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 9, blue": {
    "filename": "Number 9, Blue.png",
    "name": "Number 9, Blue",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 9, green": {
    "filename": "Number 9, Green.png",
    "name": "Number 9, Green",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "number 9, red": {
    "filename": "Number 9, Red.png",
    "name": "Number 9, Red",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "oil field": {
    "filename": "Oil Field.png",
    "name": "Oil Field",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "oval, blue": {
    "filename": "Oval, Blue.png",
    "name": "Oval, Blue",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "oval, green": {
    "filename": "Oval, Green.png",
    "name": "Oval, Green",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "oval, red": {
    "filename": "Oval, Red.png",
    "name": "Oval, Red",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "poi": {
    "filename": "POI.png",
    "name": "POI",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "parachute area": {
    "filename": "Parachute Area.png",
    "name": "Parachute Area",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "park": {
    "filename": "Park.png",
    "name": "Park",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "parking area": {
    "filename": "Parking Area.png",
    "name": "Parking Area",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "pharmacy": {
    "filename": "Pharmacy.png",
    "name": "Pharmacy",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "picnic area": {
    "filename": "Picnic Area.png",
    "name": "Picnic Area",
    "license": {
      "title": "Public Domain",
      "url": "https://en.wikipedia.org/wiki/Public_domain"
    },
    "maker": {
      "title": "seamus mcgill",
      "url": "https://commons.wikimedia.org/wiki/User:Mcgill"
    },
    "provider": {
      "title": "Wikimedia",
      "url": "https://commons.wikimedia.org"
    }
  },
  "pin, blue": {
    "filename": "Pin, Blue.png",
    "name": "Pin, Blue",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "pin, green": {
    "filename": "Pin, Green.png",
    "name": "Pin, Green",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "pin, red": {
    "filename": "Pin, Red.png",
    "name": "Pin, Red",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "pizza": {
    "filename": "Pizza.png",
    "name": "Pizza",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "police station": {
    "filename": "Police Station.png",
    "name": "Police Station",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "post office": {
    "filename": "Post Office.png",
    "name": "Post Office",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "private field": {
    "filename": "Private Field.png",
    "name": "Private Field",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "puzzle cache": {
    "filename": "Puzzle Cache.png",
    "name": "Puzzle Cache",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Geocaching",
      "url": "https://www.geocaching.com/"
    },
    "provider": {
      "title": "Geocaching",
      "url": "https://www.geocaching.com/"
    }
  },
  "rv park": {
    "filename": "RV Park.png",
    "name": "RV Park",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Smashicons",
      "url": "https://www.flaticon.com/authors/smashicons"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "radio beacon": {
    "filename": "Radio Beacon.png",
    "name": "Radio Beacon",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "rectangle, blue": {
    "filename": "Rectangle, Blue.png",
    "name": "Rectangle, Blue",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "rectangle, green": {
    "filename": "Rectangle, Green.png",
    "name": "Rectangle, Green",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "rectangle, red": {
    "filename": "Rectangle, Red.png",
    "name": "Rectangle, Red",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "reef": {
    "filename": "Reef.png",
    "name": "Reef",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "residence": {
    "filename": "Residence.png",
    "name": "Residence",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "restaurant": {
    "filename": "Restaurant.png",
    "name": "Restaurant",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "restricted area": {
    "filename": "Restricted Area.png",
    "name": "Restricted Area",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "BoatSafe.com",
      "url": "https://boatsafe.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "restroom": {
    "filename": "Restroom.png",
    "name": "Restroom",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "runway threshold": {
    "filename": "Runway Threshold.png",
    "name": "Runway Threshold",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "saddle": {
    "filename": "Saddle.png",
    "name": "Saddle",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "dayanuyim",
      "url": "https://github.com/dayanuyim"
    },
    "provider": {
      "title": "dayanuyim",
      "url": "https://github.com/dayanuyim"
    }
  },
  "scales": {
    "filename": "Scales.png",
    "name": "Scales",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "scenic area": {
    "filename": "Scenic Area.png",
    "name": "Scenic Area",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "itim2101",
      "url": "https://www.flaticon.com/authors/itim2101"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "school": {
    "filename": "School.png",
    "name": "School",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "seaplane base": {
    "filename": "Seaplane Base.png",
    "name": "Seaplane Base",
    "license": {
      "title": "Public Domain",
      "url": "https://en.wikipedia.org/wiki/Public_domain"
    },
    "maker": {
      "title": "ZyMOS-Bot",
      "url": "https://commons.wikimedia.org/wiki/User:ZyMOS-Bot"
    },
    "provider": {
      "title": "Wikimedia",
      "url": "https://commons.wikimedia.org"
    }
  },
  "shipwreck": {
    "filename": "Shipwreck.png",
    "name": "Shipwreck",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "surang",
      "url": "https://www.flaticon.com/authors/surang"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "shopping center": {
    "filename": "Shopping Center.png",
    "name": "Shopping Center",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "short tower": {
    "filename": "Short Tower.png",
    "name": "Short Tower",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "shower": {
    "filename": "Shower.png",
    "name": "Shower",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Pause08",
      "url": "https://www.flaticon.com/authors/pause08"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "ski resort": {
    "filename": "Ski Resort.png",
    "name": "Ski Resort",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "skiing area": {
    "filename": "Skiing Area.png",
    "name": "Skiing Area",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "skull and crossbones": {
    "filename": "Skull And Crossbones.png",
    "name": "Skull And Crossbones",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "small game": {
    "filename": "Small Game.png",
    "name": "Small Game",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "soft field": {
    "filename": "Soft Field.png",
    "name": "Soft Field",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "square, blue": {
    "filename": "Square, Blue.png",
    "name": "Square, Blue",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "square, green": {
    "filename": "Square, Green.png",
    "name": "Square, Green",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "square, red": {
    "filename": "Square, Red.png",
    "name": "Square, Red",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "stadium": {
    "filename": "Stadium.png",
    "name": "Stadium",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "stump": {
    "filename": "Stump.png",
    "name": "Stump",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "summit": {
    "filename": "Summit.png",
    "name": "Summit",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "swimming area": {
    "filename": "Swimming Area.png",
    "name": "Swimming Area",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "tacan": {
    "filename": "TACAN.png",
    "name": "TACAN",
    "license": {
      "title": "Public Domain",
      "url": "https://en.wikipedia.org/wiki/Public_domain"
    },
    "maker": {
      "title": "Denelson83",
      "url": "https://commons.wikimedia.org/wiki/User:Denelson83"
    },
    "provider": {
      "title": "Wikimedia",
      "url": "https://commons.wikimedia.org"
    }
  },
  "tall tower": {
    "filename": "Tall Tower.png",
    "name": "Tall Tower",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "telephone": {
    "filename": "Telephone.png",
    "name": "Telephone",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "toll booth": {
    "filename": "Toll Booth.png",
    "name": "Toll Booth",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Smashicons",
      "url": "https://www.flaticon.com/authors/smashicons"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "tracback point": {
    "filename": "Tracback Point.png",
    "name": "Tracback Point",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "traditional geocache": {
    "filename": "Traditional Geocache.png",
    "name": "Traditional Geocache",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Geocaching",
      "url": "https://www.geocaching.com/"
    },
    "provider": {
      "title": "Geocaching",
      "url": "https://www.geocaching.com/"
    }
  },
  "trail head": {
    "filename": "Trail Head.png",
    "name": "Trail Head",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "tree stand": {
    "filename": "Tree Stand.png",
    "name": "Tree Stand",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "treed quarry": {
    "filename": "Treed Quarry.png",
    "name": "Treed Quarry",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "triangle, blue": {
    "filename": "Triangle, Blue.png",
    "name": "Triangle, Blue",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "triangle, green": {
    "filename": "Triangle, Green.png",
    "name": "Triangle, Green",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "triangle, red": {
    "filename": "Triangle, Red.png",
    "name": "Triangle, Red",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "truck stop": {
    "filename": "Truck Stop.png",
    "name": "Truck Stop",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Eucalyp",
      "url": "https://www.flaticon.com/authors/eucalyp"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "truck": {
    "filename": "Truck.png",
    "name": "Truck",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "tunnel": {
    "filename": "Tunnel.png",
    "name": "Tunnel",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Eucalyp",
      "url": "https://www.flaticon.com/authors/eucalyp"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "ultralight area": {
    "filename": "Ultralight Area.png",
    "name": "Ultralight Area",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "unknown": {
    "filename": "Unknown.png",
    "name": "Unknown",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "upland game": {
    "filename": "Upland Game.png",
    "name": "Upland Game",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "vor": {
    "filename": "VOR.png",
    "name": "VOR",
    "license": {
      "title": "Public Domain",
      "url": "https://en.wikipedia.org/wiki/Public_domain"
    },
    "maker": {
      "title": "Denelson83",
      "url": "https://commons.wikimedia.org/wiki/User:Denelson83"
    },
    "provider": {
      "title": "Wikimedia",
      "url": "https://commons.wikimedia.org"
    }
  },
  "virtual cache": {
    "filename": "Virtual Cache.png",
    "name": "Virtual Cache",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Geocaching",
      "url": "https://www.geocaching.com/"
    },
    "provider": {
      "title": "Geocaching",
      "url": "https://www.geocaching.com/"
    }
  },
  "water hydrant": {
    "filename": "Water Hydrant.png",
    "name": "Water Hydrant",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "water source": {
    "filename": "Water Source.png",
    "name": "Water Source",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "waterfowl": {
    "filename": "Waterfowl.png",
    "name": "Waterfowl",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "waypoint": {
    "filename": "Waypoint.png",
    "name": "Waypoint",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "webcam cache": {
    "filename": "Webcam Cache.png",
    "name": "Webcam Cache",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Geocaching",
      "url": "https://www.geocaching.com/"
    },
    "provider": {
      "title": "Geocaching",
      "url": "https://www.geocaching.com/"
    }
  },
  "weed bed": {
    "filename": "Weed Bed.png",
    "name": "Weed Bed",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "wherigo cache": {
    "filename": "Wherigo Cache.png",
    "name": "Wherigo Cache",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Geocaching",
      "url": "https://www.geocaching.com/"
    },
    "provider": {
      "title": "Geocaching",
      "url": "https://www.geocaching.com/"
    }
  },
  "winery": {
    "filename": "Winery.png",
    "name": "Winery",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "wrecker": {
    "filename": "Wrecker.png",
    "name": "Wrecker",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "monkik",
      "url": "https://www.flaticon.com/authors/monkik"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  },
  "zoo": {
    "filename": "Zoo.png",
    "name": "Zoo",
    "license": {
      "title": "Creative Commons BY 3.0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "maker": {
      "title": "Freepik",
      "url": "https://www.Freepik.com/"
    },
    "provider": {
      "title": "Flaticon",
      "url": "https://www.flaticon.com/"
    }
  }
}


