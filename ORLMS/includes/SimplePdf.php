<?php
declare(strict_types=1);

/**
 * Very small text-only PDF writer for ORLMS report exports.
 * It intentionally avoids external packages so the XAMPP deployment
 * can generate basic PDF reports offline.
 */
final class SimplePdf
{
    private array $pages=[[]];
    private int $page=0;
    private int $maxLines=48;

    public function addLine(string $text='',int $fontSize=10): void
    {
        $text=$this->normalize($text);
        foreach($this->wrap($text,$fontSize>=14?70:($fontSize>=11?92:110)) as $line){
            if(count($this->pages[$this->page]) >= $this->maxLines){
                $this->page++;
                $this->pages[$this->page]=[];
            }
            $this->pages[$this->page][]=[$line,$fontSize];
        }
    }

    public function addBlank(): void
    {
        $this->addLine('',10);
    }

    public function output(string $filename='report.pdf'): void
    {
        $objects=[];
        $pageObjectIds=[];
        $contentObjectIds=[];

        // 1 catalog, 2 pages tree, 3 font
        $objects[1]='<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        $nextId=4;
        foreach($this->pages as $page){
            $pageObjectIds[]=$nextId++;
            $contentObjectIds[]=$nextId++;
        }

        $kids=[];
        foreach($pageObjectIds as $id)$kids[]=$id.' 0 R';
        $objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($pageObjectIds).' >>';

        foreach($this->pages as $i=>$lines){
            $content='';
            $y=805;
            foreach($lines as [$line,$size]){
                $escaped=$this->escapePdf($line);
                $content.="BT /F1 {$size} Tf 45 {$y} Td ({$escaped}) Tj ET\n";
                $y-=($size>=14?20:($size>=11?16:14));
            }

            $contentId=$contentObjectIds[$i];
            $pageId=$pageObjectIds[$i];
            $objects[$contentId]="<< /Length ".strlen($content)." >>\nstream\n".$content."endstream";
            $objects[$pageId]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$contentId.' 0 R >>';
        }

        ksort($objects);
        $pdf="%PDF-1.4\n";
        $offsets=[0=>0];

        foreach($objects as $id=>$body){
            $offsets[$id]=strlen($pdf);
            $pdf.=$id." 0 obj\n".$body."\nendobj\n";
        }

        $xref=strlen($pdf);
        $max=max(array_keys($objects));
        $pdf.="xref\n0 ".($max+1)."\n";
        $pdf.="0000000000 65535 f \n";

        for($i=1;$i<=$max;$i++){
            $pdf.=sprintf("%010d 00000 n \n",$offsets[$i]??0);
        }

        $pdf.="trailer\n<< /Size ".($max+1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="'.preg_replace('/[^A-Za-z0-9_.-]/','_',$filename).'"');
        header('Content-Length: '.strlen($pdf));
        echo $pdf;
        exit;
    }

    private function normalize(string $text): string
    {
        $text=str_replace(["\r","\n","\t"],[' ',' ',' '],$text);
        $converted=@iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$text);
        return trim($converted!==false?$converted:$text);
    }

    private function wrap(string $text,int $width): array
    {
        if($text==='')return [''];
        $wrapped=wordwrap($text,$width,"\n",true);
        return explode("\n",$wrapped);
    }

    private function escapePdf(string $text): string
    {
        return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$text);
    }
}
