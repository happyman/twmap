<?php

//http://www.fileformat.info/format/tiff/egff.htm
//https://www.loc.gov/preservation/digital/formats/content/tiff_tags.shtml


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$dev = 0;
$reset = 0;
$filter = 0;
if ( isset($_GET['dev']) ) $dev = intval( $_GET['dev'] );
if ( isset($_GET['reset']) ) $reset = intval( $_GET['reset'] );
if ( isset($_GET['filter']) ) $filter = $_GET['filter'];
if ( $dev>0 ) {
    header('Content-Type: application/json; charset=utf-8');
    echo 'DEV'.PHP_EOL;
}

if ( !function_exists('intdiv') ) {
    function intdiv( $dividend, $divisor ) {
        return ( $dividend - $dividend % $divisor ) / $divisor;
    }
}

class GeoPoint {
    public $mFormat; /* 0 = raster, 4326 = WGS84 */
    public $x;
    public $y;
    public $lon;
    public $lat;
    
    public function __construct( $p1, $p2, $format=4326 ) {
        $this->mFormat = $format;
        if ( $format===4326 ) {
            $this->lat = $p1;
            $this->lon = $p2;
        } else {
            $this->x = $p1;
            $this->y = $p2;
        }
    }
    
    public function add( &$vector ) {
        if ( $vector->mFormat===$this->mFormat ) {
            if ( $format===4326 ) {
                $this->lat += $vector->lat;
                $this->lon += $vector->lon;
            } else {
                $this->x += $vector->x;
                $this->y += $vector->y;
            }
        } else {
            // Incompatible format
        }
    }
    
    public function getReadable () {
        if ( $this->mFormat===4326 ) {
            return sprintf( '(%.6f, %.6f)', $this->lat, $this->lon );
        }
        if ( $this->x > intval($this->x) || $this->y > intval($this->y) ) {
            return sprintf( '(%.2f, %.2f)', $this->x, $this->y );
        }
        return sprintf( '(%d, %d)', $this->x, $this->y );
    }
    
}

class GeoKeyDirectory {
    private $mImageFileDirectory;
    
    public $KeyID;
    public $TIFFTagLocation;
    public $Count;
    public $Value_Offset;
    
    public function __construct( $id, $location, $count, $offset, &$ifd ) {
        $this->mImageFileDirectory = $ifd;
        $this->KeyID = $id;
        $this->TIFFTagLocation = $location;
        $this->Count = $count;
        $this->Value_Offset = $offset;
    }
    
    public function getData() {
        if ( $this->TIFFTagLocation===0 ) {
            return $this->Value_Offset;
        }
        return $this->mImageFileDirectory->TagList[$this->TIFFTagLocation];
    }
}

class SplFixedRaster extends SplFixedArray {
    public $mBitmap;
    public $mWidth, $mHeight, $mLength;
    
    public $mTiepoint = array();
    public $mPixelScale = array();

    public function __construct( $width, $height ) {
        $this->mWidth = $width;
        $this->mHeight = $height;
        $this->mLength = $width * $height;
        $this->mBitmap = new SplFixedArray( $this->mLength );
    }

    public function getSize() {
        return $this->mLength;
    }
    
    public function fromSplFixedRaster( &$source, $copydata = true ) {
        if ( $source->mLength!==$this->mLength ) {
            // Error handling
        }
        if ( $copydata ) {
            for ( $i=0; $i<$this->mLength; $i++ ) {
                $this->mBitmap[$i] = $source->mBitmap[$i];
            }
        }
        $this->mTiepoint[0] = $source->mTiepoint[0];
        $this->mTiepoint[1] = $source->mTiepoint[1];
        $this->mPixelScale[0] = $source->mPixelScale[0];
        $this->mPixelScale[1] = $source->mPixelScale[1];
    }
    
    public function setRange( $tpx, $tpy, $psx, $psy ) {
        //echo 'setRange(), tpx: '.$tpx.', tpy: '.$tpy.PHP_EOL;
        //echo 'setRange(), psx: '.$psx.', psy: '.$psy.PHP_EOL;
        $this->mTiepoint[0] = $tpx;
        $this->mTiepoint[1] = $tpy;
        $this->mPixelScale[0] = $psx;
        $this->mPixelScale[1] = $psy;
        if ( $GLOBALS['dev'] ) {
            echo $this->mTiepoint[1].','.$this->mTiepoint[0].PHP_EOL;
            echo ($this->mTiepoint[1] + $this->mHeight * $this->mPixelScale[1]).','.($this->mTiepoint[0] + $this->mWidth * $this->mPixelScale[0]).PHP_EOL;
        }
    }
    
    public function trim( $size ) {
        $dst = 0;
        $src = $size * ($this->mWidth+1);
        $this->mWidth = $this->mWidth - $size*2;
        $this->mHeight = $this->mHeight - $size*2;
        $this->mLength = $this->mWidth * $this->mHeight;
        if ( $GLOBALS['dev'] ) echo 'trim, bitmap: '.$this->mBitmap->getSize().', mem: '.memory_get_usage().PHP_EOL;
        for ( $y=0; $y<$this->mHeight; $y++ ) {
            for ( $x=0; $x<$this->mWidth; $x++ ) {
                $this->mBitmap[$dst] = $this->mBitmap[$src];
                $dst++;
                $src++;
            }
            $src += $size*2;
        }
        $this->mBitmap->setSize( $this->mLength );
        if ( $GLOBALS['dev'] ) echo 'trim, bitmap: '.$this->mBitmap->getSize().', mem: '.memory_get_usage().PHP_EOL;
        $this->mTiepoint[0] += $this->mPixelScale[0] * $size;
        $this->mTiepoint[1] += $this->mPixelScale[1] * $size;
    }
    
