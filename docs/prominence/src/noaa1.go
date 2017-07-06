package main

import (
	"compress/gzip"
	"io/ioutil"
	"log"
	"os"
)

// Importer for NOAA GLOBE Data
// http://www.ngdc.noaa.gov/mgg/topo/gltiles.html
// Imports one tile (for now).

// The gzip-uncompressed file is just a sequence of
// 16-bit little-endian signed data points.
// A row is 10800 points long.
// There are 6000 rows in equatorial tiles, 4800 rows in polar tiles.
// One tile is ~1/16 of earth.
// -500 = sentinel for ocean
// Each sample is 30 arc seconds "square".  At the equator, that's about 1km square.
// Heights are in meters.

type noaa1 string

func (file noaa1) Init() {
}

func (file noaa1) Bounds() (minx, maxx coord, miny, maxy coord, minz, maxz height) {
	// For the E tile (~western North America)
	return 0, 10800, 0, 6000, -499, 8849
}

func (file noaa1) Pos(c cell) (lat, long, height float64) {
	// for the E tile
	return float64(c.p.x)/120 - 180, 50 - float64(c.p.y)/120, float64(c.z)
}

func (file noaa1) Reader() <-chan []cell {
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
		log.Print("reading " + file)
		buf, err := ioutil.ReadAll(r)
		if err != nil {
			log.Fatal(err)
		}
		if len(buf) != 2*10800*6000 && len(buf) != 2*10800*4800 {
			log.Fatalf("bad # bytes, want %d or %d, got %d", 2*10800*6000, 2*10800*4800, len(buf))
		}
		var chunker cellChunker
		chunker.c = c
		cnt := 0
		for len(buf) > 0 {
			alt := height(int16(int(buf[0]) + int(buf[1])<<8))
			buf = buf[2:]
			if alt != -500 { // -500 is ocean
				chunker.send(cell{point{coord(cnt % 10800), coord(cnt / 10800)}, alt})
			}
			cnt++
		}
		chunker.flush()
		close(c)
	}()
	return c
}
