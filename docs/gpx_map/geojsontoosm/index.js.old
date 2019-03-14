var jxon = require('jxon');


function geojsontoosm(geojson) {
    var features = geojson.features || (geojson.length>0 ? geojson : [geojson])

    var nodes = [], nodesIndex = {},
        ways = [],
        relations = [];

    features.forEach(function(feature) { // feature can also be a pure GeoJSON geometry object
        // todo: GeometryCollection?
        var properties = feature.properties || {},
            geometry = feature.geometry || feature
        // todo: validity check
        // todo: ids if (feature.id && feature.id.match(/^(node|way|relation)\/(\d+)$/)) id = …
        switch (geometry.type) {
        case "Point":
            processPoint(geometry.coordinates, properties, nodes, nodesIndex)
        break;
        case "LineString":
            processLineString(geometry.coordinates, properties, ways, nodes, nodesIndex)
        break;
        case "Polygon":
            processMultiPolygon([geometry.coordinates], properties, relations, ways, nodes, nodesIndex)
        break;
        case "Multipolygon":
            processMultiPolygon(geometry.coordinates, properties, relations, ways, nodes, nodesIndex)
        break;
        default:
            console.error("unknown or unsupported geometry type:", geometry.type);
        }
    });

    //console.log(nodes, ways, relations)
    var lastNodeId = -1,
        lastWayId = -1,
        lastRelationId = -1
    function jxonTags(tags) {
        var res = []
        for (var k in tags) {
            res.push({
                "@k": k,
                "@v": tags[k]
            })
        }
        return res
    }
    var jxonData = {
        osm: {
            "@version": "0.6",
            "@generator": "geojsontoosm",
            "node": nodes.map(function(node) {
                node.id = lastNodeId--
                return {
                    "@id": node.id,
                    "@lat": node.lat,
                    "@lon": node.lon,
                    // todo: meta
                    "tag": jxonTags(node.tags)
                }
            }),
            "way": ways.map(function(way) {
                way.id = lastWayId--
                return {
                    "@id": way.id,
                    "nd": way.nodes.map(function(nd) { return {"@ref": nd.id} }),
                    "tag": jxonTags(way.tags)
                }
            }),
            "relation": relations.map(function(relation) {
                relation.id = lastRelationId--
                return {
                    "@id": relation.id,
                    "member": relation.members.map(function(member) {
                        return {
                            "@type": member.type,
                            "@ref": member.elem.id,
                            "@role": member.role
                        }
                    }),
                    "tag": jxonTags(relation.tags)
                    // todo: meta
                }
            })
        } 
    }
    // todo: sort by id
    return jxon.jsToString(jxonData)
}

function getNodeHash(coords) {
    return JSON.stringify(coords)
}
function emptyNode(coordinates, properties) {
    return {
        tags: properties,
        lat: coordinates[1],
        lon: coordinates[0]
    }
    // todo: meta
    // todo: move "nodesIndex[hash] = node" here
}

function processPoint(coordinates, properties, nodes, nodesIndex) {
    var hash = getNodeHash(coordinates),
        node
    if (!(node = nodesIndex[hash])) {
        nodes.push(node = emptyNode(coordinates, properties))
        nodesIndex[hash] = node
    } else {
        for (var k in properties) {
            node.tags[k] = properties[k]
        }
        // todo: meta
    }
}

function processLineString(coordinates, properties, ways, nodes, nodesIndex) {
    var way = {
        tags: properties,
        nodes: []
    }
    ways.push(way)
    // todo: meta
    coordinates.forEach(function(point) {
        var hash = getNodeHash(point),
            node
        if (!(node = nodesIndex[hash])) {
            nodes.push(node = emptyNode(point, {}))
            nodesIndex[hash] = node
        }
        way.nodes.push(node)
    })
}

function processMultiPolygon(coordinates, properties, relations, ways, nodes, nodesIndex) {
    // simple area with only 1 ring: -> closed way
    if (coordinates.length === 1 && coordinates[0].length === 1)
        return processLineString(coordinates[0][0], properties, ways, nodes, nodesIndex)
    // multipolygon
    var relation = {
        tags: properties,
        members: []
    }
    relation.tags["type"] = "multipolygon"
    relations.push(relation)
    // todo: meta
    coordinates.forEach(function(polygon) {
        polygon.forEach(function(ring, index) {
            var way = {
                tags: {},
                nodes: []
            }
            ways.push(way)
            relation.members.push({
                elem: way,
                type: "way",
                role: index===0 ? "outer" : "inner"
            })
            ring.forEach(function(point) {
                var hash = getNodeHash(point),
                    node
                if (!(node = nodesIndex[hash])) {
                    nodes.push(node = emptyNode(point, {}))
                    nodesIndex[hash] = node
                }
                way.nodes.push(node)
            })
        })
    })
}

module.exports = geojsontoosm;