    public function normalize( &$stats, $type = 'dynamic', $min = -500, $max = 500 ) {
        if ( $type==='static' ) {
        } else {
            $min = 65536 * 65536;
            $max = -65536 * 65536;
            foreach ( $this->mBitmap as $value ) {
                if ( $value > $max ) $max = $value;
                if ( $value < $min ) $min = $value;
            }
            header('G4H-RasterMaxValue: '.$max);
            header('G4H-RasterMinValue: '.$min);
        }
        $multiplier = 255.0 / ($max-$min);
        
        $quantization = 64;
        $qdivisor = intdiv(256, $quantization);
        $histogram = new SplFixedArray( $quantization );
        for ( $i=0; $i<$quantization; $i++ ) {
            $histogram[$i] = 0;
        }
        
        for ( $i=0; $i<$this->mLength; $i++ ) {
            $v = intval( ($this->mBitmap[$i]-$min) * $multiplier );
            if ( $v > 255 ) $v = 255;
            else if ( $v < 0 ) $v = 0;
            $this->mBitmap[$i] = $v;
            $segment = intdiv($this->mBitmap[$i], $qdivisor );
            $histogram[$segment] = $histogram[$segment]+1;
            if ( $segment<1 || $segment>=$quantization-1 ) {
                $px = $i%$this->mWidth;
                $py = intdiv($i, $this->mWidth);
                if ( $GLOBALS['dev'] ) echo $px.', '.$py.PHP_EOL;
                $px = $this->mTiepoint[0] + $px*$this->mPixelScale[0];
                $py = $this->mTiepoint[1] + $py*$this->mPixelScale[1];
                if ( $GLOBALS['dev'] ) echo $py.', '.$px.PHP_EOL;
            }
        }

        $accum = 0;
        $threshold_low = 0;
        $threshold_high = 0;
        $tcount_low = intdiv($this->mLength, 5);
        $tcount_high = $tcount_low * 4;
        for ( $i=0; $i<$quantization; $i++ ) {
            $accum += $histogram[$i];
            if ( $accum>$tcount_low && $threshold_low===0 ) {
                $threshold_low = $i * $qdivisor;
            } else if ( $accum>$tcount_high && $threshold_high===0 ) {
                $threshold_high = $i * $qdivisor;
            }
        }
        $stats['threshold'] = array(
            $threshold_high,
            $threshold_low,
        );

        if ( $GLOBALS['dev'] ) {
            //print_r( array($threshold_low, $threshold_high) );
            //print_r($histogram);
        }
    }
}

class ImageFileDirectory {
    private $mGeoTIFF;

    public $NumDirEntries;

    public $ImageWidth; /* 256 The number of columns in the image, i.e., the number of pixels per row. */
    public $ImageLength; /* 257 The number of rows of pixels in the image. */
    public $BitsPerSample; /* 258 Number of bits per component. */
    public $BytesPerSample;
    public $Compression; /* 259 Compression scheme used on the image data. 1=uncompressed and 4=CCITT Group 4. */
    public $PhotometricInterpretation; /* 262 The color space of the image data. 1=black is zero and 2=RGB. */
    public $Threshholding; /* 263 For black and white TIFF files that represent shades of gray, the technique used to convert from gray to black and white pixels. */
    public $CellWidth; /* 264 The width of the dithering or halftoning matrix used to create a dithered or halftoned bilevel file. */
    public $CellLength; /* 265 The length of the dithering or halftoning matrix used to create a dithered or halftoned bilevel file. */
    public $Orientation; /* 274 The orientation of the image with respect to the rows and columns. */
    public $SamplesPerPixel; /* 277 The number of components per pixel. */
    public $RowsPerStrip; /* 278 The number of rows per strip. */
    public $StripByteCounts; /* 279 For each strip, the number of bytes in the strip after compression. */
    public $XResolution; /* 282 The number of pixels per ResolutionUnit in the ImageWidth direction. */
    public $YResolution; /* 283 The number of pixels per ResolutionUnit in the ImageLength direction. */
    public $ResolutionUnit; /* 296 The unit of measurement for XResolution and YResolution. */
    
    public $TileWidth; /* 322 The tile width in pixels. This is the number of columns in each tile. */
    public $TileLength; /* 323 The tile length (height) in pixels. This is the number of rows in each tile. */
    public $TileOffsets; /* 324 For each tile, the byte offset of that tile, as compressed and stored on disk. */
    public $TileByteCounts; /* 325 For each tile, the number of (compressed) bytes in that tile. */
    
    public $TileColumnNum;
    public $TileRowNum;
    public $TileBytesPerRow;
    public $TileIndex; /* current index of tile in this IFD */
    public $PixelPointer; /* current pixel offset */
    
    public $GeographicTypeGeoKey; /* This key may be used to specify the code for the geographic coordinate system used to map lat-long to a specific ellipsoid over the earth. */

    public $SampleFormat; /* 339 Specifies how to interpret each data sample in a pixel. */
    public $ModelPixelScaleTag; /* 33550 Used in interchangeable GeoTIFF_1_0 files. */
    public $ModelTiepointTag; /* 33922 Originally part of Intergraph's GeoTIFF tags, but now used in interchangeable GeoTIFF_1_0 files. */
    public $ModelTransformationTag; /* 34264 (JPL Carto Group) */
    public $GeoKeyDirectoryTag; /* 34735 Used in interchangeable GeoTIFF_1_0 files. This tag is also know as 'ProjectionInfoTag' and 'CoordSystemInfoTag' */
    public $GeoDoubleParamsTag; /* 34736 Used in interchangeable GeoTIFF_1_0 files. */
    public $GeoAsciiParamsTag; /* 34737 Used in interchangeable GeoTIFF_1_0 files. */
    
    public $TagList = array(); /* tagid => data are stored in this array to be referenced by GeoKeyDirectoryTag with TIFFTagLocation */    

    public $mUnpackFormats;

    public function __construct() {
    }
    
    public function init( &$geotiff ) {
        $this->mGeoTIFF = $geotiff;
        if ( isset($geotiff->mUnpackFormats) ) $this->mUnpackFormats = $geotiff->mUnpackFormats; /* Endianness is stored outside IFD and need to be copied into IFD or serialize/unserialize fails */
    }

    public function getValue( &$point ) {
        /* Get value of a single pixel */
        if ( $point->mFormat===$this->GeographicTypeGeoKey ) {
            $x = intval( $this->ModelTiepointTag[0] + ($point->lon - $this->ModelTiepointTag[3]) / $this->ModelPixelScaleTag[0] );
            $y = intval( $this->ModelTiepointTag[1] + ($point->lat - $this->ModelTiepointTag[4]) / $this->ModelPixelScaleTag[1] );
            
            $this->TileIndex = intdiv( $x, $this->TileWidth ) + intdiv( $y, $this->TileLength ) * $this->TileColumnNum;
            $this->PixelPointer = $this->TagList[324][$this->TileIndex] + (($x % $this->TileWidth) + ($y % $this->TileLength) * $this->TileWidth) * $this->BytesPerSample;

            fseek( $this->mGeoTIFF->hFile, $this->PixelPointer );
            $value = current(unpack( $this->mUnpackFormats[$this->BytesPerSample], fread( $this->mGeoTIFF->hFile, $this->BytesPerSample ) ));

            if ( $GLOBALS['dev'] ) {
                $pixel = new GeoPoint(
                    $x,
                    $y,
                    0
                );
                echo 'getValue @ '.$pixel->getReadable().PHP_EOL;
                echo 'value: '.$value.PHP_EOL.PHP_EOL;
            }
        } else {
            echo 'Warning! Coordinate system mismatch. point CS: '.$point->mFormat.', map CS: '.$this->GeographicTypeGeoKey.PHP_EOL;
        }
        return -1;
    }

