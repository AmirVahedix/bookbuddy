<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookSection;
use App\Services\EpubExtractorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class EpubExtractorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_extract_section_to_pdf_runs_chrome_and_creates_pdf(): void
    {
        Storage::fake('public');

        // Fake the process to output a mock PDF
        Process::fake([
            '*' => function (PendingProcess $pendingProcess) {
                // Find the --print-to-pdf= argument and write a dummy file there
                $cmd = $pendingProcess->command;
                $command = is_array($cmd) ? implode(' ', $cmd) : $cmd;
                if (preg_match('/--print-to-pdf=([^\s]+)/', $command, $matches)) {
                    file_put_contents($matches[1], 'mock pdf content');
                }

                return Process::result('Success', 0);
            },
        ]);

        // Create a mock EPUB
        $htmlFiles = [
            'text/chapter1.xhtml' => '<html><head><title>Chapter 1</title></head><body><h1 id="sec-1">Heading 1</h1><p>Chapter 1 Content</p></body></html>',
        ];

        $uploadedFile = $this->createMockEpubFile('book.epub', $htmlFiles);

        $book = Book::create([
            'title' => 'Test EPUB',
            'author' => 'Test Author',
            'file_type' => 'epub',
        ]);
        $book->addMedia($uploadedFile)->toMediaCollection('file');

        $secChapter1 = BookSection::create([
            'book_id' => $book->id,
            'title' => 'Chapter 1',
            'section_identifier' => 'text/chapter1.xhtml#sec-1',
            'level' => 1,
            'order' => 1,
        ]);

        $service = new EpubExtractorService;
        $extractedPath = $service->extractSectionToPdf($book, $secChapter1);

        $this->assertNotNull($extractedPath);
        $this->assertFileExists($extractedPath);
        $this->assertEquals('mock pdf content', file_get_contents($extractedPath));

        // Verify chrome command was executed
        Process::assertRan(function (PendingProcess $process) {
            $cmd = $process->command;
            $command = is_array($cmd) ? implode(' ', $cmd) : $cmd;

            return str_contains($command, '--headless') && str_contains($command, '--print-to-pdf=') && str_contains($command, 'chapter1.xhtml');
        });

        if (file_exists($extractedPath)) {
            unlink($extractedPath);
        }
    }

    protected function createMockEpubFile(string $filename, array $htmlFilesContent): UploadedFile
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'epub');
        $zip = new ZipArchive;
        $zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('mimetype', 'application/epub+zip');

        $containerXml = '<?xml version="1.0"?>
<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
  <rootfiles>
    <rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>';
        $zip->addFromString('META-INF/container.xml', $containerXml);

        $manifestItems = '';
        $spineItems = '';
        foreach (array_keys($htmlFilesContent) as $index => $href) {
            $id = 'item_'.$index;
            $manifestItems .= "<item id=\"{$id}\" href=\"{$href}\" media-type=\"application/xhtml+xml\"/>\n";
            $spineItems .= "<itemref idref=\"{$id}\"/>\n";
        }

        $opfContent = '<?xml version="1.0" encoding="utf-8"?>
<package xmlns="http://www.idpf.org/2007/opf" unique-identifier="bookid" version="2.0">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:title>Mock Book</dc:title>
  </metadata>
  <manifest>
    '.$manifestItems.'
  </manifest>
  <spine>
    '.$spineItems.'
  </spine>
</package>';
        $zip->addFromString('OEBPS/content.opf', $opfContent);

        foreach ($htmlFilesContent as $href => $content) {
            $zip->addFromString('OEBPS/'.$href, $content);
        }

        $zip->close();

        return new UploadedFile(
            $tempFile,
            $filename,
            'application/epub+zip',
            null,
            true
        );
    }
}
