<?php

//http://www.fileformat.info/format/tiff/egff.htm
//https://www.loc.gov/preservation/digital/formats/content/tiff_tags.shtml

header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

class GeoPoint {
    public $mFormat;
    public $x;
    public $y;
    public $lon;
    public $lat;
    
    public function __construct( $horizontal, $vertical, $format=4326 ) {
        $this->mFormat = $format;
        if ( $format===4326 ) {
            $this->lon = $horizontal;
            $this->lat = $vertical;
        } else {
            $this->x = $horizontal;
            $this->y = $vertical;
        }
    }
    
    public function add ( &$vector ) {
        if ( $vector->mFormat===$this->mFormat ) {
            if ( $format===4326 ) {
                $this->lon += $vector->lon;
                $this->lat += $vector->lat;
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

class ImageFileDirectory {
    public $NumDirEntries;

    public $ImageWidth; /* 256 The number of columns in the image, i.e., the number of pixels per row. */
    public $ImageLength; /* 257 The number of rows of pixels in the image. */
    public $BitsPerSample; /* 258 Number of bits per component. */
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
    
    public $SampleFormat; /* 339 Specifies how to interpret each data sample in a pixel. */
    public $ModelPixelScaleTag; /* 33550 Used in interchangeable GeoTIFF_1_0 files. */
    public $ModelTiepointTag; /* 33922 Originally part of Intergraph's GeoTIFF tags, but now used in interchangeable GeoTIFF_1_0 files. */
    public $ModelTransformationTag; /* 34264 (JPL Carto Group) */
    public $GeoKeyDirectoryTag; /* 34735 Used in interchangeable GeoTIFF_1_0 files. This tag is also know as 'ProjectionInfoTag' and 'CoordSystemInfoTag' */
    public $GeoDoubleParamsTag; /* 34736 Used in interchangeable GeoTIFF_1_0 files. */
    public $GeoAsciiParamsTag; /* 34737 Used in interchangeable GeoTIFF_1_0 files. */
    
    public $TagList = array(); /* tagid => data are stored in this array to be referenced by GeoKeyDirectoryTag with TIFFTagLocation */
}

class GeoTIFF {
    public $mFilename;
    private $hFile;
    
    private $mUnpackFormats;
    
    public $Identifier; /* Byte-order Identifier */
    public $Version;    /* TIFF version number (always 2Ah) */
    public $IFDOffset;  /* Offset of the first Image File Directory*/
    public $IFDList = array();
    
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
            // 7.0.15,7.1.1	The "e", "E", "g" and "G" codes were added to enable byte order support for float and double.
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
            // 7.0.15,7.1.1	The "e", "E", "g" and "G" codes were added to enable byte order support for float and double.
            11  => 'GX', /* FLOAT 4-byte single-precision IEEE floating-point value */
            12  => 'EX', /* DOUBLE 8-byte double-precision IEEE floating-point value */
        ),
    );
    
    public function __construct() {
        $this->TagList = array();
    }
    
    public function __destruct () {
        fclose( $this->hFile );
    }

    public static function isLittleEndian() {
        $testint = 0x00FF;
        $p = pack('S', $testint);
        return $testint===current(unpack('v', $p));
    }
    
    public function open( $filename ) {
        $this->mFilename = $filename;
        $this->hFile = fopen( $this->mFilename, 'r' );
        if ( !$this->hFile ) {
            // Error handling
            die();
        }
        $this->Identifier = fread( $this->hFile, 2 );
        echo $this->Identifier.PHP_EOL;
        if ( $this->Identifier==='II' ) { // little endian
            $this->mUnpackFormats = array( 'v', 'V', );
        } else if ( $this->Identifier==='MM' ) { // big endian
            $this->mUnpackFormats = array( 'n', 'N', );
        } else {
            // Error handling
        }
        $this->Version = current(unpack( $this->mUnpackFormats[0], fread( $this->hFile, 2 ) ));
        echo $this->Version.PHP_EOL;
        $ifdoffset = current(unpack( $this->mUnpackFormats[1], fread( $this->hFile, 4 ) )); /* Offset to next IFD  */
        while ( $ifdoffset ) {
            echo $ifdoffset.PHP_EOL;
            if ( fseek($this->hFile, $ifdoffset)===0 ) {
                $numdir = current(unpack( $this->mUnpackFormats[0], fread( $this->hFile, 2 ) )); /* Number of Tags in IFD  */
                $this->IFDList[$ifdoffset] = new ImageFileDirectory();
                $this->IFDList[$ifdoffset]->NumDirEntries = $numdir;
                echo $numdir.PHP_EOL;
                for ( $i=0; $i<$numdir; $i++ ) {
                    $tagid = current(unpack( $this->mUnpackFormats[0], fread( $this->hFile, 2 ) )); /* The tag identifier  */
                    $datatype = current(unpack( $this->mUnpackFormats[0], fread( $this->hFile, 2 ) )); /* The scalar type of the data items  */
                    $datacount = current(unpack( $this->mUnpackFormats[1], fread( $this->hFile, 4 ) )); /* The number of items in the tag data  */
                    $dataoffset = current(unpack( $this->mUnpackFormats[1], fread( $this->hFile, 4 ) )); /* The byte offset to the data items  */
                    $datalength = $datacount * self::$DataTypeLength[$datatype];
                    $data = array();
                    echo $i.': '.$tagid.', type: '.$datatype.', len: '.$datalength.', offset: '.$dataoffset.PHP_EOL;
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
                        switch ( $tagid ) {
                            case 256:
                                $this->IFDList[$ifdoffset]->ImageWidth = $data;
                                break;
                            case 257:
                                $this->IFDList[$ifdoffset]->ImageLength = $data;
                                break;
                            case 258:
                                $this->IFDList[$ifdoffset]->BitsPerSample = $data;
                                break;
                            case 259:
                                $this->IFDList[$ifdoffset]->Compression = $data;
                                break;
                            case 262:
                                $this->IFDList[$ifdoffset]->PhotometricInterpretation = $data;
                                break;
                            case 274:
                                $this->IFDList[$ifdoffset]->PhotometricInterpretation = $data;
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
                            case 305:
                            case 34737:
                                $data = trim(implode('', $data));
                                break;
                            default:
                                break;
                        }
                        $this->IFDList[$ifdoffset]->TagList[$tagid] = $data;
                    } else {
                        // Error handling...
                        // data not read correctly
                    }
                }
                $ifdoffset = current(unpack( $this->mUnpackFormats[1], fread( $this->hFile, 4 ) )); /* Offset to next IFD  */
                echo 'next: '.$ifdoffset.PHP_EOL;
            } else {
                // Error handling...
                // fseek() failed
                $ifdoffset = 0;
            }
            // End of reading all ImageFileDirectory (IFD)
        }
        echo PHP_EOL;
        
        foreach ( $this->IFDList as $ifd ) {
            if ( isset($ifd->TagList[33922]) && count($ifd->TagList[33922])>=6 ) {
                $UpperLeft = new GeoPoint( $ifd->TagList[33922][3], $ifd->TagList[33922][4] );
                echo $UpperLeft->getReadable().PHP_EOL;
                $UpperLeft->lon += $ifd->ImageWidth * $ifd->TagList[33550][0];
                $UpperLeft->lat += $ifd->ImageLength * $ifd->TagList[33550][1];
                echo $UpperLeft->getReadable().PHP_EOL;
                echo PHP_EOL;
            }
        }
        
        print_r( $this->IFDList );
        
    }
}
          
// Creating a new person called "boring 12345", who is 12345 years old ;-)
$tiff = new GeoTIFF();
$tiff->open( __DIR__.'/twdtm_asterV2_30m.tif' );
unset( $tiff );

?>