    public static $mKernels = array(
        'gaussian' => array(
            1/16, 2/16, 1/16,
            2/16, 4/16, 2/16,
            1/16, 2/16, 1/16,
        ),
        'prominence' => array(
            -1/8, -1/8, -1/8,
            -1/8,    1, -1/8,
            -1/8, -1/8, -1/8,
        ),
        'identity' => array(
            0, 0, 0,
            0, 1, 0,
            0, 0, 0,
        ),
        'blur' => array(
            1/9, 1/9, 1/9,
            1/9, 1/9, 1/9,
            1/9, 1/9, 1/9,
        ),
        'v-sobel' => array(
            1, 0, -1,
            2, 0, -2,
            1, 0, -1,
        ),
        'h-sobel' => array(
             1,  2,  1,
             0,  0,  0,
            -1, -2, -1,
        ),
        'erosion' => array(
            1, 1, 1,
            1, 1, 1,
            1, 1, 1,
        ),
    );
    
    public static function convolution( &$dstRaster, &$srcRaster, $kernelName ) {
        $kernel = &self::$mKernels[$kernelName];
        $buffer = new SplFixedRaster( $srcRaster->mWidth, $srcRaster->mHeight );
        $buffer->fromSplFixedRaster( $srcRaster, false );
        $ksz = sqrt( count($kernel) ); /* kernel width */
        $vi = $ksz-1; /* vertical incremental of array offset */
        $src = &$srcRaster->mBitmap;
        $dst = &$buffer->mBitmap;
        $w = $srcRaster->mWidth;
        $h = $srcRaster->mHeight;
        $k0 = $kernel[0]; $k1 = $kernel[1]; $k2 = $kernel[2];
        $k3 = $kernel[3]; $k4 = $kernel[4]; $k5 = $kernel[5];
        $k6 = $kernel[6]; $k7 = $kernel[7]; $k8 = $kernel[8];
        $p0 = 0;            $p1 = $p0 + 1;      $p2 = $p0 + 2;
        $p3 = $p0 + $w;     $p4 = $p3 + 1;      $p5 = $p3 + 2;  
        $p6 = $p3 + $w;     $p7 = $p6 + 1;      $p8 = $p6 + 2;
        if ( $GLOBALS['dev'] ) {
            echo 'applying kernel'.PHP_EOL;
            echo 'srcRaster: '.$srcRaster->getSize().', mem: '.memory_get_usage().PHP_EOL;
            echo 'buffer: '.$buffer->getSize().', mem: '.memory_get_usage().PHP_EOL;
        }
        for ( $py=0; $py<=$h-$ksz; $py++ ) {
            for ( $px=0; $px<=$w-$ksz; $px++ ) {
                $dst[$p4] = $src[$p8] * $k0 + $src[$p7] * $k1 + $src[$p6] * $k2 +
                            $src[$p5] * $k3 + $src[$p4] * $k4 + $src[$p3] * $k5 +
                            $src[$p2] * $k6 + $src[$p1] * $k7 + $src[$p0] * $k8;
                $p0++; $p1++; $p2++;
                $p3++; $p4++; $p5++;
                $p6++; $p7++; $p8++;
            }
            $p0 += $vi; $p1 += $vi; $p2 += $vi;
            $p3 += $vi; $p4 += $vi; $p5 += $vi;
            $p6 += $vi; $p7 += $vi; $p8 += $vi;
        }
        if ( $GLOBALS['dev'] ) {
            echo 'kernel applied'.PHP_EOL;
            echo 'dstRaster: '.$dstRaster->getSize().', mem: '.memory_get_usage().PHP_EOL;
            echo 'buffer: '.$buffer->getSize().', mem: '.memory_get_usage().PHP_EOL;
        }
        $dstRaster->fromSplFixedRaster( $buffer );
    }

