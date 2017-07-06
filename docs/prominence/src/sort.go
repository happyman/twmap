package main

import (
	"io/ioutil"
	"log"
	"os"
	"sort"
	"sync"
	"unsafe"
)

const bufSize = 1 << 15

type fileRange struct {
	off int64
	len int
}

// cellSort externally sorts the cells in descending altitude order.
// Returns a channel producing the sorted data.
func cellSort(r <-chan []cell) <-chan []cell {
	// Make a temp file for the external sort.
	f, err := ioutil.TempFile(*tmpDirPtr, "prominenceAltitudeSort")
	if err != nil {
		log.Fatal(err)
	}
	// Remove the file to keep the filesystem clean.
	// Note: this call deletes the file before we've even used it.
	// That's ok, we keep the file open.  At least most OSes do
	// the right thing here.
	os.Remove(f.Name())

	// Lock on the temporary file and the range map, below.
	var lock sync.Mutex
	var fileLen int64

	// We'll divide up the input into contiguous chunks of cells
	// that all have the same altitude, then write that chunk to
	// the temporary file.  This map keeps track of which altitudes
	// are where.
	ranges := map[height][]fileRange{}

	// Step 1: Divide input data into stripes.  We do this so that
	// any particular altitude is buffered by only one worker.
	var stripes = make([]chan []cell, *P)
	for i := 0; i < *P; i++ {
		stripes[i] = make(chan []cell, 1)
	}
	// Read input data, send to the correct stripe.
	var wg1 sync.WaitGroup
	wg1.Add(*P)
	for i := 0; i < *P; i++ {
		go func() {
			k := make([]cellChunker, *P)
			for j := 0; j < *P; j++ {
				k[j].c = stripes[j]
			}
			for cslice := range r {
				for _, c := range cslice {
					k[uint(c.z)%uint(*P)].send(c)
				}
				chunkPool.Put(cslice)
			}
			for j := 0; j < *P; j++ {
				k[j].flush()
			}
			wg1.Done()
		}()
	}
	// When all data is striped, close the stripe channels.
	go func() {
		wg1.Wait()
		for i := 0; i < *P; i++ {
			close(stripes[i])
		}
	}()

	// Step 2: Read stripe, split into individual altitude buffers.
	// When buffers fill up, write the buffer to the temporary file.
	var wg2 sync.WaitGroup
	wg2.Add(*P)
	for i := 0; i < *P; i++ {
		i := i
		go func() {
			// Keep a write buffer for each altitude.
			wbufs := map[height]*wbuf{}
			for cslice := range stripes[i] {
				for _, c := range cslice {
					w := wbufs[c.z]
					if w == nil {
						w = &wbuf{}
						wbufs[c.z] = w
					}
					if w.n == len(w.buf) {
						// Write full buffer to the temp file.
						b := int(unsafe.Sizeof(w.buf))
						s := *(*[]byte)(unsafe.Pointer(&slice{unsafe.Pointer(&w.buf), b, b}))
						lock.Lock()
						_, err = f.Write(s)
						if err != nil {
							log.Fatal(err)
						}
						ranges[c.z] = append(ranges[c.z], fileRange{fileLen, b})
						fileLen += int64(b)
						lock.Unlock()
						w.n = 0
					}
					w.buf[w.n] = c.p
					w.n++
				}
				chunkPool.Put(cslice)
			}
			// Write any remaining parital buffers to the temp file.
			for h, w := range wbufs {
				b := w.n * int(unsafe.Sizeof(point{}))
				s := *(*[]byte)(unsafe.Pointer(&slice{unsafe.Pointer(&w.buf), b, b}))
				lock.Lock()
				_, err = f.Write(s)
				if err != nil {
					log.Fatal(err)
				}
				ranges[h] = append(ranges[h], fileRange{fileLen, b})
				fileLen += int64(b)
				lock.Unlock()
			}
			wg2.Done()
		}()
	}
	wg2.Wait()
	log.Printf("temp file size: %d", fileLen)

	// Step 3: Compute descending altitude order.
	alts := make([]int, 0, len(ranges))
	for h := range ranges {
		alts = append(alts, int(h))
	}
	sort.Sort(sort.Reverse(sort.IntSlice(alts)))

	// Step 4: Make a channel and shove the sorted data into it.
	c := make(chan []cell, 1)
	go func() {
		var points [bufSize]point
		var chunker cellChunker
		chunker.c = c
		for _, a := range alts {
			h := height(a)

			// Read chunks from temporary file.
			for _, rng := range ranges[h] {
				b := rng.len
				if b > int(unsafe.Sizeof(points)) {
					log.Fatal("block too big")
				}
				s := *(*[]byte)(unsafe.Pointer(&slice{unsafe.Pointer(&points), b, b}))
				_, err := f.ReadAt(s, rng.off)
				if err != nil {
					log.Fatal(err)
				}
				for i := 0; i < b/int(unsafe.Sizeof(point{})); i++ {
					chunker.send(cell{points[i], h})
				}
			}
		}
		chunker.flush()
		close(c)
	}()
	return c
}

type wbuf struct {
	buf [bufSize]point
	n   int
}

type slice struct {
	p unsafe.Pointer
	l int
	c int
}
