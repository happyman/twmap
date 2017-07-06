package main

import "sync"

// A cellChunker gathers batches of cells to send over a []cell channel.
type cellChunker struct {
	buf []cell
	c   chan<- []cell
}

// send will send c over the underlying channel, eventually.
func (cc *cellChunker) send(c cell) {
	buf := cc.buf
	if len(buf) == cap(buf) {
		if len(buf) > 0 {
			cc.c <- buf
		}
		i := chunkPool.Get()
		if i != nil {
			buf = i.([]cell)[:0]
		} else {
			buf = make([]cell, 0, 1024)
		}
	}
	cc.buf = append(buf, c)
}

// flush sends all pending cells, now.
func (cc *cellChunker) flush() {
	if len(cc.buf) > 0 {
		cc.c <- cc.buf
		cc.buf = nil
	}
}

// A pool of unused buffers
var chunkPool sync.Pool