    public function getTile( &$point, $format='png' ) {
        global $dev, $reset;
        
        /* Get TIFF Tile where the point is in */
        if ( $point->mFormat===$this->GeographicTypeGeoKey ) {
            $mx = intval( $this->ModelTiepointTag[0] + ($point->lon - $this->ModelTiepointTag[3]) / $this->ModelPixelScaleTag[0] ); /* pixel x in whole map */
            $my = intval( $this->ModelTiepointTag[1] + ($point->lat - $this->ModelTiepointTag[4]) / $this->ModelPixelScaleTag[1] ); /* pixel y in whole map */
            $tx = intdiv( $mx, $this->TileWidth ); /* tile x */
            $ty = intdiv( $my, $this->TileLength ); /* tile y */
            
            $this->TileIndex = $tx + $ty * $this->TileColumnNum;
            $this->PixelPointer = $this->TagList[324][$this->TileIndex] + (($mx % $this->TileWidth) + ($my % $this->TileLength) * $this->TileWidth) * $this->BytesPerSample;
            //echo sprintf( 'tile index: %d, offset: %d, bytes: %d'.PHP_EOL, $this->TileIndex, $this->TagList[324][$this->TileIndex], $this->TagList[325][$this->TileIndex] );
            
            $cache_filename = md5( $this->TileIndex.'::'.$GLOBALS['filter'] );
            $cache_dir = './cache/'.substr($cache_filename, 0, 2).'/'.substr($cache_filename, 2, 2);
            $cache_filename = $cache_dir.'/'.$cache_filename;
            @mkdir( $cache_dir, 0777, true );
            
            header('G4H-Reset: '.$GLOBALS['reset']);

            if ( !$GLOBALS['reset'] && file_exists($cache_filename) ) {
                header('G4H-CachePath: '.$cache_filename);
                header('G4H-CacheSize: '.filesize($cache_filename));
                $bitmap = unserialize(gzuncompress(file_get_contents( $cache_filename )));
            } else {
                $count = 0;
                $mean = 0.0;
                $sqrmean = 0.0;
                
                $now = microtime(true) * 1000;
                
                $expand = 16; /* expand for convolution */
                $bitmap = new SplFixedRaster( $this->TileWidth + $expand * 2, $this->TileLength + $expand * 2 );
                $bitmap->setRange(
                    $this->ModelTiepointTag[3] + $tx*$this->TileWidth*$this->ModelPixelScaleTag[0] - $expand*$this->ModelPixelScaleTag[0],
                    $this->ModelTiepointTag[4] + $ty*$this->TileLength*$this->ModelPixelScaleTag[1] - $expand*$this->ModelPixelScaleTag[1],
                    $this->ModelPixelScaleTag[0],
                    $this->ModelPixelScaleTag[1]
                );
                if ( $GLOBALS['dev'] ) echo 'pixels: '.$bitmap->getSize().', mem: '.memory_get_usage().PHP_EOL;
                
                $buffer = '';

                $t0 = $this->TileIndex - $this->TileColumnNum - 1;
                $t1 = $t0 + 1;
                $t2 = $t0 + 2;
                $o0 = $this->TagList[324][$t0] + ($this->TileWidth-$expand)*$this->BytesPerSample + ($this->TileLength-$expand)*$this->TileWidth*$this->BytesPerSample;
                $o1 = $this->TagList[324][$t1] + ($this->TileLength-$expand)*$this->TileWidth*$this->BytesPerSample;
                $o2 = $this->TagList[324][$t2] + ($this->TileLength-$expand)*$this->TileWidth*$this->BytesPerSample;
                for ( $i=0; $i<$expand; $i++ ) {
                    fseek( $this->mGeoTIFF->hFile, $o0 );
                    $buffer .= fread( $this->mGeoTIFF->hFile, $expand * $this->BytesPerSample );
                    fseek( $this->mGeoTIFF->hFile, $o1 );
                    $buffer .= fread( $this->mGeoTIFF->hFile, $this->TileWidth * $this->BytesPerSample );
                    fseek( $this->mGeoTIFF->hFile, $o2 );
                    $buffer .= fread( $this->mGeoTIFF->hFile, $expand * $this->BytesPerSample );
                    $o0 += $this->TileWidth * $this->BytesPerSample;
                    $o1 += $this->TileWidth * $this->BytesPerSample;
                    $o2 += $this->TileWidth * $this->BytesPerSample;
                }
                if ( $GLOBALS['dev'] ) echo 'fread, buffer: '.strlen($buffer).', mem: '.memory_get_usage().PHP_EOL;
                
                $t0 = $this->TileIndex - 1;
                $t1 = $t0 + 1;
                $t2 = $t0 + 2;
                $o0 = $this->TagList[324][$t0] + ($this->TileWidth-$expand)*$this->BytesPerSample;
                $o1 = $this->TagList[324][$t1];
                $o2 = $this->TagList[324][$t2];
                for ( $i=0; $i<$this->TileLength; $i++ ) {
                    fseek( $this->mGeoTIFF->hFile, $o0 );
                    $buffer .= fread( $this->mGeoTIFF->hFile, $expand * $this->BytesPerSample );
                    fseek( $this->mGeoTIFF->hFile, $o1 );
                    $buffer .= fread( $this->mGeoTIFF->hFile, $this->TileWidth * $this->BytesPerSample );
                    fseek( $this->mGeoTIFF->hFile, $o2 );
                    $buffer .= fread( $this->mGeoTIFF->hFile, $expand * $this->BytesPerSample );
                    $o0 += $this->TileWidth * $this->BytesPerSample;
                    $o1 += $this->TileWidth * $this->BytesPerSample;
                    $o2 += $this->TileWidth * $this->BytesPerSample;
                }
                if ( $GLOBALS['dev'] ) echo 'fread, buffer: '.strlen($buffer).', mem: '.memory_get_usage().PHP_EOL;

                $t0 = $this->TileIndex - 1 + $this->TileColumnNum;
                $t1 = $t0 + 1;
                $t2 = $t0 + 2;
                $o0 = $this->TagList[324][$t0] + ($this->TileWidth-$expand)*$this->BytesPerSample;
                $o1 = $this->TagList[324][$t1];
                $o2 = $this->TagList[324][$t2];
                for ( $i=0; $i<$expand; $i++ ) {
                    fseek( $this->mGeoTIFF->hFile, $o0 );
                    $buffer .= fread( $this->mGeoTIFF->hFile, $expand * $this->BytesPerSample );
                    fseek( $this->mGeoTIFF->hFile, $o1 );
                    $buffer .= fread( $this->mGeoTIFF->hFile, $this->TileWidth * $this->BytesPerSample );
                    fseek( $this->mGeoTIFF->hFile, $o2 );
                    $buffer .= fread( $this->mGeoTIFF->hFile, $expand * $this->BytesPerSample );
                    $o0 += $this->TileWidth * $this->BytesPerSample;
                    $o1 += $this->TileWidth * $this->BytesPerSample;
                    $o2 += $this->TileWidth * $this->BytesPerSample;
                }
                if ( $GLOBALS['dev'] ) echo 'fread, buffer: '.strlen($buffer).', mem: '.memory_get_usage().PHP_EOL;
                
                // unpack 
                $bitmap->mBitmap = SplFixedArray::fromArray( unpack( $this->mUnpackFormats[$this->BytesPerSample], $buffer ), false ); /* This must be done with one line or extra memory is allocated */
                if ( $GLOBALS['dev'] ) echo 'unpacked: '.count($buffer).', mem: '.memory_get_usage().PHP_EOL;
              
                /* apply kernels */
                if ( $GLOBALS['filter']==='prominence' ) {
                    self::convolution( $bitmap, $bitmap, 'gaussian' );
                    self::convolution( $bitmap, $bitmap, 'prominence' );
                } else if ( $GLOBALS['filter']==='v-sobel' ) {
                    self::convolution( $bitmap, $bitmap, 'gaussian' );
                    self::convolution( $bitmap, $bitmap, 'v-sobel' );
                } else if ( $GLOBALS['filter']==='h-sobel' ) {
                    self::convolution( $bitmap, $bitmap, 'gaussian' );
                    self::convolution( $bitmap, $bitmap, 'h-sobel' );
                } else if ( $GLOBALS['filter']==='sobel' ) {
                    self::convolution( $bitmap, $bitmap, 'gaussian' );
                    $vsobel = new SplFixedRaster( $bitmap->mWidth, $bitmap->mHeight );
                    $vsobel->fromSplFixedRaster( $bitmap, false );
                    self::convolution( $vsobel, $bitmap, 'v-sobel' );
                    $hsobel = new SplFixedRaster( $bitmap->mWidth, $bitmap->mHeight );
                    $hsobel->fromSplFixedRaster( $bitmap, false );
                    self::convolution( $hsobel, $bitmap, 'h-sobel' );
                    
                    $vsobel->normalize( $stats, 'static' );
                    $hsobel->normalize( $stats, 'static' );
                    
                    $n = $bitmap->mLength;
                    for ( $i=0; $i<$n; $i++ ) {
                        $bitmap->mBitmap[$i] = $hsobel->mBitmap[$i] * 256 + $vsobel->mBitmap[$i];
                    }

                    // Convert sobel vectors to heading
                    /*
                    $n = $bitmap->mLength;
                    $vx = 1;
                    $vy = 2;
                    for ( $i=0; $i<$n; $i++ ) {
                        if ( $hsobel->mBitmap[$i] || $vsobel->mBitmap[$i] ) {
                            $bitmap->mBitmap[$i] = acos(
                                ($vx * $hsobel->mBitmap[$i] + $vy * $vsobel->mBitmap[$i]) /
                                sqrt(pow($vx, 2) + pow($vy, 2)) /
                                sqrt(pow($hsobel->mBitmap[$i], 2) + pow($vsobel->mBitmap[$i], 2))
                            );
                        } else {
                            $bitmap->mBitmap[$i] = 0;
                        }
                    }
                    self::convolution( $bitmap, $bitmap, 'gaussian' );
                    */
                }            
                
                $bitmap->trim($expand);

                file_put_contents( $cache_filename, gzcompress(serialize($bitmap)) );
            }
            
            /* find max and min for value scaling */
            if ( $GLOBALS['filter']==='sobel' ) {
            } else {
                $stats = array();
                //$bitmap->normalize( $stats );
                $bitmap->normalize( $stats, 'static' );
                $threshold_high = $stats['threshold'][0];
                $threshold_low = $stats['threshold'][1];
            }

            //echo 'bitmap: '.count($bitmap).PHP_EOL;
            $img = imagecreatetruecolor( $bitmap->mWidth, $bitmap->mHeight );
            if ( is_resource($img) ) {
                $index = 0;
                for ( $y=0; $y<$bitmap->mHeight; $y++ ) {
                    for ( $x=0; $x<$bitmap->mWidth; $x++ ) {
                        $p = $bitmap->mBitmap[$index];
                        if ( $GLOBALS['filter']==='prominence' ) {
                            if ( $p > $threshold_high ) {
                                imagesetpixel( $img, $x, $y, imagecolorallocate ( $img, 255, $p, $p ) );
                            } else if ( $p < $threshold_low ) {
                                imagesetpixel( $img, $x, $y, imagecolorallocate ( $img, $p, $p, 255 ) );
                            } else {
                                imagesetpixel( $img, $x, $y, imagecolorallocate ( $img, $p, $p, $p ) );
                            }
                        } else if ( $GLOBALS['filter']==='sobel' ) {
                            $r = 0;
                            $g = $p % 256;
                            $b = ( $p - $g ) / 256;
                            imagesetpixel( $img, $x, $y, imagecolorallocate ( $img, $r, $g, $b ) );
                        } else {
                            imagesetpixel( $img, $x, $y, imagecolorallocate ( $img, $p, $p, $p ) );
                        }
                        $index++;
                    }
                }
                if ( $GLOBALS['dev'] ) {
                    die();
                } else {
                    header('Content-Type: image/png');
                    imagepng( $img );
                }
                imagedestroy( $img );
                /*foreach ( $bitmap as $index => $value ) {
                    $bitmap[$index] = intval($bitmap[$index] * $multiplier);
                }*/
            }

            if ( $GLOBALS['dev'] ) {
                echo 'average time taken: '.(microtime(true)*1000-$now).' ms'.PHP_EOL;
                echo sprintf( 'max: %d, min: %d'.PHP_EOL, $max, $min );
                echo sprintf( 'count: %d, mean: %.2f, sqr mean: %.2f'.PHP_EOL, $count, $mean, $sqrmean );
            }
        } else {
            echo 'Warning! Coordinate system mismatch. point CS: '.$point->mFormat.', map CS: '.$this->GeographicTypeGeoKey.PHP_EOL;
        }
        return -1;
    }
    
