<?php

namespace Tests\Unit;

use App\Services\PdfImageExtractorService;
use Tests\TestCase;

class PdfImageExtractorServiceTest extends TestCase
{
    public function test_extracts_pages_to_images_using_imagick(): void
    {
        $service = new PdfImageExtractorService;
        $pdfPath = storage_path('app/public/27/D2L.pdf');

        if (! file_exists($pdfPath)) {
            $this->markTestSkipped('Sample PDF file not found at '.$pdfPath);
        }

        $imagePaths = $service->extractPagesToImages($pdfPath, [1, 2], 2, 3500);

        $this->assertNotEmpty($imagePaths);
        foreach ($imagePaths as $path) {
            $this->assertFileExists($path);
            $this->assertGreaterThan(0, filesize($path));
            unlink($path);
        }
    }
}
