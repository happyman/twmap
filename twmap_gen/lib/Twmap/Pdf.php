<?php
Namespace Happyman\Twmap\Export;

class Pdf {
	var $infiles;
	var $outfiles;
	var $info_tmp = "";
	var $do = 1;
	var $print_cmd = 1;
	var $subject = "地圖產生器";
	var $title = "地圖產生器";
	var $outfile = "";
	var $do_cleanup = 1;
	var $paramok = 0;
	var $a3 = 0;
	var $twmap_ver;
	function __construct($opt) {
		if (!isset($opt['infiles']) || !isset($opt['outfile'])){
			echo "require parameters";
			return false;
		}
		else  {
			$this->infiles = $opt['infiles'];
			$this->outfile = $opt['outfile'];
			$this->info_tmp = dirname($this->outfile) . "/info.txt";
			$this->paramok = 1;
		}
		if (isset($opt['title'])) $this->title = $opt['title'];
		if (isset($opt['subject'])) $this->subject = $opt['subject'];
		if (isset($opt['print_only']) && $opt['print_only'] == 1) $this->do = 0;
		if (isset($opt['do_cleanup']) && $opt['do_cleanup'] == 1) $this->do_cleanup = 0;
		if (isset($opt['quiet']) && $opt['quiet'] == 1) $this->print_cmd = 0;
		if (isset($opt['outfile'])) $this->outfile = $opt['outfile'];
		if (isset($opt['a3'])) $this->a3 = $opt['a3'];
		if (isset($opt['twmap_ver'])) $this->twmap_ver = $opt['twmap_ver'];
	}

	function png2pdf() {
		$i=0;
		foreach($this->infiles as $infile) {
			$this->outfiles[$i] = $infile.".pdf";
			// consider margin
			if ($this->a3 == 1)
			$cmd =sprintf("cat %s | pngtopnm | pnmtops -width 11.69 -height 16.53 -imagewidth 11.69 -imageheight 16.53 |ps2pdf -r300x300 -sPAPERSIZE=a3 -dOptimize=true -dEmbedAllFonts=true - %s", $infile, $this->outfiles[$i]);
				else
			$cmd =sprintf("cat %s | pngtopnm | pnmtops -width 8.27 -height 11.69 -imagewidth 8.27 -imageheight 11.69 |ps2pdf -r300x300 -sPAPERSIZE=a4 -dOptimize=true -dEmbedAllFonts=true - %s", $infile, $this->outfiles[$i]);
			$i++;
			if ($this->print_cmd)
				echo "$cmd\n";
			if ($this->do)
				exec($cmd);
		}
	}
	function merge_pdf() {
		$outfiles_line = implode(" ",$this->outfiles);
		$cmd=sprintf("gs -dOptimize=true -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile=%s %s %s;", $this->outfile, $outfiles_line, $this->info_tmp);
		if ($this->print_cmd)
			echo "$cmd\n";
		if ($this->do)
			exec($cmd);
	}
	function cleanup() {
		$outfiles_line = implode(" ",$this->outfiles);
		$cmd = "rm ". $outfiles_line . "  $this->info_tmp";
		if ($this->print_cmd)
			echo "$cmd\n";
		if ($this->do)
			exec($cmd);
	}


	function doit() {
		if ($this->paramok == 1 ) {
			$this->create_pdf_meta();
			$this->png2pdf();
			$this->merge_pdf();
			if ($this->do_cleanup == 1)
				$this->cleanup();
			if (file_exists($this->outfile)) {
				return $this->outfile;
			}
		}
		return false;
	}

	function create_pdf_meta() {
		$out[] = "[ /Author (happyman) \n";
		$out[] = sprintf(" /Creator %s\n", $this->str_in_pdf("地圖產生器 v" .$this->twmap_ver ));
		$out[] = sprintf(" /Title %s\n", $this->str_in_pdf($this->title));
		$out[] = sprintf(" /Subject %s\n", $this->str_in_pdf($this->subject));
		$out[] = " /Keywords (Taiwan Hiking Map)\n /DOCINFO pdfmark\n";
		file_put_contents($this->info_tmp, implode("",$out));
	}

	function str_in_pdf($str){
		$cmd = sprintf("echo '%s'| iconv -f utf8 -t utf-16 |od -x -A none",$str);
		if ($this->print_cmd)
			echo $cmd ."\n";
		if ($this->do)
			 exec($cmd,$out,$ret);
			//echo "ret=$ret\n" . print_r($out);
		return "<" . str_replace(" ","",implode("",$out)) .">";
	}
}

