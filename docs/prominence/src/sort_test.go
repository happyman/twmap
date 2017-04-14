package main

import (
	"math/rand"
	"reflect"
	"testing"
)

func testSort(t *testing.T, cells []cell) {
	// sort using cellSort
	var cells2 []cell
	r := cellSort(simpleReader(cells))
	for cslice := range r {
		for _, c := range cslice {
			cells2 = append(cells2, c)
		}
	}

	// Check ordering.
	if len(cells2) != len(cells) {
		t.Errorf("lengths don't match %d %d", len(cells2), len(cells))
	}
	for i := 0; i < len(cells2)-1; i++ {
		if cells2[i].z < cells2[i+1].z {
			t.Errorf("bad sort %d %v %v\n", i, cells2[i], cells2[i+1])
		}
	}

	// Check to make sure nothing was lost or added.
	m := map[cell]struct{}{}
	m2 := map[cell]struct{}{}
	for _, c := range cells {
		m[c] = struct{}{}
	}
	for _, c := range cells2 {
		m2[c] = struct{}{}
	}
	if !reflect.DeepEqual(m, m2) {
		t.Errorf("bad sort")
	}
}

func TestCellSort(t *testing.T) {
	cells := []cell{
		{point{0, 0}, 5},
		{point{1, 1}, 3},
		{point{2, 2}, 7},
		{point{3, 3}, 2},
		{point{4, 4}, 8},
		{point{5, 5}, 2},
		{point{6, 6}, 1},
		{point{7, 7}, 2},
		{point{8, 8}, 7},
		{point{9, 9}, 9},
	}
	testSort(t, cells)
}

func TestCellSortBig(t *testing.T) {
	rnd := rand.New(rand.NewSource(127))
	var cells []cell
	for i := 0; i < 100000; i++ {
		x := coord(rnd.Intn(1000))
		y := coord(rnd.Intn(1000))
		z := height(rnd.Intn(100))
		cells = append(cells, cell{point{x, y}, z})
	}
	testSort(t, cells)
}