    public function getValueEx( &$point ) {
        if ( $point->mFormat===$this->GeographicTypeGeoKey ) {
            $pixel = new GeoPoint(
                $this->ModelTiepointTag[0] + ($point->lon - $this->ModelTiepointTag[3]) / $this->ModelPixelScaleTag[0],
                $this->ModelTiepointTag[1] + ($point->lat - $this->ModelTiepointTag[4]) / $this->ModelPixelScaleTag[1],
                0
            );

            if ( $GLOBALS['dev'] ) {
                echo 'getValue @ '.$pixel->getReadable().PHP_EOL;
                $pixel->x = intval($pixel->x);
                $pixel->y = intval($pixel->y);
                echo 'getValue @ '.$pixel->getReadable().PHP_EOL;
            }

            $tile = new GeoPoint(
                intdiv( $pixel->x, $this->TileWidth ),
                intdiv( $pixel->y, $this->TileLength ),
                0
            );
            //echo $tile->getReadable().PHP_EOL;
            $this->TileIndex = $tile->x + $tile->y * $this->TileColumnNum;
            //echo $this->TileIndex.PHP_EOL;
            //echo $this->TagList[324][$this->TileIndex].PHP_EOL;

            $ptile = new GeoPoint(
                $pixel->x % $this->TileWidth,
                $pixel->y % $this->TileLength,
                0
            );
            echo $ptile->getReadable().PHP_EOL;
            $this->PixelPointer = $this->TagList[324][$this->TileIndex] + ($ptile->x + $ptile->y * $this->TileWidth) * $this->BytesPerSample;
            //echo 'offset: '.$this->PixelPointer.PHP_EOL;
            $fcurrent = ftell( $this->mGeoTIFF->hFile );
            
            fseek( $this->mGeoTIFF->hFile, $this->PixelPointer );
            $value = current(unpack( 'v', fread( $this->mGeoTIFF->hFile, $this->BytesPerSample ) ));
            echo 'value: '.$value.PHP_EOL;
            $window = 7;
            $this->PixelPointer -= $this->BytesPerSample * $window; // move left
            $this->PixelPointer -= $this->TileBytesPerRow * $window; // move up
            fseek( $this->mGeoTIFF->hFile, $this->PixelPointer );
            $value = current(unpack( 'v', fread( $this->mGeoTIFF->hFile, $this->BytesPerSample ) ));
            echo 'value: '.$value.PHP_EOL;
            fseek( $this->mGeoTIFF->hFile, $this->PixelPointer );
            for ( $y=0; $y<$window*2+1; $y++ ) {
                for ( $x=0; $x<$window*2+1; $x++ ) {
                    $value = current(unpack( 'v', fread( $this->mGeoTIFF->hFile, $this->BytesPerSample ) ));
                    echo sprintf('%04d ', $value);
                }
                $this->PixelPointer += $this->TileBytesPerRow; // move down
                fseek( $this->mGeoTIFF->hFile, $this->PixelPointer );
                echo PHP_EOL;
            }
            
            fseek( $this->mGeoTIFF->hFile, $fcurrent );
            echo PHP_EOL;            
        }
        return -1;
    }
}

