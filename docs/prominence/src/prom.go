package main

import (
	"fmt"
	"log"
	"unsafe"
)

// Prominence computation.
//
// The prominence of a peak is the least amount of
// altitude you must lose to walk to a higher peak.
//
// Our algorithm for computing prominences I'll call the
// After Noah's Flood algorithm.  We start with the world
// completely covered in water and slowly drain that water.
// As islands appear, we keep track of their extent.  When
// enough water has drained to join two islands, then the
// prominence of the highest point on the lower island is
// its height above the water when the joining happens.
// The key col for that highest point is the point of land
// where the two islands just joined.
//
// Our world is a 2d grid of altitude samples.  When we
// say "walk" above, we mean travel from a sample to an
// adjacent sample in one of the 4 cardinal directions.
// (Allowing 8 directions will cost more and doesn't seem
// like it would change anything materially.)
//
// We break altitude ties arbitrarily.  Internally, between
// two equal-altitude samples the first one processed is
// considered higher.

const debug = false

type coord int32
type height int32

// A point is a location in a 2d grid.
type point struct {
	x, y coord
}

func (p point) String() string {
	return fmt.Sprintf("{%d,%d}", p.x, p.y)
}

// A cell is a location in a 2d grid together with its
// altitude in the 3rd dimension.
type cell struct {
	p point
	z height
}

// TODO: pack cells into a 64-bit number?
// For 1-arc-sec data and 1-meter height resolution,
// we could use 21 bits for coordinates and 14 for height.
// 21+21+14 = 56.  That would use 8 instead of 12 bytes
// per cell.

func (c cell) String() string {
	return fmt.Sprintf("%s:%d", c.p, c.z)
}

// An island is a contiguous group of points which have an altitude
// greater than or equal to the altitude currently being processed.
type island struct {
	// peak is the highest point in the island
	peak cell
	// # of cells comprising this island
	size int64
	// when this island is joined to another, parent points to the containing island.
	parent *island
}

// root returns the top island to which i has been joined.
func (i *island) root() *island {
	p := i.parent
	if p == nil {
		// i is a root island
		return i
	}
	gp := p.parent
	if gp != nil {
		// i has at least a grandparent.  Do path compression
		// (ala disjoint set union) so that we point directly to the root.
		// This is important not only for the speed of root(), but for
		// making sure that old islands that are engulfed in other
		// islands are eventually collected by the garbage collector.
		// TODO: is path compression enough to achieve that, or do
		// we need to do something more aggressive?
		p = p.root()
		i.parent = p
	}
	return p
}

// An islandBorder records a border point of an island and
// indicates how many edges of that point are current island edges.
type islandBorder struct {
	i *island
	n int
}

// An islandCount represents an island neighbor of a cell and its multipicity.
type islandCount struct {
	i *island
	n int
}

