<?php
namespace Database\Seeders\V2;

/** Original synthetic records. Never reproduces protected university forms or signatures. */
final class SeedDocumentWriter
{
    public static function pdf(string $title, string $reference, array $headers, array $rows): string
    {
        $escape = static fn ($s) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], preg_replace('/[^\x20-\x7E]/', ' ', (string) $s));
        $text = static fn ($x, $y, $size, $s) => "BT /F1 $size Tf $x $y Td (".$escape($s).") Tj ET\n";
        $pages = []; $body = ''; $y = 726;
        $heading = static function () use ($text, $title, $reference) {
            return "0.02 0.13 0.35 rg 36 752 540 34 re f\n1 1 1 rg\n".$text(46, 765, 13, 'COMPLITRACK | INTERNAL QUALITY AUDIT')."0.1 0.15 0.23 rg\n".$text(36, 737, 12, $title).$text(36, 721, 9, $reference);
        };
        $body = $heading(); $y = 698;
        foreach ($rows as $i => $row) {
            // A vertical field register remains readable even with fourteen CAR columns.
            $lines = [];
            foreach ($headers as $j => $label) {
                $value = (string) ($row[$j] ?? 'Not applicable');
                foreach (explode("\n", wordwrap($label.': '.$value, 92, "\n", true)) as $line) { $lines[] = $line; }
            }
            if ($y - 14 * count($lines) - 30 < 58) { $pages[] = $body; $body = $heading(); $y = 698; }
            $body .= "0.91 0.94 0.98 rg 36 ".($y-6)." 540 19 re f\n0.02 0.13 0.35 rg\n".$text(43, $y, 10, 'Record '.($i+1)); $y -= 24;
            foreach ($lines as $line) { if ($y < 60) { $pages[] = $body; $body = $heading(); $y = 698; } $body .= "0.1 0.15 0.23 rg\n".$text(43, $y, 9, $line); $y -= 14; }
            $y -= 12;
        }
        $pages[] = $body; $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>']; $kids=[];
        foreach ($pages as $i => $stream) { $p = 4 + $i * 2; $s = $p+1; $kids[] = "$p 0 R"; $stream .= "0.35 0.39 0.45 rg\n".$text(36, 36, 8, 'Simulation dataset - Not an official USTP record | Page '.($i+1)); $objects[$p] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> >> /Contents $s 0 R >>"; $objects[$s] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream.'endstream'; }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>'; ksort($objects); $pdf = "%PDF-1.4\n"; $offsets = [0];
        foreach ($objects as $id => $obj) { $offsets[$id] = strlen($pdf); $pdf .= "$id 0 obj\n$obj\nendobj\n"; }
        $xref = strlen($pdf); $pdf .= 'xref' . "\n0 ".count($offsets)."\n0000000000 65535 f \n"; foreach (array_slice($offsets,1) as $offset) { $pdf .= sprintf('%010d 00000 n ', $offset)."\n"; }
        return $pdf.'trailer'."\n<< /Size ".count($offsets)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }

    /** Minimal standards-based OOXML workbook, with real inline string cells. */
    public static function xlsx(string $title, array $headers, array $rows): string
    {
        $xml = static fn ($v) => htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $sheet = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="3" topLeftCell="A4" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="'.count($headers).'" width="28" customWidth="1"/></cols><sheetData>';
        foreach ([[$title], ['Simulation dataset - Not an official USTP record'], $headers, ...$rows] as $i=>$row) { $n=$i+1; $sheet .= '<row r="'.$n.'">'; foreach ($row as $j=>$value) { $col=chr(65+$j); $sheet .= '<c r="'.$col.$n.'" t="inlineStr"><is><t xml:space="preserve">'.$xml($value).'</t></is></c>'; } $sheet.='</row>'; }
        $sheet .= '</sheetData><autoFilter ref="A3:'.chr(64+count($headers)).(3+count($rows)).'"/></worksheet>';
        return self::zip([
            '[Content_Types].xml'=>'<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
            '_rels/.rels'=>'<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml'=>'<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="CAR Register" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels'=>'<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
            'xl/worksheets/sheet1.xml'=>$sheet,
        ]);
    }

    private static function zip(array $files): string
    {
        $data=''; $directory=''; foreach ($files as $name=>$content) { $offset=strlen($data); $len=strlen($content); $crc=crc32($content); $n=strlen($name); $data.=pack('VvvvvvVVVvv',0x04034b50,20,0,0,0,33,$crc,$len,$len,$n,0).$name.$content; $directory.=pack('VvvvvvvVVVvvvvvVV',0x02014b50,20,20,0,0,0,33,$crc,$len,$len,$n,0,0,0,0,0,$offset).$name; } return $data.$directory.pack('VvvvvVVv',0x06054b50,0,0,count($files),count($files),strlen($directory),strlen($data),0);
    }
}
