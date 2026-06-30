<?php

namespace App\Support\Imports;

use SimpleXMLElement;
use XMLReader;
use ZipArchive;

class XlsxStreamReader
{
    public function __construct(
        private readonly string $filePath,
    ) {
    }

    /**
     * @return \Generator<int, array<int, string|null>>
     */
    public function rows(): \Generator
    {
        $sharedStrings = $this->loadSharedStrings();
        $sheetPath = $this->resolveSheetPath();
        $sheetUri = $this->worksheetUri($sheetPath);

        $reader = new XMLReader();
        $reader->open($sheetUri, null, LIBXML_NONET);

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                continue;
            }

            yield $this->parseRow(
                new SimpleXMLElement($reader->readOuterXML()),
                $sharedStrings,
            );
        }

        $reader->close();
    }

    /**
     * @return array<int, string>
     */
    private function loadSharedStrings(): array
    {
        $zip = new ZipArchive();

        if ($zip->open($this->filePath) !== true) {
            return [];
        }

        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();

        if ($sharedStringsXml === false || trim($sharedStringsXml) === '') {
            return [];
        }

        $strings = [];
        $reader = new XMLReader();
        $reader->XML($sharedStringsXml);

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'si') {
                continue;
            }

            $strings[] = $this->extractSharedStringText(
                new SimpleXMLElement($reader->readOuterXML()),
            );
        }

        $reader->close();

        return $strings;
    }

    private function resolveSheetPath(): string
    {
        $zip = new ZipArchive();

        if ($zip->open($this->filePath) !== true) {
            throw new \RuntimeException("Unable to open XLSX file: {$this->filePath}");
        }

        foreach (['xl/worksheets/sheet1.xml', 'xl/worksheets/Sheet1.xml'] as $candidate) {
            if ($zip->locateName($candidate) !== false) {
                $zip->close();

                return $candidate;
            }
        }

        $zip->close();

        throw new \RuntimeException('Worksheet sheet1.xml was not found in the XLSX file.');
    }

    private function worksheetUri(string $sheetPath): string
    {
        $normalizedPath = str_replace('\\', '/', $this->filePath);

        if (preg_match('/^[A-Za-z]:\\//', $normalizedPath) === 1) {
            return 'zip:///'.$normalizedPath.'#'.$sheetPath;
        }

        return 'zip://'.$normalizedPath.'#'.$sheetPath;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @return array<int, string|null>
     */
    private function parseRow(SimpleXMLElement $row, array $sharedStrings): array
    {
        $cells = [];

        foreach ($row->c as $cell) {
            $reference = (string) ($cell['r'] ?? '');

            if ($reference === '' || ! preg_match('/^([A-Z]+)/', strtoupper($reference), $matches)) {
                continue;
            }

            $columnIndex = $this->columnIndex($matches[1]);
            $cells[$columnIndex] = $this->cellValue($cell, $sharedStrings);
        }

        if ($cells === []) {
            return [];
        }

        $values = [];

        for ($index = 0; $index <= max(array_keys($cells)); $index++) {
            $values[] = $cells[$index] ?? null;
        }

        return $values;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     */
    private function cellValue(SimpleXMLElement $cell, array $sharedStrings): ?string
    {
        $type = (string) ($cell['t'] ?? '');

        if ($type === 'inlineStr') {
            return $this->nullableString((string) ($cell->is->t ?? ''));
        }

        $rawValue = isset($cell->v) ? (string) $cell->v : null;

        if ($rawValue === null || $rawValue === '') {
            return null;
        }

        if ($type === 's') {
            return $this->nullableString($sharedStrings[(int) $rawValue] ?? null);
        }

        return $this->nullableString($rawValue);
    }

    private function extractSharedStringText(SimpleXMLElement $node): string
    {
        if (isset($node->t)) {
            return (string) $node->t;
        }

        $parts = [];

        foreach ($node->r as $run) {
            if (isset($run->t)) {
                $parts[] = (string) $run->t;
            }
        }

        return implode('', $parts);
    }

    private function columnIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $index = 0;

        for ($offset = 0; $offset < strlen($letters); $offset++) {
            $index = ($index * 26) + (ord($letters[$offset]) - ord('A') + 1);
        }

        return $index - 1;
    }

    private function nullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
