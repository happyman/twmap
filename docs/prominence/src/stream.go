package main

import (
	"bufio"
	"encoding/binary"
	"io"
	"log"
	"math"
)

// Stream importer.  Used to import data from sources which are
// hard to generate from Go.

// Stream format:
//   minx, maxx, miny, maxy, minz, maxz: 32-bit signed little-endian
//   scalex, offsetx, scaley, offsety, scalez, offsetz: 64-bit float little-endian
//   [x y z]*n: 32-bit signed little-endian samples

type stream struct {
	r io.Reader     // underlying reader
	b *bufio.Reader // buffered wrapper

	minx, maxx, miny, maxy                            coord
	minz, maxz                                        height
	scalex, offsetx, scaley, offsety, scalez, offsetz float64

	reader bool
}

func (s *stream) Init() {
	s.b = bufio.NewReader(s.r)

	// read header
	var b [6*4 + 6*8]byte
	n, err := s.b.Read(b[:])
	if err != nil {
		log.Fatal(err)
	}
	if n != len(b) {
		log.Fatal("not enough header")
	}
	bo := binary.LittleEndian
	s.minx = coord(bo.Uint32(b[0:4]))
	s.maxx = coord(bo.Uint32(b[4:8]))
	s.miny = coord(bo.Uint32(b[8:12]))
	s.maxy = coord(bo.Uint32(b[12:16]))
	s.minz = height(bo.Uint32(b[16:20]))
	s.maxz = height(bo.Uint32(b[20:24]))
	s.scalex = math.Float64frombits(bo.Uint64(b[24:32]))
	s.offsetx = math.Float64frombits(bo.Uint64(b[32:40]))
	s.scaley = math.Float64frombits(bo.Uint64(b[40:48]))
	s.offsety = math.Float64frombits(bo.Uint64(b[48:56]))
	s.scalez = math.Float64frombits(bo.Uint64(b[56:64]))
	s.offsetz = math.Float64frombits(bo.Uint64(b[64:72]))
}

func (s *stream) Bounds() (minx, maxx coord, miny, maxy coord, minz, maxz height) {
	return s.minx, s.maxx, s.miny, s.maxy, s.minz, s.maxz
}

func (s *stream) Pos(c cell) (lat, long, height float64) {
	return float64(c.p.x)*s.scalex + s.offsetx,
		float64(c.p.y)*s.scaley + s.offsety,
		float64(c.z)*s.scalez + s.offsetz
}

func (s *stream) Reader() <-chan []cell {
	if s.reader {
		panic("can't reuse stream reader")
	}
	s.reader = true

	c := make(chan []cell, *P)
	go func() {
		var chunker cellChunker
		chunker.c = c
		bo := binary.LittleEndian
		var b [12]byte
		for {
			cnt := 0
			for {
				n, err := s.b.Read(b[cnt:])
				if err == io.EOF {
					if cnt != 0 {
						log.Fatalf("not enough data %d", n)
					}
					// no more data
					chunker.flush()
					close(c)
					return
				}
				if err != nil {
					log.Fatal(err)
				}
				cnt += n
				if cnt == len(b) {
					break
				}
			}
			x := coord(bo.Uint32(b[0:4]))
			y := coord(bo.Uint32(b[4:8]))
			z := height(bo.Uint32(b[8:12]))
			chunker.send(cell{point{x, y}, z})
		}
	}()
	return c
}