class GeoTIFF {
    public $mFilename;
    public $hFile;
    
    public $mUnpackFormats;
    
    public $Identifier; /* Byte-order Identifier */
    public $Version;    /* TIFF version number (always 2Ah) */
    public $IFDOffset;  /* Offset of the first Image File Directory*/
    public $IFDList = array();

    private static $CacheExpire = 60 * 60; /* Cache expiration time in seconds */
    
    public static $DataTypeLength = array(
        1   => 1, /* BYTE 8-bit unsigned integer */
        2   => 1, /* ASCII 8-bit, NULL-terminated string */
        3   => 2, /* SHORT 16-bit unsigned integer */
        4   => 4, /* LONG 32-bit unsigned integer */
        5   => 8, /* RATIONAL Two 32-bit unsigned integers */
        6   => 1, /* SBYTE 8-bit signed integer */
        7   => 1, /* UNDEFINE 8-bit byte */
        8   => 2, /* SSHORT 16-bit signed integer */
        9   => 4, /* SLONG 32-bit signed integer */
        10  => 8, /* SRATIONAL Two 32-bit signed integers */
        11  => 4, /* FLOAT 4-byte single-precision IEEE floating-point value */
        12  => 8, /* DOUBLE 8-byte double-precision IEEE floating-point value */
    );
    
    public static $DataTypeUnpackFormat = array(
        'II' => array(
            1   => 'C', /* BYTE 8-bit unsigned integer */
            2   => 'a', /* ASCII 8-bit, NULL-terminated string */
            3   => 'v', /* SHORT 16-bit unsigned integer */
            4   => 'V', /* LONG 32-bit unsigned integer */
            5   => 'V2', /* RATIONAL Two 32-bit unsigned integers */
            6   => 'c', /* SBYTE 8-bit signed integer */
            7   => 'u8', /* UNDEFINE 8-bit byte */
            8   => 's', /* SSHORT 16-bit signed integer */
            9   => 'l', /* SLONG 32-bit signed integer */
            10  => 'l2', /* SRATIONAL Two 32-bit signed integers */
            // PHP 7.0.15,7.1.1	The "e", "E", "g" and "G" codes were added to enable byte order support for float and double.
            11  => 'gX', /* FLOAT 4-byte single-precision IEEE floating-point value */
            12  => 'eX', /* DOUBLE 8-byte double-precision IEEE floating-point value */
        ),
        'MM' => array(
            1   => 'C', /* BYTE 8-bit unsigned integer */
            2   => 'a', /* ASCII 8-bit, NULL-terminated string */
            3   => 'n', /* SHORT 16-bit unsigned integer */
            4   => 'N', /* LONG 32-bit unsigned integer */
            5   => 'N2', /* RATIONAL Two 32-bit unsigned integers */
            6   => 'c', /* SBYTE 8-bit signed integer */
            7   => 'u8', /* UNDEFINE 8-bit byte */
            8   => 's', /* SSHORT 16-bit signed integer */
            9   => 'l', /* SLONG 32-bit signed integer */
            10  => 'l2', /* SRATIONAL Two 32-bit signed integers */
            // PHP 7.0.15,7.1.1	The "e", "E", "g" and "G" codes were added to enable byte order support for float and double.
            11  => 'GX', /* FLOAT 4-byte single-precision IEEE floating-point value */
            12  => 'EX', /* DOUBLE 8-byte double-precision IEEE floating-point value */
        ),
    );
    
    public function __construct() {
        $this->TagList = array();
    }
    
    public function __destruct () {
        if ( $this->hFile && is_resource($this->hFile) ) fclose( $this->hFile );
    }

    public static function isLittleEndian() {
        $testint = 0x00FF;
        $p = pack('S', $testint);
        return $testint===current(unpack('v', $p));
    }

    public static function array_append( &$array1, &$array2, $offset, $length ) {
        foreach ( array_slice($array2, $offset, $length) as $ele ) {
            $array1[] = $ele;
        }
    }
    
    public static function loadFromCache( $filename, &$obj ) {
        if ( file_exists($filename) && !$GLOBALS['reset'] ) {
            if ( time() - filemtime( $filename ) < self::$CacheExpire ) {
                try {
                    $obj = unserialize (gzuncompress(file_get_contents( $filename )));
                    if ( $GLOBALS['dev'] > 1 ) {
                        echo 'LOADED FROM CACHE'.PHP_EOL;
                        print_r( $obj );
                    }
                    return 1;
                } catch (Exception $e) {
                    echo 'Caught exception: ',  $e->getMessage(), PHP_EOL;
                }
            }
        }
        return 0;
    }

