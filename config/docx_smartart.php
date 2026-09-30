<?php
function extractSmartArtText(string $docxPath): array
{
    $zip = new ZipArchive();
    if ($zip->open($docxPath) !== true) {
        throw new RuntimeException('เปิดไฟล์ .docx ไม่ได้');
    }

    $texts = [];

    try {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!is_string($name) || !preg_match('#^word/diagrams/data\d+\.xml$#', $name)) {
                continue;
            }

            $xmlContent = $zip->getFromName($name);
            if ($xmlContent === false) {
                continue;
            }

            $previousLibxmlSetting = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOBLANKS);
            libxml_clear_errors();
            libxml_use_internal_errors($previousLibxmlSetting);
            if ($xml === false) {
                continue;
            }

            $xml->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
            $nodes = $xml->xpath('//a:t') ?: [];
            $combined = '';
            foreach ($nodes as $node) {
                $combined .= (string) $node;
            }

            $combined = trim($combined);
            if ($combined !== '') {
                $texts[$name] = $combined;
            }
        }
    } finally {
        $zip->close();
    }

    return $texts;
}

function getThaiNamesFromDocx(string $docxPath): array
{
    $smartArtTexts = extractSmartArtText($docxPath);
    return array_values(array_filter($smartArtTexts, static fn(string $text): bool => $text !== ''));
}

function extractSmartArtNamesInDocumentOrder(string $docxPath): array
{
    $zip = new ZipArchive();
    if ($zip->open($docxPath) !== true) {
        throw new RuntimeException('เปิดไฟล์ .docx ไม่ได้');
    }

    try {
        $documentXml = $zip->getFromName('word/document.xml');
        if ($documentXml === false) {
            throw new RuntimeException('ไม่พบเนื้อหาเอกสารหลัก (document.xml)');
        }

        $document = simplexml_load_string($documentXml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOBLANKS);
        if ($document === false) {
            throw new RuntimeException('อ่านเนื้อหาเอกสารหลักไม่สำเร็จ');
        }

        $relationshipXml = $zip->getFromName('word/_rels/document.xml.rels');
        $relationships = [];
        if ($relationshipXml !== false) {
            $relationshipDocument = simplexml_load_string($relationshipXml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOBLANKS);
            if ($relationshipDocument !== false) {
                $relationshipDocument->registerXPathNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');
                foreach ($relationshipDocument->xpath('//rel:Relationship') ?: [] as $relationship) {
                    $attributes = $relationship->attributes();
                    $relationships[(string) ($attributes['Id'] ?? '')] = (string) ($attributes['Target'] ?? '');
                }
            }
        }

        $document->registerXPathNamespace('dgm', 'http://schemas.openxmlformats.org/drawingml/2006/diagram');
        $document->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $orderedPaths = [];
        foreach ($document->xpath('//dgm:relIds') ?: [] as $relationshipIds) {
            $attributes = $relationshipIds->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $relationshipId = (string) ($attributes['dm'] ?? '');
            $target = $relationships[$relationshipId] ?? '';
            if ($target === '') {
                continue;
            }

            $target = str_replace('\\', '/', $target);
            $dataPath = isset($target[0]) && $target[0] === '/'
                ? ltrim($target, '/')
                : (strncmp($target, 'word/', 5) === 0 ? $target : 'word/' . $target);
            if (preg_match('#^word/diagrams/data\d+\.xml$#', $dataPath)) {
                $orderedPaths[] = $dataPath;
            }
        }
    } finally {
        $zip->close();
    }

    $textsByPath = extractSmartArtText($docxPath);
    return array_map(static fn(string $path): string => $textsByPath[$path] ?? '', $orderedPaths);
}

function getThaiNameFromDocx(string $docxPath): ?string
{
    $thaiNames = getThaiNamesFromDocx($docxPath);
    return $thaiNames[0] ?? null;
}