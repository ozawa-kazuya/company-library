<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class BookSpreadsheetWriter
{
    /**
     * @param  list<array{name: string, rows: list<list<string>>}>  $sheets
     */
    public function write(array $sheets): string
    {
        if ($sheets === []) {
            throw new RuntimeException('出力するシートがありません。');
        }

        $path = tempnam(sys_get_temp_dir(), 'library-xlsx-');

        if ($path === false) {
            throw new RuntimeException('一時ファイルを作成できませんでした。');
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new RuntimeException('XLSX ファイルを作成できませんでした。');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes($sheets));
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook($sheets));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels($sheets));

        foreach ($sheets as $index => $sheet) {
            $zip->addFromString(
                'xl/worksheets/sheet'.($index + 1).'.xml',
                $this->worksheet($sheet['rows'] ?? []),
            );
        }

        $zip->close();

        return $path;
    }

    /**
     * @param  list<array{name: string, rows: list<list<string>>}>  $sheets
     */
    private function contentTypes(array $sheets): string
    {
        $overrides = [
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>',
        ];

        foreach ($sheets as $index => $sheet) {
            $overrides[] = '<Override PartName="/xl/worksheets/sheet'.($index + 1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .implode('', $overrides)
            .'</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    /**
     * @param  list<array{name: string, rows: list<list<string>>}>  $sheets
     */
    private function workbook(array $sheets): string
    {
        $sheetNodes = [];

        foreach ($sheets as $index => $sheet) {
            $name = $this->sheetName((string) ($sheet['name'] ?? ('Sheet'.($index + 1))));
            $sheetNodes[] = '<sheet name="'.$this->xml($name).'" sheetId="'.($index + 1).'" r:id="rId'.($index + 1).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.implode('', $sheetNodes).'</sheets>'
            .'</workbook>';
    }

    /**
     * @param  list<array{name: string, rows: list<list<string>>}>  $sheets
     */
    private function workbookRels(array $sheets): string
    {
        $rels = [];

        foreach ($sheets as $index => $sheet) {
            $rels[] = '<Relationship Id="rId'.($index + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($index + 1).'.xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .implode('', $rels)
            .'</Relationships>';
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function worksheet(array $rows): string
    {
        $rowXml = [];

        foreach (array_values($rows) as $rowIndex => $cells) {
            $rowNumber = $rowIndex + 1;
            $cellXml = [];

            foreach (array_values($cells) as $columnIndex => $value) {
                $reference = $this->columnLetter($columnIndex).$rowNumber;
                $text = $this->xml((string) $value);
                $cellXml[] = '<c r="'.$reference.'" t="inlineStr"><is><t xml:space="preserve">'.$text.'</t></is></c>';
            }

            $rowXml[] = '<row r="'.$rowNumber.'">'.implode('', $cellXml).'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.implode('', $rowXml).'</sheetData>'
            .'</worksheet>';
    }

    private function sheetName(string $name): string
    {
        $name = str_replace(['\\', '/', '?', '*', '[', ']'], '', $name);
        $name = trim($name);

        if ($name === '') {
            $name = 'Sheet';
        }

        return mb_substr($name, 0, 31);
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        $number = $index + 1;

        while ($number > 0) {
            $number--;
            $letter = chr(65 + ($number % 26)).$letter;
            $number = intdiv($number, 26);
        }

        return $letter;
    }

    private function xml(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? $value;

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
