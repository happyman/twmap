package main

// A (hopefully) faster map than the generic maps.
//
// Implements map[point]islandBorder{}

type bucket struct {
	p   [8]point   // grid location
	i   [8]*island // island it is part of
	c   [8]int8    // # of missing neighbors
	ovf *bucket    // overflow
}

// Empty slots have c[x] == 0 and i[x] == nil.

type hashmap struct {
	n int
	b []bucket
}

func newmap() *hashmap {
	return &hashmap{n: 0, b: make([]bucket, 1024)}
}

func hash(p point) int {
	return int(p.x) + int(p.y)*37
}

func (m *hashmap) size() int {
	return m.n
}

func (m *hashmap) find(p point) *island {
	h := hash(p) & (len(m.b) - 1)
	b := &m.b[h]
	for {
		if p == b.p[0] && b.i[0] != nil {
			b.c[0]--
			i := b.i[0]
			if b.c[0] == 0 {
				b.i[0] = nil
				m.n--
			}
			return i
		}
		if p == b.p[1] && b.i[1] != nil {
			b.c[1]--
			i := b.i[1]
			if b.c[1] == 0 {
				b.i[1] = nil
				m.n--
			}
			return i
		}
		if p == b.p[2] && b.i[2] != nil {
			b.c[2]--
			i := b.i[2]
			if b.c[2] == 0 {
				b.i[2] = nil
				m.n--
			}
			return i
		}
		if p == b.p[3] && b.i[3] != nil {
			b.c[3]--
			i := b.i[3]
			if b.c[3] == 0 {
				b.i[3] = nil
				m.n--
			}
			return i
		}
		if p == b.p[4] && b.i[4] != nil {
			b.c[4]--
			i := b.i[4]
			if b.c[4] == 0 {
				b.i[4] = nil
				m.n--
			}
			return i
		}
		if p == b.p[5] && b.i[5] != nil {
			b.c[5]--
			i := b.i[5]
			if b.c[5] == 0 {
				b.i[5] = nil
				m.n--
			}
			return i
		}
		if p == b.p[6] && b.i[6] != nil {
			b.c[6]--
			i := b.i[6]
			if b.c[6] == 0 {
				b.i[6] = nil
				m.n--
			}
			return i
		}
		if p == b.p[7] && b.i[7] != nil {
			b.c[7]--
			i := b.i[7]
			if b.c[7] == 0 {
				b.i[7] = nil
				m.n--
			}
			return i
		}
		b = b.ovf
		if b == nil {
			return nil
		}
	}
}

func (m *hashmap) insert(p point, i *island, c int8) {
	if m.n >= 5*len(m.b) {
		m.grow()
	}
	m.n++
	h := hash(p) & (len(m.b) - 1)
	b := &m.b[h]
	for {
		if b.c[0] == 0 {
			b.p[0] = p
			b.i[0] = i
			b.c[0] = c
			return
		}
		if b.c[1] == 0 {
			b.p[1] = p
			b.i[1] = i
			b.c[1] = c
			return
		}
		if b.c[2] == 0 {
			b.p[2] = p
			b.i[2] = i
			b.c[2] = c
			return
		}
		if b.c[3] == 0 {
			b.p[3] = p
			b.i[3] = i
			b.c[3] = c
			return
		}
		if b.c[4] == 0 {
			b.p[4] = p
			b.i[4] = i
			b.c[4] = c
			return
		}
		if b.c[5] == 0 {
			b.p[5] = p
			b.i[5] = i
			b.c[5] = c
			return
		}
		if b.c[6] == 0 {
			b.p[6] = p
			b.i[6] = i
			b.c[6] = c
			return
		}
		if b.c[7] == 0 {
			b.p[7] = p
			b.i[7] = i
			b.c[7] = c
			return
		}
		if b.ovf != nil {
			b = b.ovf
			continue
		}
		newb := &bucket{}
		b.ovf = newb
		b = newb
	}
}

func (m *hashmap) contents() []*island {
	var r []*island
	for i := range m.b {
		b := &m.b[i]
		for b != nil {
			for j := 0; j < 8; j++ {
				i := b.i[j]
				if i != nil {
					r = append(r, i)
				}
			}
			b = b.ovf
		}
	}
	return r
}

func (m *hashmap) grow() {
	oldb := m.b
	newb := make([]bucket, len(oldb)*2)

	m.n = 0
	m.b = newb
	for i := range oldb {
		b := &oldb[i]
		for b != nil {
			for j := 0; j < 8; j++ {
				if b.i[j] != nil {
					m.insert(b.p[j], b.i[j], b.c[j])
				}
			}
			b = b.ovf
		}
	}
}
