<?php
/** Kleine A4-PDF-schrijver zonder serverafhankelijkheden. Alle maten in punten. */
class OffertePdf {
    private const NORMAL_WIDTHS = [600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584,350,556,350,222,556,333,1000,556,556,333,1000,667,333,1000,350,611,350,350,222,222,333,333,350,556,1000,333,1000,500,333,944,350,500,667,278,333,556,556,556,556,260,556,333,737,370,556,584,333,737,333,400,584,333,333,333,556,537,278,333,333,365,556,834,834,834,611,667,667,667,667,667,667,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,500,556,556,556,556,278,278,278,278,556,556,556,556,556,556,556,584,611,556,556,556,556,500,556,500];
    private const BOLD_WIDTHS = [600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,600,278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584,350,556,350,278,556,500,1000,556,556,333,1000,667,333,1000,350,611,350,350,278,278,500,500,350,556,1000,333,1000,556,333,944,350,500,667,278,333,556,556,556,556,280,556,333,737,370,556,584,333,737,333,400,584,333,333,333,611,556,278,333,333,365,556,834,834,834,611,722,722,722,722,722,722,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,556,556,556,556,556,278,278,278,278,611,611,611,611,611,611,611,584,611,611,611,611,611,556,611,556];
    private $pages = array();
    private $stream = '';
    private $y = 46;
    private function rgb($hex) {
        return implode(' ', array_map(function($v) { return sprintf('%.3F', hexdec($v)/255); }, str_split(ltrim($hex,'#'),2)));
    }
    private function encoded($text) {
        $s = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        return str_replace(array('\\','(',')',"\r","\n"), array('\\\\','\\(','\\)','',' '), $s === false ? '?' : $s);
    }
    public function text($x, $y, $text, $size = 10, $bold = false, $color = '#203345') {
        $this->stream .= 'BT /' . ($bold ? 'F2' : 'F1') . ' ' . $size . ' Tf ' . $this->rgb($color) . ' rg 1 0 0 1 ' . $x . ' ' . (842-$y) . ' Tm (' . $this->encoded($text) . ") Tj ET\n";
    }
    private function textWidth($text, $size, $bold) {
        $bytes = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        $widths = $bold ? self::BOLD_WIDTHS : self::NORMAL_WIDTHS;
        $width = 0;
        foreach (unpack('C*', $bytes === false ? '?' : $bytes) ?: array() as $char) { $width += $widths[$char]; }
        return $width * $size / 1000;
    }
    private function rect($x,$y,$w,$h,$color) {
        $this->stream .= $this->rgb($color) . ' rg ' . $x . ' ' . (842-$y-$h) . ' ' . $w . ' ' . $h . " re f\n";
    }
    private function space($height) {
        if ($this->y + $height > 775) {
            $this->pages[] = $this->stream; $this->stream=''; $this->y=56;
            $this->text(42,30,'Immens Stucwerken | Offerte, vervolg',9,true,'#174c85');
        }
    }
    public function paragraph($text, $size=10, $bold=false, $color='#203345') {
        // Houd ook lange woorden en e-mailadressen binnen de tekstkolom.
        $limit = 511;
        foreach (explode("\n", str_replace("\r",'', $text)) as $paragraph) {
            $line='';
            foreach (preg_split('/\s+/u', $paragraph) as $word) {
                while ($this->textWidth($word,$size,$bold)>$limit) {
                    if ($line!=='') { $this->space($size+5); $this->text(42,$this->y,$line,$size,$bold,$color); $this->y+=$size+5; $line=''; }
                    $length=1;
                    while($length<mb_strlen($word) && $this->textWidth(mb_substr($word,0,$length+1),$size,$bold)<=$limit){$length++;}
                    $this->space($size+5); $this->text(42,$this->y,mb_substr($word,0,$length),$size,$bold,$color); $this->y+=$size+5; $word=mb_substr($word,$length);
                }
                if ($line!=='' && $this->textWidth($line.' '.$word,$size,$bold)>$limit) { $this->space($size+5); $this->text(42,$this->y,$line,$size,$bold,$color); $this->y+=$size+5; $line=''; }
                $line.=($line!==''?' ':'').$word;
            }
            $this->space($size+5); $this->text(42,$this->y,$line,$size,$bold,$color); $this->y+=$size+5;
        }
        $this->y+=5;
    }
    public function quotation($record) {
        $config=$record['instellingen']; $business=$config['bedrijf']; $customer=$record['klant']; $calc=$record['berekening'];
        $this->paragraph($business['naam'],18,true,'#174c85');
        $this->paragraph(implode("\n",array_filter(array($business['adres'],$business['telefoon'],$business['email'], $business['kvk']!==''?'KvK: '.$business['kvk']:'', $business['btw_nummer']!==''?'Btw-nummer: '.$business['btw_nummer']:''))),9);
        $this->y+=10;
        $this->paragraph('Voorlopige offerte',20,true,'#174c85');
        $this->paragraph($record['id'].' | Datum: '.date('d-m-Y',strtotime($record['gemaakt_op'])).' | Geldig t/m: '.date('d-m-Y',strtotime($record['geldig_tot'])),10);
        $this->paragraph('Voor: '.$customer['naam']."\n".$customer['adres']."\n".$customer['postcode'].' '.$customer['plaats']."\n".$customer['email'].($customer['telefoon']!==''?"\n".$customer['telefoon']:''),10);
        $this->y+=10;
        $this->space(45);
        $this->rect(42,$this->y-13,511,27,'#174c85');
        foreach (array(array(50,'Omschrijving'),array(290,'Aantal'),array(355,'Tarief'),array(455,'Excl. btw')) as $col) { $this->text($col[0],$this->y+4,$col[1],10,true,'#ffffff'); }
        $this->y+=36;
        foreach ($calc['regels'] as $row) {
            $name=$row['naam']; $parts=array();
            while (mb_strlen($name)>38) { $parts[]=mb_substr($name,0,38); $name=mb_substr($name,38); }
            $parts[]=$name;
            $height=max(31,count($parts)*13+10); $this->space($height);
            foreach($parts as $i=>$part) { $this->text(50,$this->y+$i*13,$part,9); }
            $this->text(290,$this->y,offerte_aantal($row['aantal_honderdste']).' '.$row['eenheid'],9);
            $this->text(355,$this->y,offerte_euro($row['prijs_cent']),9);
            $this->text(455,$this->y,offerte_euro($row['totaal_cent']),9);
            $this->rect(42,$this->y+$height-16,511,.5,'#d8e0e6');
            $this->y+=$height;
        }
        $this->space(100); $this->y+=8;
        foreach(array(array('Subtotaal excl. btw',$calc['subtotaal_cent']),array('Btw ('.$calc['btw_percentage'].'%)',$calc['btw_cent']),array('Totaal incl. btw',$calc['totaal_cent'])) as $i=>$row) {
            $this->text(300,$this->y,$row[0],$i===2?12:10,$i===2);
            $this->text(455,$this->y,offerte_euro($row[1]),$i===2?12:10,$i===2);
            $this->y+=24;
        }
        $types=array('woning-oud'=>'Woning ouder dan twee jaar','woning-nieuw'=>'Woning van twee jaar of jonger','overig'=>'Ander gebouw','onbekend'=>'Ouderdom of gebruik onbekend');
        $this->y+=8; $this->paragraph('Opgegeven pand: '.$types[$calc['pand']],9);
        $this->paragraph('Voorwaarden',11,true);
        $this->paragraph($config['voorwaarden'],9);
        if ($customer['opmerking']!=='') { $this->paragraph('Jouw toelichting',11,true); $this->paragraph($customer['opmerking'],9); }
        $this->paragraph('Bewaar deze offerte. Neem contact op met '.$business['naam'].' om je project en de definitieve prijs af te stemmen.',9);
    }
    public function bytes() {
        $this->pages[]=$this->stream;
        $objects=array(1=>'<< /Type /Catalog /Pages 2 0 R >>', 3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',4=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');
        $kids=array(); $total=count($this->pages);
        foreach($this->pages as $i=>$stream) {
            $page=5+$i*2; $content=$page+1; $kids[]=$page.' 0 R';
            $this->stream=''; $this->text(42,812,'Voorlopige offerte | Pagina '.($i+1).' van '.$total,8,false,'#546575');
            $stream.=$this->stream;
            $objects[$page]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$content.' 0 R >>';
            $objects[$content]='<< /Length '.strlen($stream).' >>' . "\nstream\n".$stream."endstream";
        }
        $objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.$total.' >>'; ksort($objects);
        $pdf="%PDF-1.4\n%\xe2\xe3\xcf\xd3\n"; $offsets=array(0);
        foreach($objects as $id=>$body) { $offsets[$id]=strlen($pdf); $pdf.=$id." 0 obj\n".$body."\nendobj\n"; }
        $xref=strlen($pdf); $pdf.='xref'."\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
        for($id=1;$id<=count($objects);$id++) { $pdf.=sprintf('%010d 00000 n ',$offsets[$id])."\n"; }
        return $pdf.'trailer << /Size '.(count($objects)+1).' /Root 1 0 R >>'."\nstartxref\n".$xref."\n%%EOF\n";
    }
}
function offerte_pdf($record) { $pdf=new OffertePdf(); $pdf->quotation($record); return $pdf->bytes(); }
