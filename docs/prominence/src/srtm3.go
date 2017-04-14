package main

import (
	"archive/zip"
	"fmt"
	"io/ioutil"
	"log"
	"path"
	"path/filepath"
	"strings"
	"sync"
)

// Importer for SRTM3 Data
// http://dds.cr.usgs.gov/srtm/version2_1/SRTM3

const hawaii = false
// 3600 for SRTM1, 1200 for SRTM3
const tile_size = 3600

type srtm3 string

func (file srtm3) Init() {
}


func (file srtm3) Bounds() (minx, maxx coord, miny, maxy coord, minz, maxz height) {
	if hawaii {
		return 19 * tile_size, 26 * tile_size, (90 - 23) * tile_size, (90 - 18) * tile_size, -499, 8849
	}
	return 0, 360 * tile_size, 0, 180 * tile_size, -449, 8849
}

func (file srtm3) Pos(c cell) (lat, long, height float64) {
	return float64(c.p.x)/tile_size - 180, 90 - float64(c.p.y)/tile_size, float64(c.z)
}

func (file srtm3) Reader() <-chan []cell {
	// Put files to be loaded into a channel
	work := make(chan string)
	go func() {
		dir := string(file)
		continents, err := ioutil.ReadDir(dir)
		if err != nil {
			log.Fatal(err)
		}
		for _, continent := range continents {
			if continent.Name() == "index.html" {
				continue
			}
			subdir := filepath.Join(dir, continent.Name())
			files, err := ioutil.ReadDir(subdir)
			if err != nil {
				log.Fatal(err)
			}
			for _, f := range files {
				if strings.HasSuffix(f.Name(), ".hgt.zip") {
					work <- filepath.Join(subdir, f.Name())
				}
			}
		}
		close(work)
	}()

	// Return channel
	c := make(chan []cell, *P)

	// Use P workers to do all the decompression.
	var wg sync.WaitGroup
	wg.Add(*P)
	for i := 0; i < *P; i++ {
		go func() {
			var chunker cellChunker
			chunker.c = c
			for name := range work {
				log.Print("reading " + name)

				// Parse tile name
				var ns, ew string
				var n, e int
				fmt.Sscanf(path.Base(name), "%1s%d%1s%d", &ns, &n, &ew, &e)
				if ns == "S" {
					n = -n
				}
				if ew == "W" {
					e = -e
				}
				log.Printf("srtmFileName for %+v,%+v: %s", n, e, name)
				// Restrict to Hawaii
				if hawaii {
					if n <= 18 || n >= 23 {
						continue
					}
					if e <= -161 || e >= -154 {
						continue
					}
				}

				// Extract tile data from zip file
				z, err := zip.OpenReader(name)
				if err != nil {
					panic("openreader")
					log.Fatal(err)
				}
				for _, sf := range z.File {
					if sf.Name[0] == '.' {
						continue // Junk in N21E034.hgt.zip
					}
					f, err := sf.Open() // always a zip of a single file
					if err != nil {
						panic("open")
						log.Fatal(err)
					}
					b, err := ioutil.ReadAll(f)
					if err != nil {
						panic("readall")
						log.Fatal(err)
					}

					// Figure out where we start
					x := tile_size * (180 + e)
					y := tile_size * (90 - n)

					// Note: tiles are named by their lower left corner.  But the data starts in
					// the upper left corner.  Plus tiles have one row overlap.
					// Adjust for all of that.
					y -= tile_size

					for i := 0; i < tile_size; i++ {
						for j := 0; j < tile_size; j++ {
							z := height(int16(int(b[0])<<8 + int(b[1])))
							b = b[2:]
							if z == 0 || z < 0 {
								continue // ocean
							}
							if z == -32768 {
								continue // data voids - is this the right thing to do?
							}
							if z == 32767 {
								continue
							}
							chunker.send(cell{point{coord(x + j), coord(y + i)}, z})
						}
						// tiles have 1201 columns - the last column is equal to
						// the first column of the next tile.
						b = b[2:]
					}
					f.Close()
				}
				z.Close()
			}
			chunker.flush()
			wg.Done()
		}()
	}
	go func() {
		wg.Wait()
		close(c)
	}()
	return c
}