    public static function saveToCache( $filename, &$obj ) {
        try {
            file_put_contents( $filename, gzcompress(serialize($obj)) );
        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), PHP_EOL;
        }
    }
    
    public function open( $filename ) {
        $this->mFilename = $filename;
        
        $cachepath = sys_get_temp_dir().DIRECTORY_SEPARATOR.md5($this->mFilename).'.cache';
        if ( !$GLOBALS['reset'] && self::loadFromCache( $cachepath, $this->IFDList ) ) {
            //if ( $GLOBALS['dev'] ) print_r( $this->IFDList );
            $this->hFile = fopen( $this->mFilename, 'rb' );
            foreach ( $this->IFDList as $ifd ) {
                $ifd->init( $this );
            }
            return 1;
        }
        
        $this->hFile = fopen( $this->mFilename, 'rb' );
        if ( !$this->hFile ) {
            // Error handling
            return 0;
        }
        $this->Identifier = fread( $this->hFile, 2 );
        if ( $GLOBALS['dev'] ) echo $this->Identifier.PHP_EOL;
        if ( $this->Identifier==='II' ) { // little endian
            $this->mUnpackFormats = array( 
                1 => 'C*',
                2 => 'v*',
                4 => 'V*',
            );
        } else if ( $this->Identifier==='MM' ) { // big endian
            $this->mUnpackFormats = array( 
                1 => 'C*',
                2 => 'n*',
                4 => 'N*',
            );
        } else {
            // Error handling
        }
        $this->Version = current(unpack( $this->mUnpackFormats[2], fread( $this->hFile, 2 ) ));
        //echo $this->Version.PHP_EOL;
        $ifdoffset = current(unpack( $this->mUnpackFormats[4], fread( $this->hFile, 4 ) )); /* Offset to next IFD  */
        while ( $ifdoffset ) {
            //echo $ifdoffset.PHP_EOL;
            if ( fseek($this->hFile, $ifdoffset)===0 ) {
                $numdir = current(unpack( $this->mUnpackFormats[2], fread( $this->hFile, 2 ) )); /* Number of Tags in IFD  */
                $this->IFDList[$ifdoffset] = new ImageFileDirectory();
                $this->IFDList[$ifdoffset]->init( $this );
                $this->IFDList[$ifdoffset]->NumDirEntries = $numdir;
                //echo $numdir.PHP_EOL;
                for ( $i=0; $i<$numdir; $i++ ) {
                    $tagid = current(unpack( $this->mUnpackFormats[2], fread( $this->hFile, 2 ) )); /* The tag identifier  */
                    $datatype = current(unpack( $this->mUnpackFormats[2], fread( $this->hFile, 2 ) )); /* The scalar type of the data items  */
                    $datacount = current(unpack( $this->mUnpackFormats[4], fread( $this->hFile, 4 ) )); /* The number of items in the tag data  */
                    $dataoffset = current(unpack( $this->mUnpackFormats[4], fread( $this->hFile, 4 ) )); /* The byte offset to the data items  */
                    $datalength = $datacount * self::$DataTypeLength[$datatype];
                    $data = array();
                    //echo $i.': '.$tagid.', type: '.$datatype.', len: '.$datalength.', offset: '.$dataoffset.PHP_EOL;
                    if ( $datalength > 4 ) {
                        $fcurrent = ftell( $this->hFile );
                        if ( fseek($this->hFile, $dataoffset)===0 ) {
                            $unpackchar = self::$DataTypeUnpackFormat[$this->Identifier][$datatype];
                            for ( $j=0; $j<$datacount; $j++ ) {
                                if ( strlen($unpackchar)===1 ) {
                                    $data[] = current(unpack( $unpackchar, fread( $this->hFile, self::$DataTypeLength[$datatype] ) ));
                                    //if ( $datatype===2 ) echo $data;
                                    //if ( $tagid===324 ) echo $data.PHP_EOL;
                                } else {
                                    // data that is unable to be unpacked using single line PHP
                                    if ( ($unpackchar==='eX' && self::isLittleEndian()) || ($unpackchar==='EX' && !self::isLittleEndian()) ) { /* DOUBLE 8-byte little endian */
                                        $bytes = '';
                                        for ( $k=0; $k<self::$DataTypeLength[$datatype]; $k++ ) {
                                            $bytes .= fread( $this->hFile, 1 );
                                        }
                                        //echo bin2hex( $bytes ).PHP_EOL;
                                        $data[] = current(unpack( 'd', $bytes ));
                                    } else if ( ($unpackchar==='eX' && !self::isLittleEndian()) || ($unpackchar==='EX' && self::isLittleEndian()) ) { /* DOUBLE 8-byte big endian */
                                        $bytes = '';
                                        for ( $k=0; $k<self::$DataTypeLength[$datatype]; $k++ ) {
                                            $bytes = fread( $this->hFile, 1 ).$bytes;
                                        }
                                        //echo bin2hex( $bytes ).PHP_EOL;
                                        $data[] = current(unpack( 'd', $bytes ));
                                    }
                                }
                            }
                        } else {
                            // Error handling...
                            // fseek() failed
                        }
                        fseek( $this->hFile, $fcurrent );
                    } else {
                        $data = $dataoffset;
                    }
                    if ( count($data) ) {
                        switch ( $datatype ) {
                            case 2:
                                $data = trim(implode('', $data));
                                break;
                        }
                        switch ( $tagid ) {
                            case 256:
                                $this->IFDList[$ifdoffset]->ImageWidth = $data;
                                break;
                            case 257:
                                $this->IFDList[$ifdoffset]->ImageLength = $data;
                                break;
                            case 258:
                                $this->IFDList[$ifdoffset]->BitsPerSample = $data;
                                $this->IFDList[$ifdoffset]->BytesPerSample = intdiv($data, 8);
                                break;
                            case 259:
                                $this->IFDList[$ifdoffset]->Compression = $data;
                                break;
                            case 262:
                                $this->IFDList[$ifdoffset]->PhotometricInterpretation = $data;
                                break;
                            case 274:
                                $this->IFDList[$ifdoffset]->Orientation = $data;
                                break;
                            case 277:
                                $this->IFDList[$ifdoffset]->SamplesPerPixel = $data;
                                break;
                            case 282:
                                $this->IFDList[$ifdoffset]->XResolution = $data;
                                break;
                            case 283:
                                $this->IFDList[$ifdoffset]->YResolution = $data;
                                break;
                            case 296:
                                $this->IFDList[$ifdoffset]->ResolutionUnit = $data;
                                break;
                            case 322:
                                $this->IFDList[$ifdoffset]->TileWidth = $data;
                                break;
                            case 323:
                                $this->IFDList[$ifdoffset]->TileLength = $data;
                                break;
                            case 33550:
                                if ( count($data)>=3 ) {
                                    $data[1] *= -1;
                                    $this->IFDList[$ifdoffset]->ModelPixelScaleTag = $data;
                                } else {
                                    // Invalid data
                                }
                                break;
                            case 33922:
                                $this->IFDList[$ifdoffset]->ModelTiepointTag = $data;
                                break;
                            case 34735:
                                if ( count($data)>=8 ) { /* header + one directory = minial of 8 bytes for a valid GeoKeyDirectoryTag */
                                    for ( $j=4; $j<$data[3]*4+1; $j+=4 ) {
                                        $geokey = new GeoKeyDirectory( $data[$j], $data[$j+1], $data[$j+2], $data[$j+3], $this->IFDList[$ifdoffset] );
                                        $this->IFDList[$ifdoffset]->GeoKeyDirectoryTag[$geokey->KeyID] = $geokey;
                                        switch ( $geokey->KeyID ) {
                                            case 2048:
                                                $this->IFDList[$ifdoffset]->GeographicTypeGeoKey = $geokey->Value_Offset;
                                                break;
                                        }
                                    }
                                } else {
                                    // Invalid data
                                }
                                //Header={KeyDirectoryVersion, KeyRevision, MinorRevision, NumberOfKeys}
                                //KeyEntry = { KeyID, TIFFTagLocation, Count, Value_Offset }
                                break;
                            case 34736:
                                $this->IFDList[$ifdoffset]->GeoDoubleParamsTag = $data;
                                break;
                        }
                        $this->IFDList[$ifdoffset]->TagList[$tagid] = $data;
                    } else {
                        // Error handling...
                        // data not read correctly
                    }
                }
                
                $this->IFDList[$ifdoffset]->TileColumnNum = intdiv($this->IFDList[$ifdoffset]->ImageWidth - 1, $this->IFDList[$ifdoffset]->TileWidth) + 1;
                $this->IFDList[$ifdoffset]->TileRowNum = intdiv($this->IFDList[$ifdoffset]->ImageLength - 1, $this->IFDList[$ifdoffset]->TileLength) + 1;
                $this->IFDList[$ifdoffset]->TileBytesPerRow = $this->IFDList[$ifdoffset]->TileWidth * $this->IFDList[$ifdoffset]->BytesPerSample;

                /*
                $bps = $this->IFDList[$ifdoffset]->BytesPerSample;
                $min = 65535;
                $max = 0;
                $count = 0;
                $mean = 0.0;
                $sqrmean = 0.0;
                $current = ftell( $this->hFile );
                $iterations = 0;
                $now = microtime(true) * 1000;
                foreach ( $this->IFDList[$ifdoffset]->TagList[324] as $index => $toffset ) {
                    fseek( $this->hFile, $toffset );
                    $remaining = $this->IFDList[$ifdoffset]->TagList[325][$index];
                    //echo 'offset: '.$toffset.' + '.$remaining.PHP_EOL;
                    while ( $remaining && !feof($this->hFile) ) {
                        $buffer = fread( $this->hFile, $remaining - $remaining%$bps );
                        $remaining -= strlen($buffer);
                        foreach ( unpack( $this->mUnpackFormats[$bps], $buffer ) as $value ) {
                            if ( $value > 0 ) {
                                $count++;
                                if ( $value > $max ) $max = $value;
                                if ( $value < $min ) $min = $value;
                                $mean = $mean*($count-1)/$count + $value/$count;
                                $sqrmean = $sqrmean*($count-1)/$count + $value*$value/$count;
                                if ( $count<=5 ) {
                                    //echo sprintf( 'value: %d, max: %d, min: %d'.PHP_EOL, $value, $max, $min );
                                    //echo sprintf( 'count: %d, mean: %.2f, sqr mean: %.2f'.PHP_EOL, $count, $mean, $sqrmean );
                                }
                            }
                        }
                    }
                    echo sprintf( 'value: %d, max: %d, min: %d'.PHP_EOL, $value, $max, $min );
                    echo sprintf( 'count: %d, mean: %.2f, sqr mean: %.2f'.PHP_EOL, $count, $mean, $sqrmean );
                    $iterations++;
                    echo 'average time taken: '.((microtime(true)*1000-$now)/$iterations).' ms'.PHP_EOL;
                }
                fseek( $this->hFile, $current );
                echo sprintf( 'value: %d, max: %d, min: %d'.PHP_EOL, $value, $max, $min );
                echo sprintf( 'count: %d, mean: %.2f, sqr mean: %.2f'.PHP_EOL, $count, $mean, $sqrmean );
                */
                
                $ifdoffset = current(unpack( $this->mUnpackFormats[1], fread( $this->hFile, 4 ) )); /* Offset to next IFD  */
                //echo 'next: '.$ifdoffset.PHP_EOL;
            } else {
                // Error handling...
                // fseek() failed
                $ifdoffset = 0;
            }
            // End of reading all ImageFileDirectory (IFD)
        }

        /*
        if ( isset($ifd->TagList[33922]) && count($ifd->TagList[33922])>=6 ) {
            $UpperLeft = new GeoPoint( $ifd->TagList[33922][4], $ifd->TagList[33922][3] );
            echo $UpperLeft->getReadable().PHP_EOL;
            $UpperLeft->lon += $ifd->ImageWidth * $ifd->TagList[33550][0];
            $UpperLeft->lat += $ifd->ImageLength * $ifd->TagList[33550][1];
            echo $UpperLeft->getReadable().PHP_EOL;
            echo PHP_EOL;
        }
        */
        
        if ( $GLOBALS['dev'] > 1 ) print_r( $this->IFDList );
        
        self::saveToCache( $cachepath, $this->IFDList );

        return 2;
        // End of open()
    }

    public function getValue( &$point ) {
        reset($this->IFDList);
        $ifd = current($this->IFDList);
        $ifd->getValue( $point );
    }

    public function getTile( &$point, $format='png' ) {
        reset($this->IFDList);
        $ifd = current($this->IFDList);
        $ifd->getTile( $point, $format );
    }
}
          
