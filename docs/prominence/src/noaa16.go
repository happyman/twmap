package main

import (
	"archive/tar"
	"compress/gzip"
	"io"
	"io/ioutil"
	"log"
	"os"
	"strings"
)

// Importer for NOAA GLOBE Data
// http://www.ngdc.noaa.gov/mgg/topo/gltiles.html

// The gzip-uncompressed files are just sequences of
// 16-bit little-endian signed data points.
// A row is 10800 points long.
// There are 6000 rows in equatorial tiles, 4800 rows in polar tiles.
// One tile is ~1/16 of earth.
// -500 = sentinel for ocean
// Each sample is 30 arc seconds "square".  At the equator, that's about 1km square.
// Heights are in meters.

var offsets = map[byte]struct{ size, x, y int }{
	'a': {10800 * 4800, 0, 0},
	'b': {10800 * 4800, 10800, 0},
	'c': {10800 * 4800, 10800 * 2, 0},
	'd': {10800 * 4800, 10800 * 3, 0},
	'e': {10800 * 6000, 0, 4800},
	'f': {10800 * 6000, 10800, 4800},
	'g': {10800 * 6000, 10800 * 2, 4800},
	'h': {10800 * 6000, 10800 * 3, 4800},
	'i': {10800 * 6000, 0, 4800 + 6000},
	'j': {10800 * 6000, 10800, 4800 + 6000},
	'k': {10800 * 6000, 10800 * 2, 4800 + 6000},
	'l': {10800 * 6000, 10800 * 3, 4800 + 6000},
	'm': {10800 * 4800, 0, 4800 + 6000*2},
	'n': {10800 * 4800, 10800, 4800 + 6000*2},
	'o': {10800 * 4800, 10800 * 2, 4800 + 6000*2},
	'p': {10800 * 4800, 10800 * 3, 4800 + 6000*2},
}

type noaa16 string

func (file noaa16) Init() {
}

func (file noaa16) Bounds() (minx, maxx coord, miny, maxy coord, minz, maxz height) {
	return 0, 10800 * 4, 0, 4800*2 + 6000*2, -499, 8849
}

func (file noaa16) Pos(c cell) (lat, long, height float64) {
	return float64(c.p.x)/120 - 180, 90 - float64(c.p.y)/120, float64(c.z)
}

func (file noaa16) Reader() <-chan []cell {
	c := make(chan []cell, 1)
	go func() {
		f, err := os.Open(string(file))
		if err != nil {
			log.Fatal(err)
		}
		r, err := gzip.NewReader(f)
		if err != nil {
			log.Fatal(err)
		}
		t := tar.NewReader(r)
		var chunker cellChunker
		chunker.c = c
		for {
			hdr, err := t.Next()
			if err == io.EOF {
				break // no more files
			}
			if err != nil {
				log.Fatal(err)
			}
			name := hdr.Name
			if !strings.HasSuffix(name, "10g") {
				// Skip non-data files.
				// Why both a10g and a11g?
				continue
			}
			log.Print("reading " + name)
			off := offsets[name[len(name)-4]]
			buf, err := ioutil.ReadAll(t)
			if err != nil {
				log.Fatal(err)
			}
			if len(buf) != 2*off.size {
				log.Fatalf("bad # bytes, want %d got %d", 2*off.size, len(buf))
			}
			cnt := 0
			for len(buf) > 0 {
				alt := height(int16(int(buf[0]) + int(buf[1])<<8))
				buf = buf[2:]
				if alt != -500 { // -500 is ocean
					chunker.send(cell{point{coord(off.x + cnt%10800), coord(off.y + cnt/10800)}, alt})
				}
				cnt++
			}
		}
		chunker.flush()
		close(c)
	}()
	return c
}