// computeProminence computes the prominence of all the peaks returned by r.
// computeProminence will call f with info about each peak:
//   peak = local maximum
//   col = key col for that peak
//   dom = dominating peak
//   island = is top of an island (or continent).
func computeProminence(r <-chan []cell, minx, maxx coord, f func(peak, col, dom cell, size int64, island bool)) {
	// Turns out patches don't really help much.
	// At least for NOAA-OCEAN, the average patch
	// size is 1.15.  For finer grids it may help more and
	// for finer altitude distinctions it may help less.
	/*
		for _, patch := range makePatches(cells) {
			fmt.Printf("%d:", patch.z)
			for _, p := range patch.border {
				fmt.Printf(" %s", p)
			}
			fmt.Println()
		}
	*/

	// Sort data in descending altitude.
	r = cellSort(r)

	// Keep track of the border of all the current islands.
	// This is the major data structure that needs to be kept
	// in memory.  Hopefully it doesn't get too big.
	// On the NOAA-GLOBE data, the maximum size of m is only
	// about 2% of the total number of samples.
	m := newmap()
	maxm := 0

	var neighborStore [4]islandCount

	// Process all of the cells in sorted order.
	for cslice := range r {
		for _, c := range cslice {
			if debug {
				fmt.Printf("@%v\n", c)
			}
			if m.size() > maxm {
				maxm = m.size()
			}
			// Find unique neighboring islands of c plus their frequency.
			neighbors := neighborStore[:0]
			var adj int8
		outer:
			for _, d := range [4][2]coord{{0, 1}, {0, -1}, {1, 0}, {-1, 0}} {
				// Find out which island is in this direction.
				p := point{c.p.x + d[0], c.p.y + d[1]}

				// Earth wraps around left-right
				if p.x == maxx {
					p.x = minx
				}
				if p.x == minx-1 {
					p.x = maxx - 1
				}

				i := m.find(p)
				if i == nil {
					// No island is in this direction.
					continue
				}
				i = i.root()

				// Add i to list of neighbors of the current cell c.
				adj++
				for a := range neighbors {
					if i == neighbors[a].i {
						neighbors[a].n++
						continue outer
					}
				}
				neighbors = append(neighbors, islandCount{i, 1})
			}

			switch len(neighbors) {
			case 0:
				// Cell makes a new island.
				i := &island{peak: c, size: 1, parent: nil}
				if debug {
					fmt.Printf("  new island %p\n", i)
				}
				m.insert(c.p, i, 4)

			case 1:
				// Cell attaches to a single island.
				i := neighbors[0].i
				if debug {
					fmt.Printf("  enlarge island %p\n", i)
				}
				i.size++
				if adj != 4 {
					m.insert(c.p, i, 4-adj)
				}

			default:
				// Connecting 2 or more islands.  This case identifies
				// a key col.  It is the key col for all the non-dominant
				// islands that are being joined.

				// Find the dominant island.
				i := neighbors[0].i
				for _, q := range neighbors[1:] {
					if q.i.peak.z > i.peak.z {
						i = q.i
					}
				}

				// Emit c as the col for non-dominant islands.
				// Join non-dominant islands to i.
				for _, z := range neighbors {
					j := z.i
					if i == j {
						continue
					}
					if debug {
						fmt.Printf("  col (joining %p into %p)\n", j, i)
						fmt.Printf("  prominence of %v is %d (key col %v to %v)\n", j.peak, j.peak.z-c.z, c, i.peak)
					}
					if j.peak.z-c.z > 0 {
						f(j.peak, c, i.peak, i.size, false)
					}
					// Note: the j.peak.z-c.z == 0 case is unfortunate.
					// If we have a situation like 334 we generate an island
					// for the leftmost 3, then join it into the 4 island when
					// we process the middle 3 (if we happen to do the 3s in
					// that order).  If we had processed the 3s in the opposite
					// order, we would have never generated that temporary
					// island and incurred that additional overhead.
					// I tried joining patches of connected uniform height areas
					// (see patch.go) but the case when we allocate 0-prominence
					// islands just doesn't happen that often.

					// Join islands.  We do joining lazily (see island.root()).
					j.parent = i
					i.size += j.size
				}

				// Add col point itself to the dominant island.
				i.size++
				if adj != 4 {
					m.insert(c.p, i, 4-adj)
				}
			}
		}
		chunkPool.Put(cslice)
	}

	//fmt.Println("remaining border")
	//for p, b := range m {
	//	fmt.Printf("  %v %d %p\n", p, b.n, b.i)
	//}

	// Report remaining islands, which are now islands in the
	// real sense.  Their prominence is equal to their altitude.
	islands := map[*island]struct{}{}
	for _, i := range m.contents() {
		i = i.root()
		if _, ok := islands[i]; ok {
			// already know about this island
			continue
		}
		islands[i] = struct{}{}
		if debug {
			fmt.Printf("island %p: @%v\n", i, i.peak)
		}
		f(i.peak, cell{}, cell{}, i.size, true)
	}

	size := int(unsafe.Sizeof(point{}) + unsafe.Sizeof(islandBorder{})) // one entry
	size *= maxm                                                        // all entries
	size *= 2                                                           // approx. map overhead
	log.Printf("approx mem used: %d MB\n", size/(1<<20))
}
