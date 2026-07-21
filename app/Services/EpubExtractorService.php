<?php

namespace App\Services;

use App\Models\Book;
use App\Models\BookSection;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use ZipArchive;

class EpubExtractorService
{
    /**
     * Extract a single section from an EPUB file and convert it to a PDF using Headless Chrome.
     *
     * @param  Book  $book  The book model.
     * @param  BookSection  $section  The section model to extract.
     * @return string|null The absolute path to the generated temporary PDF file, or null on failure.
     */
    public function extractSectionToPdf(Book $book, BookSection $section): ?string
    {
        $originalFilePath = $book->getFirstMediaPath('file');
        if (! $originalFilePath || ! file_exists($originalFilePath)) {
            Log::error("Original EPUB file not found: {$originalFilePath}");

            return null;
        }

        $tempDir = storage_path('app/tmp');
        if (! file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $uniqId = uniqid('epub_pdf_');
        $extractDir = $tempDir.'/'.$uniqId;
        if (! mkdir($extractDir, 0755, true)) {
            Log::error("Failed to create temporary extraction directory: {$extractDir}");

            return null;
        }

        // 1. Extract the whole EPUB zip to the extract directory
        $zip = new ZipArchive;
        if ($zip->open($originalFilePath) !== true) {
            Log::error("Failed to open original EPUB: {$originalFilePath}");
            $this->cleanupDir($extractDir);

            return null;
        }
        $zip->extractTo($extractDir);
        $zip->close();

        // 2. Locate container.xml to find the OPF path
        $containerPath = $extractDir.'/META-INF/container.xml';
        if (! file_exists($containerPath)) {
            Log::error("META-INF/container.xml not found in extracted EPUB: {$containerPath}");
            $this->cleanupDir($extractDir);

            return null;
        }

        $containerContent = file_get_contents($containerPath);
        $containerDom = new DOMDocument;
        libxml_use_internal_errors(true);
        $containerDom->loadXML($containerContent);
        libxml_clear_errors();

        $xpath = new DOMXPath($containerDom);
        $xpath->registerNamespace('c', 'urn:oasis:names:tc:opendocument:xmlns:container');
        $rootfileNode = $xpath->query('//c:rootfile[@full-path]')->item(0);
        if (! $rootfileNode) {
            $rootfileNode = $containerDom->getElementsByTagName('rootfile')->item(0);
        }

        if (! $rootfileNode) {
            Log::error('No rootfile element found in container.xml');
            $this->cleanupDir($extractDir);

            return null;
        }

        $opfPath = $rootfileNode->getAttribute('full-path');
        $opfFullPath = $extractDir.'/'.$opfPath;
        if (! file_exists($opfFullPath)) {
            Log::error("OPF file not found at path: {$opfFullPath}");
            $this->cleanupDir($extractDir);

            return null;
        }

        // 3. Find the target href (HTML file name)
        $sectionIdentifier = $section->section_identifier;
        $targetHref = explode('#', $sectionIdentifier)[0]; // e.g. "Text/Chapter01.xhtml"

        $opfDir = dirname($opfPath);
        if ($opfDir === '.' || $opfDir === '/') {
            $opfDir = '';
        } else {
            $opfDir = rtrim($opfDir, '/').'/';
        }

        // Absolute path to the XHTML file
        $htmlFilePath = $extractDir.'/'.$opfDir.$targetHref;
        if (! file_exists($htmlFilePath)) {
            Log::error("XHTML file not found: {$htmlFilePath}");
            $this->cleanupDir($extractDir);

            return null;
        }

        // 4. Run Headless Chrome to convert the HTML file to PDF
        $pdfPath = $tempDir.'/'.$uniqId.'.pdf';

        $chromePath = env('CHROME_PATH');
        if (empty($chromePath)) {
            $chromePath = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
            if (! file_exists($chromePath)) {
                $chromePath = 'google-chrome'; // Linux/fallback
            }
        }

        try {
            $result = Process::run([
                $chromePath,
                '--headless',
                '--disable-gpu',
                '--print-to-pdf='.$pdfPath,
                $htmlFilePath,
            ]);

            if (! $result->successful() || ! file_exists($pdfPath)) {
                Log::error('Headless Chrome HTML to PDF failed: '.$result->errorOutput());
                $this->cleanupDir($extractDir);

                return null;
            }
        } catch (\Exception $e) {
            Log::error('Exception running Headless Chrome: '.$e->getMessage());
            $this->cleanupDir($extractDir);

            return null;
        }

        // 5. Cleanup the extracted directory
        $this->cleanupDir($extractDir);

        return $pdfPath;
    }

    /**
     * Helper to recursively delete a directory.
     *
     * @param  string  $dir  The directory path.
     */
    protected function cleanupDir(string $dir): void
    {
        if (! file_exists($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            if (is_dir($path)) {
                $this->cleanupDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
