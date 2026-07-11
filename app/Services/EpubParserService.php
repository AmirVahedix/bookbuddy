<?php

namespace App\Services;

use App\Models\Book;
use App\Models\BookSection;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ZipArchive;

class EpubParserService
{
    /**
     * Parse the EPUB and extract its sections.
     *
     * @param  string  $filePath  Absolute path to the EPUB file.
     */
    public function parseAndStoreSections(Book $book, string $filePath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            Log::error("Failed to open EPUB archive: {$filePath}");

            return;
        }

        // 1. Locate rootfile from META-INF/container.xml
        $containerContent = $zip->getFromName('META-INF/container.xml');
        if (! $containerContent) {
            Log::error("META-INF/container.xml not found in EPUB: {$filePath}");
            $zip->close();

            return;
        }

        $containerDom = new DOMDocument;
        libxml_use_internal_errors(true);
        $containerDom->loadXML($containerContent);
        libxml_clear_errors();

        $xpath = new DOMXPath($containerDom);
        $xpath->registerNamespace('c', 'urn:oasis:names:tc:opendocument:xmlns:container');
        $rootfileNode = $xpath->query('//c:rootfile[@full-path]')->item(0);

        if (! $rootfileNode) {
            // Try without namespace fallback
            $rootfileNode = $containerDom->getElementsByTagName('rootfile')->item(0);
        }

        if (! $rootfileNode) {
            Log::error('No rootfile element found in META-INF/container.xml');
            $zip->close();

            return;
        }

        $opfPath = $rootfileNode->getAttribute('full-path');
        if (! $opfPath) {
            Log::error('rootfile full-path attribute missing in container.xml');
            $zip->close();

            return;
        }

        // 2. Read and parse OPF file
        $opfContent = $zip->getFromName($opfPath);
        if (! $opfContent) {
            Log::error("OPF file not found: {$opfPath}");
            $zip->close();

            return;
        }

        $opfDom = new DOMDocument;
        libxml_use_internal_errors(true);
        $opfDom->loadXML($opfContent);
        libxml_clear_errors();

        // Extract manifest mapping
        $manifestMap = [];
        $manifestItems = $opfDom->getElementsByTagName('item');
        foreach ($manifestItems as $item) {
            $itemId = $item->getAttribute('id');
            $itemHref = $item->getAttribute('href');
            if ($itemId && $itemHref) {
                $manifestMap[$itemId] = $itemHref;
            }
        }

        // Extract spine items
        $spineItems = $opfDom->getElementsByTagName('itemref');
        $htmlFiles = [];
        foreach ($spineItems as $itemref) {
            $idref = $itemref->getAttribute('idref');
            if (isset($manifestMap[$idref])) {
                $htmlFiles[] = $manifestMap[$idref];
            }
        }

        // OPF Base Directory (e.g. if OPF is OEBPS/content.opf, base is OEBPS/)
        $opfDir = dirname($opfPath);
        if ($opfDir === '.' || $opfDir === '/') {
            $opfDir = '';
        } else {
            $opfDir = rtrim($opfDir, '/').'/';
        }

        $sectionOrder = 1;

        // 3. Process each HTML file in the spine
        foreach ($htmlFiles as $relativeHref) {
            // Resolve path inside the zip file
            $cleanHref = rawurldecode($relativeHref);
            $zipEntryPath = $opfDir.$cleanHref;

            $fileContent = $zip->getFromName($zipEntryPath);
            if (! $fileContent) {
                Log::warning("Spine file not found in zip: {$zipEntryPath}");

                continue;
            }

            // Parse HTML
            $docDom = new DOMDocument;
            libxml_use_internal_errors(true);
            $docDom->loadHTML($fileContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();

            // Extract document title from <title> tag
            $docTitle = '';
            $titleNode = $docDom->getElementsByTagName('title')->item(0);
            if ($titleNode) {
                $docTitle = trim($titleNode->textContent);
            }

            // Find all headings
            $xpathDoc = new DOMXPath($docDom);
            $headings = $xpathDoc->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6');

            if ($headings->length === 0) {
                // No headings! Entire file is a single section to prevent orphan content.
                $title = Str::limit($docTitle ?: basename($cleanHref), 252);
                BookSection::create([
                    'book_id' => $book->id,
                    'title' => $title,
                    'section_identifier' => $cleanHref,
                    'level' => null,
                    'order' => $sectionOrder++,
                ]);

                continue;
            }

            // Headings are present! We partition the file content.
            // Check if there is content before the first heading.
            $firstHeadingNode = $headings->item(0);
            $hasContentBeforeFirstHeading = false;

            $bodyNode = $docDom->getElementsByTagName('body')->item(0) ?: $docDom->documentElement;

            if ($bodyNode) {
                $hasContentBeforeFirstHeading = $this->hasSignificantContentBefore($bodyNode, $firstHeadingNode);
            }

            if ($hasContentBeforeFirstHeading) {
                $title = Str::limit($docTitle ? $docTitle.' (Introduction)' : 'Introduction', 252);
                BookSection::create([
                    'book_id' => $book->id,
                    'title' => $title,
                    'section_identifier' => $cleanHref,
                    'level' => null,
                    'order' => $sectionOrder++,
                ]);
            }

            // Create a section for each heading
            foreach ($headings as $index => $headingNode) {
                $title = trim($headingNode->textContent);
                if (empty($title)) {
                    $title = 'Section '.($index + 1);
                }
                $title = Str::limit($title, 252);

                $tagName = strtolower($headingNode->nodeName);
                $level = (int) substr($tagName, 1);

                // Get ID attribute if present, otherwise generate one using the tag and index
                $id = $headingNode->getAttribute('id');
                if (! $id) {
                    $id = $tagName.'-'.$index;
                }

                $identifier = $cleanHref.'#'.$id;

                BookSection::create([
                    'book_id' => $book->id,
                    'title' => $title,
                    'section_identifier' => $identifier,
                    'level' => $level,
                    'order' => $sectionOrder++,
                ]);
            }
        }

        $zip->close();
    }

    /**
     * Helper to detect if there is significant text/content before the target node in a DOM tree.
     */
    private function hasSignificantContentBefore(\DOMNode $container, \DOMNode $targetNode): bool
    {
        $foundTarget = false;

        return $this->scanNodeForContentBefore($container, $targetNode, $foundTarget);
    }

    private function scanNodeForContentBefore(\DOMNode $currentNode, \DOMNode $targetNode, bool &$foundTarget): bool
    {
        if ($currentNode === $targetNode) {
            $foundTarget = true;

            return false;
        }

        if ($currentNode->nodeType === XML_TEXT_NODE) {
            $text = trim($currentNode->textContent);
            if (! empty($text)) {
                return true;
            }
        }

        if ($currentNode->nodeType === XML_ELEMENT_NODE) {
            $tagName = strtolower($currentNode->nodeName);
            if (in_array($tagName, ['img', 'table', 'iframe', 'object', 'svg'])) {
                return true;
            }
        }

        foreach ($currentNode->childNodes as $child) {
            if ($this->scanNodeForContentBefore($child, $targetNode, $foundTarget)) {
                return true;
            }
            if ($foundTarget) {
                return false;
            }
        }

        return false;
    }
}
