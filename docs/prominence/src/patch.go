package main

import (
	"fmt"
	"log"
)

// A patch is a contiguous chunk of points with the same altitude.
// The first step of our algorithm is to divide the
// world up into patches.  We do this because the world
// has lots of flat areas, and those are easier to process
// in bulk instead of point-by-point.
// In particular it prevents creating islands that have
// prominence zero.

// TODO: not used.  Delete?  Make work?

type patch struct {
	alt    height
	border []point
}

// makePatches makes patches from input cells.
// The returned slice of patches is sorted from highest
// to lowest altitude.
func makePatches(cells []cell) []patch {
	// TODO: this code is in-memory.  For a real
	// whole-earth dataset, we probably need to
	// use disk.
	// (1 arc-sec -> 840 billion points -> 9.4TB, and we can get 1/3 arc-sec data or better?)
	// (NOTE: reduce that estimate by 2/3 because ocean doesn't count.)
	var patches []patch

	// Step 1: find min/max altitude
	min := cells[0].z
	max := min
	for _, c := range cells[1:] {
		if c.z < min {
			min = c.z
		}
		if c.z > max {
			max = c.z
		}
	}
	fmt.Printf("min:%d max:%d\n", min, max)

	// Step 2: count altitudes
	cnt := make([]int64, max-min+1)
	for _, c := range cells {
		cnt[c.z-min]++
	}

	// Step 3: find cumulative count of all points above each altitude
	above := make([]int64, max-min+1)
	var sum int64
	for i := max; i >= min; i-- {
		above[i-min] = sum
		sum += cnt[i-min]
	}
	if sum != int64(len(cells)) {
		log.Fatalf("bad sum %d %d", sum, len(cells))
	}

	// Step 2: shard by altitude
	alt := make([][]point, max-min+1)
	backing := make([]point, len(cells))
	for i := max; i >= min; i-- {
		alt[i-min] = backing[above[i-min]:][:0]
	}
	for _, c := range cells {
		alt[c.z-min] = append(alt[c.z-min], c.p)
	}

	// Step 3: find patches at each altitude
	backing2 := make([]point, len(cells))
	m := map[point]struct{}{}
	for a := max; a >= min; a-- {
		// Add all points at this altitude to a map.
		for _, p := range alt[a-min] {
			m[p] = struct{}{}
		}
		// Find connected components at altitude a.
		startq := alt[a-min]
		for len(m) > 0 {
			// Find a point we haven't used yet to start.
			var start point
			for {
				p := startq[0]
				if _, ok := m[p]; ok {
					start = p
					break
				}
				startq = startq[1:]
			}

			// Flood fill from starting point.
			q := backing2[:0]
			delete(m, start)
			q = append(q, start)
			i := 0
			for i < len(q) {
				p := q[i]
				i++
				for _, d := range [4][2]coord{{0, 1}, {0, -1}, {1, 0}, {-1, 0}} {
					x := point{p.x + d[0], p.y + d[1]}
					if _, ok := m[x]; !ok {
						continue
					}
					delete(m, x)
					q = append(q, x)
				}
			}
			// q is now a patch
			// TODO: border, not whole
			//fmt.Printf("patch @%d:", a)
			//for _, p := range q {
			//	fmt.Printf(" %s", p)
			//}
			//fmt.Println()
			patches = append(patches, patch{alt: a, border: q})
			backing2 = backing2[len(q):]
		}
	}
	fmt.Printf("  cells: %d\n", len(cells))
	fmt.Printf("patches: %d\n", len(patches))
	panic("stop")
	return patches
}
