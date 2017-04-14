package main

// A dataSet provides an interface to topography data
// about the world.  A dataset is conceptually a 2d grid
// of altitude samples.
// Samples are identified by their (dense) grid coordinates.
// Samples are adjacent if they differ by exactly 1 in
// exactly one coordinate.
// Samples which are at sea level do not need to be
// considered part of the dataSet.
type dataSet interface {
	// Init performs any once-only initialization.
	Init()

	// Bounds returns bounds on the returned cells.
	// minx <= x < maxx
	// miny <= y < maxy
	// minz <= z < maxz
	Bounds() (minx, maxx, miny, maxy coord, minz, maxz height)

	// Returns a channel of all samples in the data set.
	// For efficiency, we send a chunk of samples at a time.
	// Multiple calls to Reader return independent channels.
	Reader() <-chan []cell

	// Pos converts from the internal integral coordinate system
	// to standard coordinates.  lat and long are in degrees.
	// height is in meters above sea level.
	// lat is degrees north of the equator (-90 to 90).
	// long is degrees east from the prime meridian (-180 to 180).
	Pos(c cell) (lat, long, height float64)
}
