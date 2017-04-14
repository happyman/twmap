SOURCEDIR=.
SOURCES := $(shell find $(SOURCEDIR) -name '*.go')

BINARY=prominence

VERSION=1.0.0
BUILD_TIME=`date +%FT%T%z`

#LDFLAGS=-ldflags "-X github.com/ariejan/roll/core.Version=${VERSION} -X github.com/ariejan/roll/core.BuildTime=${BUILD_TIME}"
LDFLAGS=

.DEFAULT_GOAL: $(BINARY)

$(BINARY): $(SOURCES)
	go build ${LDFLAGS} -o ${BINARY} ${SOURCES}