// Creating a new person called "boring 12345", who is 12345 years old ;-)
$geotiff = new GeoTIFF();
if ( $geotiff->open( __DIR__.DIRECTORY_SEPARATOR.'twdtm_asterV2_30m.tif' ) ) {

    //$point = new GeoPoint( 23.47, 120.95728 );
    //$point = new GeoPoint( 24.80635,121.4768 ); /* Mt. Woo */
    $point = new GeoPoint( 23.90165,121.32335 ); /* Mt. Pinnacle 2313 */
    //$point = new GeoPoint( 24.48084,121.47884 );
    $geotiff->getTile( $point );

    if ( $GLOBALS['dev'] ) {
        $samples = array(
            new GeoPoint( 26.000139, 118.999861 ), /* Upper Left 0m */
            new GeoPoint( 25.67371,119.84161 ), /* Some where in first tile 0m */
            new GeoPoint( 23.47, 120.95728 ), /* Mt. Tonku Saveq 3952m */
            new GeoPoint( 23.90165,121.32335 ), /* Mt. Pinnacle 2313 */
            new GeoPoint( 24.44647,121.61394 ), /* Haga-Paris 911m */
            new GeoPoint( 22.69926,120.94559 ), /* Mt. Katana 1666m */
            new GeoPoint( 24.48084,121.47884 ), /* Karasan */
            new GeoPoint( 24.80635,121.4768 ), /* Mt. Woo */
        );

        foreach ( $samples as $sample ) {
            echo $sample->getReadable().PHP_EOL;
            $geotiff->getValue( $sample );
            $geotiff->getTile( $sample );
            echo PHP_EOL.PHP_EOL;
        }
    }
}

unset( $geotiff );

?>