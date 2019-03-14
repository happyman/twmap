#!/usr/bin/env node

var geojsontoosm = require('./'),
    opt = require('optimist')
        .usage('Usage: $0 FILE')
        .boolean('version').describe('version','display software version')
        .boolean('help').describe('help','print this help message'),
    argv = opt.argv,
    fs = require('fs'),
    geojsonStream = require('geojson-stream'),
    concat = require('concat-stream'),
    pack = require('./package.json');

if (argv.help) {
    return opt.showHelp();
}
if (argv.version) {
    process.stdout.write(pack.version+'\n');
    return;
}

var filename = argv._[0] || '';

var datastream = (filename ? fs.createReadStream(filename) : process.stdin);

datastream
.pipe(geojsonStream.parse())
.pipe(concat(function(data) {
    convert(data)
}))

function convert(data) {
    var result = geojsontoosm(data);
    process.stdout.write(result);
}
