package main

import (
	"fmt"
	"sort"
	"testing"
)

// parseTest converts a ASCII representation of an altitude map
// (characters 0-9) and returns the cells comprising that map.
func parseTest(s string) []cell {
	// Trim /n.
	for s[0] == '\n' {
		s = s[1:]
	}
	for s[len(s)-1] == '\n' {
		s = s[:len(s)-1]
	}

	// Read altitude grid, make cells from it.
	var r []cell
	var p point
	for _, c := range s {
		if c == '\n' {
			p = point{0, p.y + 1}
			continue
		}
		r = append(r, cell{p, height(c - '0')})
		p = point{p.x + 1, p.y}
	}
	return r
}

// A prominenceRecord is pne prominence calculation result.
type prominenceRecord struct {
	peak   cell
	col    cell
	dom    cell
	island bool
}

// equal reports whether a and b are equal prominence results.
func equal(a, b []prominenceRecord) bool {
	if len(a) != len(b) {
		return false
	}
	for i := range a {
		if a[i] != b[i] {
			return false
		}
	}
	return true
}

// Sort prominenceRecords by peak.
type byPeak []prominenceRecord

func (a byPeak) Len() int      { return len(a) }
func (a byPeak) Swap(i, j int) { a[i], a[j] = a[j], a[i] }
func (a byPeak) Less(i, j int) bool {
	return a[i].peak.p.x < a[j].peak.p.x || (a[i].peak.p.x == a[j].peak.p.x && a[i].peak.p.y < a[j].peak.p.y)
}

// print displays a prominence result nicely for test failures.
func print(a []prominenceRecord) string {
	s := ""
	for _, r := range a {
		s += fmt.Sprintf("peak:%s col:%s dom:%s\n", r.peak, r.col, r.dom)
	}
	return s
}

// runTest parses s, computes prominences on it, and sorts and returns the results.
func runTest(s string) []prominenceRecord {
	var r []prominenceRecord
	data := parseTest(s)
	computeProminence(simpleReader(data), minx(data), maxx(data), func(peak, col, dom cell, island bool) {
		r = append(r, prominenceRecord{peak, col, dom, island})
	})
	sort.Sort(byPeak(r))
	return r
}

func minx(d []cell) coord {
	x := d[0].p.x
	for _, c := range d[1:] {
		if c.p.x < x {
			x = c.p.x
		}
	}
	return x
}

func maxx(d []cell) coord {
	x := d[0].p.x
	for _, c := range d[1:] {
		if c.p.x > x {
			x = c.p.x
		}
	}
	return x + 1
}

func TestSingle(t *testing.T) {
	// A simple mountain.
	got := runTest(`
33433
34543
45654
34543
33433
`)
	want := []prominenceRecord{
		{cell{point{2, 2}, 6}, cell{}, cell{}, true},
	}
	sort.Sort(byPeak(want))
	if !equal(got, want) {
		t.Errorf("want %v, got %v", want, got)
	}
}

func TestPair(t *testing.T) {
	// A two-peak mountain.
	got := runTest(`
33433
34543
48674
34543
33433
`)
	want := []prominenceRecord{
		{cell{point{1, 2}, 8}, cell{}, cell{}, true},
		{cell{point{3, 2}, 7}, cell{point{2, 2}, 6}, cell{point{1, 2}, 8}, false},
	}
	sort.Sort(byPeak(want))
	if !equal(got, want) {
		t.Errorf("want\n%s, got\n%s", print(want), print(got))
	}
}

func TestTripleCol(t *testing.T) {
	// Three islands come together at a single point.
	got := runTest(`
338333
336333
765673
333333
333333
`)
	want := []prominenceRecord{
		{cell{point{2, 0}, 8}, cell{}, cell{}, true},
		{cell{point{0, 2}, 7}, cell{point{2, 2}, 5}, cell{point{2, 0}, 8}, false},
		{cell{point{4, 2}, 7}, cell{point{2, 2}, 5}, cell{point{2, 0}, 8}, false},
	}
	sort.Sort(byPeak(want))
	if !equal(got, want) {
		t.Errorf("want\n%s, got\n%s", print(want), print(got))
	}
}

func TestJoin(t *testing.T) {
	// Test merging multiple islands.
	got := runTest(`
111111111111
132425262728
111111111111
`)
	want := []prominenceRecord{
		{cell{point{1, 1}, 3}, cell{point{2, 1}, 2}, cell{point{3, 1}, 4}, false},
		{cell{point{3, 1}, 4}, cell{point{4, 1}, 2}, cell{point{5, 1}, 5}, false},
		{cell{point{5, 1}, 5}, cell{point{6, 1}, 2}, cell{point{7, 1}, 6}, false},
		{cell{point{7, 1}, 6}, cell{point{8, 1}, 2}, cell{point{9, 1}, 7}, false},
		{cell{point{9, 1}, 7}, cell{point{10, 1}, 2}, cell{point{11, 1}, 8}, false},
		{cell{point{11, 1}, 8}, cell{}, cell{}, true},
	}
	sort.Sort(byPeak(want))
	if !equal(got, want) {
		t.Errorf("want\n%s, got\n%s", print(want), print(got))
	}
}

func TestWraparound(t *testing.T) {
	// Test east-west (but not north-south) wraparound.
	got := runTest(`
1114111
1115111
1114111
4532373
1113111
1116111
1113111
`)
	want := []prominenceRecord{
		{cell{point{3, 1}, 5}, cell{point{3, 3}, 2}, cell{point{5, 3}, 7}, false},
		{cell{point{1, 3}, 5}, cell{point{6, 3}, 3}, cell{point{5, 3}, 7}, false},
		{cell{point{3, 5}, 6}, cell{point{3, 3}, 2}, cell{point{5, 3}, 7}, false},
		{cell{point{5, 3}, 7}, cell{}, cell{}, true},
	}
	sort.Sort(byPeak(want))
	if !equal(got, want) {
		t.Errorf("want\n%s, got\n%s", print(want), print(got))
	}
}
