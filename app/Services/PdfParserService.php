<?php

namespace App\Services;

use App\Models\Book;
use App\Models\BookSection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class PdfParserService
{
    /**
     * Parse the PDF and extract its sections.
     *
     * @param  string  $filePath  Absolute path to the PDF file.
     */
    public function parseAndStoreSections(Book $book, string $filePath): void
    {
        try {
            $sections = [];
            $driver = config('services.pdf.parser', 'php');

            if ($driver === 'php') {
                $sections = $this->parseUsingPhp($filePath);
            } else {
                $sections = $this->parseUsingPython($filePath);
                if (empty($sections)) {
                    // Fallback to PHP if Python is selected but returns nothing
                    $sections = $this->parseUsingPhp($filePath);
                }
            }

            if (empty($sections)) {
                Log::info("No outline/table of contents found for PDF book: {$book->id}");

                return;
            }

            $order = 1;
            foreach ($sections as $section) {
                $sectionIdentifier = 'page-'.$section['page'];

                BookSection::updateOrCreate(
                    [
                        'book_id' => $book->id,
                        'title' => mb_substr($section['title'], 0, 250),
                        'section_identifier' => $sectionIdentifier,
                        'level' => $section['level'],
                        'start_page' => $section['page'],
                    ],
                    [
                        'end_page' => $section['end_page'],
                        'order' => $order++,
                    ]
                );
            }
        } catch (\Exception $e) {
            Log::error('Error parsing and storing PDF sections: '.$e->getMessage());
        }
    }

    /**
     * Parse outline using Python script.
     *
     * @return array<int, array{title: string, page: int, level: int, end_page: int}>
     */
    protected function parseUsingPython(string $filePath): array
    {
        try {
            $pythonPath = env('PYTHON_PATH');

            if (empty($pythonPath)) {
                $pythonPath = '/Users/macbookair/miniconda3/bin/python3';
                if (! $this->isSafePath($pythonPath) || ! @file_exists($pythonPath)) {
                    $pythonPath = 'python3';
                }
            }

            $scriptPath = base_path('app/Services/pdf_outline_parser.py');

            $result = Process::run([$pythonPath, $scriptPath, $filePath]);

            if (! $result->successful()) {
                Log::error('PDF outline parser script failed: '.$result->errorOutput());

                return [];
            }

            $data = json_decode($result->output(), true);

            if (isset($data['error'])) {
                Log::error('PDF outline parser returned error: '.$data['error']);

                return [];
            }

            return $data['sections'] ?? [];
        } catch (\Exception $e) {
            Log::error('Error running Python PDF outline parser: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Check if a file path is safe to access under open_basedir restriction.
     */
    protected function isSafePath(string $path): bool
    {
        $openBasedir = ini_get('open_basedir');
        if (empty($openBasedir)) {
            return true;
        }

        $allowedPaths = explode(PATH_SEPARATOR, $openBasedir);
        $realPath = realpath($path) ?: $path;

        foreach ($allowedPaths as $allowed) {
            if (empty($allowed)) {
                continue;
            }
            $allowedReal = realpath($allowed) ?: $allowed;
            if (str_starts_with($realPath, $allowedReal)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse outline using native PHP.
     *
     * @return array<int, array{title: string, page: int, level: int, end_page: int}>
     */
    /**
     * Parse outline using native PHP.
     *
     * @return array<int, array{title: string, page: int, level: int, end_page: int}>
     */
    public function parseUsingPhp(string $filePath): array
    {
        if (! file_exists($filePath)) {
            return [];
        }

        // Increase limits for processing potentially large PDF files on shared hosting
        @ini_set('memory_limit', '256M');
        @ini_set('pcre.backtrack_limit', '10000000');
        @ini_set('pcre.recursion_limit', '10000000');

        $pdfData = file_get_contents($filePath);
        if ($pdfData === false) {
            return [];
        }

        // Parse indirect objects
        $objects = [];
        $offset = 0;
        $len = strlen($pdfData);
        while (true) {
            if (! preg_match('/(\d+)\s+(\d+)\s+obj/s', $pdfData, $matches, PREG_OFFSET_CAPTURE, $offset)) {
                break;
            }
            $objId = (int) $matches[1][0];
            $startPos = $matches[0][1];
            $endPos = strpos($pdfData, 'endobj', $startPos);
            if ($endPos === false) {
                break;
            }
            $objContent = substr($pdfData, $startPos, $endPos - $startPos);

            // Strip stream if present to save memory and avoid scanning binary data
            $streamStart = strpos($objContent, 'stream');
            if ($streamStart !== false) {
                $objContent = substr($objContent, 0, $streamStart);
            }

            // Memory Optimization: Only store structural objects needed for outline resolution
            if (str_contains($objContent, '/Catalog') ||
                str_contains($objContent, '/Pages') ||
                str_contains($objContent, '/Page') ||
                str_contains($objContent, '/Outlines') ||
                str_contains($objContent, '/Title') ||
                str_contains($objContent, '/Dest') ||
                str_contains($objContent, '/A') ||
                str_contains($objContent, '/XYZ') ||
                str_contains($objContent, '/GoTo')
            ) {
                $objects[$objId] = $objContent;
            }

            $offset = $endPos + 6;
        }

        // Find Catalog / Root ID
        $rootId = null;
        foreach ($objects as $id => $content) {
            if (preg_match('/\/Type\s*\/Catalog\b/', $content)) {
                $rootId = $id;
                break;
            }
        }
        if (! $rootId) {
            // Fallback: Search from the end of the file for the trailer which contains the Root reference
            $trailerOffset = max(0, strlen($pdfData) - 100000);
            $trailerData = substr($pdfData, $trailerOffset);
            if (preg_match('/\/Root\s+(\d+)\s+\d+\s+R/', $trailerData, $m)) {
                $rootId = (int) $m[1];
            }
        }

        // Resolve Page Tree to map object IDs to 1-based page numbers
        $pagesId = null;
        if ($rootId && isset($objects[$rootId])) {
            if (preg_match('/\/Pages\s+(\d+)\s+\d+\s+R/', $objects[$rootId], $m)) {
                $pagesId = (int) $m[1];
            }
        }

        $pages = [];
        $traversePages = function (int $id) use (&$traversePages, &$pages, $objects) {
            if (! isset($objects[$id])) {
                return;
            }
            $content = $objects[$id];
            if (preg_match('/\/Type\s*\/Page\b/', $content) && ! preg_match('/\/Type\s*\/Pages\b/', $content)) {
                $pages[] = $id;

                return;
            }

            if (preg_match('/\/Kids\s*\[([^\]]+)\]/', $content, $m)) {
                $kidsStr = $m[1];
                if (preg_match_all('/(\d+)\s+\d+\s+R/', $kidsStr, $matches)) {
                    foreach ($matches[1] as $kidId) {
                        $traversePages((int) $kidId);
                    }
                }
            }
        };

        if ($pagesId) {
            $traversePages($pagesId);
        }

        // Fallback: order of /Type /Page if traversal didn't yield anything
        if (empty($pages)) {
            foreach ($objects as $id => $content) {
                if (preg_match('/\/Type\s*\/Page\b/', $content) && ! preg_match('/\/Type\s*\/Pages\b/', $content)) {
                    $pages[] = $id;
                }
            }
        }

        // Resolve Outlines Tree
        $outlinesId = null;
        if ($rootId && isset($objects[$rootId])) {
            if (preg_match('/\/Outlines\s+(\d+)\s+\d+\s+R/', $objects[$rootId], $m)) {
                $outlinesId = (int) $m[1];
            }
        }
        if (! $outlinesId) {
            foreach ($objects as $id => $content) {
                if (preg_match('/\/Type\s*\/Outlines\b/', $content)) {
                    $outlinesId = $id;
                    break;
                }
            }
        }

        $firstId = null;
        if ($outlinesId && isset($objects[$outlinesId])) {
            if (preg_match('/\/First\s+(\d+)\s+\d+\s+R/', $objects[$outlinesId], $m)) {
                $firstId = (int) $m[1];
            }
        }

        $sections = [];
        $traverseOutline = function (int $id, int $level) use (&$traverseOutline, &$sections, $objects, $pages) {
            $visited = [];
            $currId = $id;
            while ($currId && ! in_array($currId, $visited)) {
                $visited[] = $currId;
                if (! isset($objects[$currId])) {
                    break;
                }
                $content = $objects[$currId];

                // Extract Title
                $title = null;
                if (preg_match('/\/Title\s*\((.*?)\)/s', $content, $m)) {
                    $title = $m[1];
                    $title = str_replace(['\\(', '\\)', '\\\\', '\\n', '\\r', '\\t'], ['(', ')', '\\', "\n", "\r", "\t"], $title);
                    $title = $this->decodePdfString($title);
                } elseif (preg_match('/\/Title\s*<([^>]+)>/s', $content, $m)) {
                    $title = $this->decodeHexPdfString($m[1]);
                }

                // Extract Target Page
                $pageNo = null;

                // Case 1: Direct /Dest with page object reference
                if (preg_match('/\/Dest\s*\[\s*(\d+)\s+\d+\s+R/', $content, $m)) {
                    $destPageObjId = (int) $m[1];
                    $pageIndex = array_search($destPageObjId, $pages);
                    if ($pageIndex !== false) {
                        $pageNo = $pageIndex + 1;
                    }
                }
                // Case 2: /Dest as indirect reference
                elseif (preg_match('/\/Dest\s+(\d+)\s+\d+\s+R/', $content, $m)) {
                    $destId = (int) $m[1];
                    if (isset($objects[$destId])) {
                        $destContent = $objects[$destId];
                        if (preg_match('/\[\s*(\d+)\s+\d+\s+R/', $destContent, $dm)) {
                            $destPageObjId = (int) $dm[1];
                            $pageIndex = array_search($destPageObjId, $pages);
                            if ($pageIndex !== false) {
                                $pageNo = $pageIndex + 1;
                            }
                        }
                    }
                }
                // Case 3: Action GoTo
                elseif (preg_match('/\/A\s*<<[^>]*\/D\s*\[\s*(\d+)\s+\d+\s+R/', $content, $m)) {
                    $destPageObjId = (int) $m[1];
                    $pageIndex = array_search($destPageObjId, $pages);
                    if ($pageIndex !== false) {
                        $pageNo = $pageIndex + 1;
                    }
                }
                // Case 4: Action GoTo indirect action
                elseif (preg_match('/\/A\s*<<[^>]*\/D\s*(\d+)\s+\d+\s+R/', $content, $m)) {
                    $destId = (int) $m[1];
                    if (isset($objects[$destId])) {
                        $destContent = $objects[$destId];
                        if (preg_match('/\[\s*(\d+)\s+\d+\s+R/', $destContent, $dm)) {
                            $destPageObjId = (int) $dm[1];
                            $pageIndex = array_search($destPageObjId, $pages);
                            if ($pageIndex !== false) {
                                $pageNo = $pageIndex + 1;
                            }
                        }
                    }
                }

                if ($title !== null && $pageNo !== null) {
                    $title = trim(preg_replace('/\s+/', ' ', $title));
                    $sections[] = [
                        'title' => $title,
                        'page' => $pageNo,
                        'level' => $level,
                    ];
                }

                // Traverse children
                if (preg_match('/\/First\s+(\d+)\s+\d+\s+R/', $content, $cm)) {
                    $firstChildId = (int) $cm[1];
                    $traverseOutline($firstChildId, $level + 1);
                }

                // Go to next sibling
                $nextId = null;
                if (preg_match('/\/Next\s+(\d+)\s+\d+\s+R/', $content, $nm)) {
                    $nextId = (int) $nm[1];
                }
                $currId = $nextId;
            }
        };

        if ($firstId) {
            $traverseOutline($firstId, 1);
        }

        // Calculate end pages
        $totalPages = count($pages);
        $sectionsCount = count($sections);
        for ($i = 0; $i < $sectionsCount; $i++) {
            $currPage = $sections[$i]['page'];
            $currLevel = $sections[$i]['level'];
            $endPage = $totalPages;

            for ($j = $i + 1; $j < $sectionsCount; $j++) {
                $nextPage = $sections[$j]['page'];
                $nextLevel = $sections[$j]['level'];

                if ($nextLevel <= $currLevel) {
                    if ($nextPage > $currPage) {
                        $endPage = $nextPage - 1;
                        break;
                    } elseif ($nextPage == $currPage) {
                        $endPage = $currPage;
                        break;
                    }
                }
            }

            $sections[$i]['end_page'] = $endPage;
        }

        return $sections;
    }

    /**
     * Decode a PDF hex string (e.g. from UTF-16BE/FEFF encoding).
     */
    protected function decodeHexPdfString(string $hex): string
    {
        $hex = preg_replace('/[^0-9A-Fa-f]/', '', $hex);
        $bin = pack('H*', $hex);

        if (str_starts_with($bin, "\xFE\xFF")) {
            $utf16 = substr($bin, 2);
            if (function_exists('iconv')) {
                $decoded = iconv('UTF-16BE', 'UTF-8//IGNORE', $utf16);
                if ($decoded !== false) {
                    return $decoded;
                }
            }
            if (function_exists('mb_convert_encoding')) {
                $decoded = mb_convert_encoding($utf16, 'UTF-8', 'UTF-16BE');
                if ($decoded !== false) {
                    return $decoded;
                }
            }
        }

        return $bin;
    }

    /**
     * Decode a standard PDF string (e.g. with octal escapes).
     */
    protected function decodePdfString(string $str): string
    {
        if (str_starts_with($str, '\\376\\377')) {
            $bytes = '';
            $i = 0;
            $len = strlen($str);
            while ($i < $len) {
                if ($str[$i] === '\\' && $i + 1 < $len && is_numeric($str[$i + 1])) {
                    $octal = substr($str, $i + 1, 3);
                    if (preg_match('/^[0-7]{1,3}/', $octal, $m)) {
                        $bytes .= chr(octdec($m[0]));
                        $i += 1 + strlen($m[0]);

                        continue;
                    }
                }
                $bytes .= $str[$i];
                $i++;
            }
            if (str_starts_with($bytes, "\xFE\xFF")) {
                $utf16 = substr($bytes, 2);
                if (function_exists('iconv')) {
                    $decoded = iconv('UTF-16BE', 'UTF-8//IGNORE', $utf16);
                    if ($decoded !== false) {
                        return $decoded;
                    }
                }
                if (function_exists('mb_convert_encoding')) {
                    $decoded = mb_convert_encoding($utf16, 'UTF-8', 'UTF-16BE');
                    if ($decoded !== false) {
                        return $decoded;
                    }
                }
            }
        }

        return $str;
    }
}
