<?php

class SimpleXlsx
{

    /**
     * @throws Exception
     */
    public function start_writer(string $sheet_temp_path): void
    {

        $dir = dirname($sheet_temp_path);
        $this->ensure_directory_is_writable($dir);

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<sheetData>';
        file_put_contents($sheet_temp_path, $xml);

    }

    /**
     * @throws Exception
     */
    public function append_rows(string $sheet_temp_path, array $rows, int $start_row_number): int
    {

        $row_number = $start_row_number;
        $buffer = '';

        foreach ($rows as $row) {

            $buffer .= '<row r="' . $row_number . '">';
            $column_index = 1;

            foreach ($row as $value) {

                $cell_reference = $this->column_letter($column_index) . $row_number;
                $buffer .= '<c r="' . $cell_reference . '" t="inlineStr"><is><t>'
                    . $this->escape_xml(string_value($value))
                    . '</t></is></c>';
                $column_index++;
            }

            $buffer .= '</row>';
            $row_number++;
        }

        file_put_contents($sheet_temp_path, $buffer, FILE_APPEND);

        return $row_number;

    }

    /**
     * @throws Exception
     */
    public function finalize_writer(string $sheet_temp_path, string $output_path): void
    {

        file_put_contents($sheet_temp_path, '</sheetData></worksheet>', FILE_APPEND);

        $dir = dirname($output_path);
        $this->ensure_directory_is_writable($dir);

        $zip = new ZipArchive();
        if ($zip->open($output_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {

            throw new RuntimeException('Unable to create XLSX archive.');
        }

        $zip->addFromString('[Content_Types].xml', $this->content_types_xml());
        $zip->addFromString('_rels/.rels', $this->root_rels_xml());
        $zip->addFromString('xl/workbook.xml', $this->workbook_xml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbook_rels_xml());
        $zip->addFromString('xl/styles.xml', $this->styles_xml());
        $zip->addFile($sheet_temp_path, 'xl/worksheets/sheet1.xml');

        if ($zip->close() !== true || !is_file($output_path)) {

            throw new RuntimeException('Unable to finalize XLSX archive.');
        }

    }

    /**
     * @throws Exception
     */
    public function read_first_column_values(string $file_path): array
    {

        $zip = new ZipArchive();
        if ($zip->open($file_path) !== true) {

            throw new RuntimeException('Unable to open XLSX file.');
        }

        $shared_strings = $this->read_shared_strings($zip);
        $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheet_xml === false) {

            throw new RuntimeException('The XLSX file does not contain a first worksheet.');
        }

        $reader = new XMLReader();
        $reader->XML($sheet_xml);
        $values = [];

        while ($reader->read()) {

            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'c') {

                continue;
            }

            $reference = string_value($reader->getAttribute('r'));
            if (!preg_match('/^A\d+$/', $reference)) {

                continue;
            }

            $type = string_value($reader->getAttribute('t'));
            $node = $reader->expand();
            if (!$node instanceof DOMElement) {

                continue;
            }

            $value = '';
            if ($type === 's') {

                $index_node = $node->getElementsByTagName('v')->item(0);
                $shared_index = $index_node ? (int) $index_node->textContent : -1;
                $value = $shared_strings[$shared_index] ?? '';
            } elseif ($type === 'inlineStr') {

                $text_node = $node->getElementsByTagName('t')->item(0);
                $value = $text_node ? string_value($text_node->textContent) : '';
            } else {

                $value_node = $node->getElementsByTagName('v')->item(0);
                $value = $value_node ? string_value($value_node->textContent) : '';
            }

            $value = trim($value);
            if ($value === '' || strcasecmp($value, 'client_name') === 0) {

                continue;
            }

            $values[] = $value;
        }

        $reader->close();

        return array_values(array_unique($values));

    }

    private function read_shared_strings(ZipArchive $zip): array
    {

        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {

            return [];
        }

        $reader = new XMLReader();
        $reader->XML($xml);
        $values = [];

        while ($reader->read()) {

            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'si') {

                continue;
            }

            $node = $reader->expand();
            if (!$node instanceof DOMElement) {

                continue;
            }

            $texts = $node->getElementsByTagName('t');
            $buffer = '';
            foreach ($texts as $text_node) {

                $buffer .= $text_node->textContent;
            }
            $values[] = $buffer;
        }

        $reader->close();

        return $values;

    }

    private function column_letter(int $index): string
    {

        $letter = '';

        while ($index > 0) {

            $index--;
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intdiv($index, 26);
        }

        return $letter;

    }

    private function escape_xml(string $value): string
    {

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

    }

    private function ensure_directory_is_writable(string $path): void
    {

        if (!is_dir($path)) {

            mkdir($path, 0777, true);
        }

        @chmod($path, 0777);

    }

    private function content_types_xml(): string
    {

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';

    }

    private function root_rels_xml(): string
    {

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

    }

    private function workbook_xml(): string
    {

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Export" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';

    }

    private function workbook_rels_xml(): string
    {

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';

    }

    private function styles_xml(): string
    {

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';

    }

}
