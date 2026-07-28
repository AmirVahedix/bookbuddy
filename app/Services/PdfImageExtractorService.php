<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Imagick;
use InvalidArgumentException;

class PdfImageExtractorService
{
    /**
     * Extract specified PDF pages and combine/stack them vertically into PNG image batches.
     *
     * @param  string  $pdfPath  Absolute path to the source PDF file.
     * @param  array<int, int>  $targetPages  List of 1-based page numbers to extract. If empty, all pages are processed.
     * @param  int  $maxPagesPerImage  Maximum number of PDF pages to stack vertically per image.
     * @param  int  $maxHeightPerImage  Maximum total pixel height per stacked image.
     * @return array<int, string> List of absolute paths to generated temporary PNG image files.
     *
     * @throws Exception
     */
    public function extractPagesToImages(
        string $pdfPath,
        array $targetPages = [],
        int $maxPagesPerImage = 3,
        int $maxHeightPerImage = 3500
    ): array {
        if (! file_exists($pdfPath)) {
            throw new InvalidArgumentException("PDF file not found: {$pdfPath}");
        }

        $tempDir = storage_path('app/tmp');
        if (! file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Determine available pages if targetPages is empty
        if (empty($targetPages)) {
            $totalCount = $this->getPdfTotalPages($pdfPath);
            if ($totalCount < 1) {
                throw new Exception("Could not determine page count for PDF: {$pdfPath}");
            }
            $targetPages = range(1, $totalCount);
        }

        sort($targetPages);
        $targetPages = array_values(array_unique(array_filter($targetPages, fn ($p) => $p >= 1)));

        $singlePageImages = [];
        $errors = [];

        // Step 1: Render each page to an individual PNG image file
        foreach ($targetPages as $pageNumber) {
            $pagePng = null;

            // Try Imagick first
            if (class_exists(Imagick::class)) {
                try {
                    $pagePng = $this->renderPageWithImagick($pdfPath, $pageNumber, $tempDir);
                } catch (Exception $e) {
                    $errors[] = "Imagick page {$pageNumber}: ".$e->getMessage();
                }
            }

            // Fallback to Ghostscript CLI if Imagick failed or not available
            if (! $pagePng) {
                try {
                    $pagePng = $this->renderPageWithGhostscript($pdfPath, $pageNumber, $tempDir);
                } catch (Exception $e) {
                    $errors[] = "Ghostscript page {$pageNumber}: ".$e->getMessage();
                }
            }

            if ($pagePng && file_exists($pagePng)) {
                $singlePageImages[] = $pagePng;
            }
        }

        if (empty($singlePageImages)) {
            $errorSummary = ! empty($errors) ? implode('; ', array_unique($errors)) : 'No pages could be rendered.';
            throw new Exception("Failed to extract page images from PDF ({$pdfPath}): {$errorSummary}");
        }

        // Step 2: Combine single page images into stacked image batches
        $generatedImagePaths = $this->batchAndStackImages($singlePageImages, $tempDir, $maxPagesPerImage, $maxHeightPerImage);

        // Unlink individual single-page PNG files as they have been stacked into combined images
        foreach ($singlePageImages as $singleImg) {
            if (file_exists($singleImg) && ! in_array($singleImg, $generatedImagePaths, true)) {
                @unlink($singleImg);
            }
        }

        return $generatedImagePaths;
    }

    /**
     * Get total page count of PDF file.
     */
    protected function getPdfTotalPages(string $pdfPath): int
    {
        if (class_exists(Imagick::class)) {
            try {
                $im = new Imagick;
                $im->pingImage($pdfPath);
                $count = $im->getNumberImages();
                $im->clear();
                $im->destroy();
                if ($count > 0) {
                    return $count;
                }
            } catch (Exception $e) {
                // Ignore and try fallback
            }
        }

        // Ghostscript fallback to count pages
        $gsPath = env('GHOSTSCRIPT_PATH');
        if (empty($gsPath)) {
            $gsPath = '/opt/homebrew/bin/gs';
            if (! file_exists($gsPath)) {
                $gsPath = 'gs';
            }
        }

        try {
            $result = Process::run([
                $gsPath,
                '-q',
                '-dNODISPLAY',
                '--command',
                "({$pdfPath}) (r) file runpdfbegin pdfpagecount = quit",
            ]);

            if ($result->successful()) {
                $output = trim($result->output());
                if (is_numeric($output)) {
                    return (int) $output;
                }
            }
        } catch (Exception $e) {
            // Log fallback error
        }

        return 0;
    }

    /**
     * Render a single PDF page using Imagick.
     */
    protected function renderPageWithImagick(string $pdfPath, int $pageNumber, string $tempDir): string
    {
        $im = new Imagick;
        $im->setResolution(150, 150);
        $im->readImage($pdfPath.'['.($pageNumber - 1).']');
        $im->setImageFormat('png');

        $outputPath = $tempDir.'/'.uniqid("page_{$pageNumber}_").'.png';
        $im->writeImage($outputPath);
        $im->clear();
        $im->destroy();

        return $outputPath;
    }

    /**
     * Render a single PDF page using Ghostscript CLI.
     */
    protected function renderPageWithGhostscript(string $pdfPath, int $pageNumber, string $tempDir): string
    {
        $gsPath = env('GHOSTSCRIPT_PATH');
        if (empty($gsPath)) {
            $gsPath = '/opt/homebrew/bin/gs';
            if (! file_exists($gsPath)) {
                $gsPath = 'gs';
            }
        }

        $outputPath = $tempDir.'/'.uniqid("gs_page_{$pageNumber}_").'.png';

        $result = Process::run([
            $gsPath,
            '-dNOPAUSE',
            '-dBATCH',
            '-sDEVICE=png16m',
            '-r150',
            "-dFirstPage={$pageNumber}",
            "-dLastPage={$pageNumber}",
            "-sOutputFile={$outputPath}",
            $pdfPath,
        ]);

        if (! $result->successful() || ! file_exists($outputPath) || filesize($outputPath) === 0) {
            throw new Exception('Ghostscript CLI failed: '.$result->errorOutput());
        }

        return $outputPath;
    }

    /**
     * Stack array of single page PNG file paths vertically into batch images.
     *
     * @param  array<int, string>  $singlePageImages
     * @return array<int, string>
     */
    protected function batchAndStackImages(
        array $singlePageImages,
        string $tempDir,
        int $maxPagesPerImage,
        int $maxHeightPerImage
    ): array {
        $generatedImagePaths = [];
        $currentBatch = [];
        $currentHeight = 0;

        foreach ($singlePageImages as $imgPath) {
            $im = new Imagick($imgPath);
            $h = $im->getImageHeight();

            if (! empty($currentBatch) && (count($currentBatch) >= $maxPagesPerImage || ($currentHeight + $h) > $maxHeightPerImage)) {
                $generatedImagePaths[] = $this->writeBatchImage($currentBatch, $tempDir);
                $currentBatch = [];
                $currentHeight = 0;
            }

            $currentBatch[] = $im;
            $currentHeight += $h;
        }

        if (! empty($currentBatch)) {
            $generatedImagePaths[] = $this->writeBatchImage($currentBatch, $tempDir);
        }

        return $generatedImagePaths;
    }

    /**
     * Stack array of Imagick page instances vertically into a single PNG image file.
     *
     * @param  array<int, Imagick>  $batch
     */
    protected function writeBatchImage(array $batch, string $tempDir): string
    {
        $stack = new Imagick;
        foreach ($batch as $pageIm) {
            $stack->addImage($pageIm);
        }

        $stack->resetIterator();
        $combined = $stack->appendImages(true);
        $combined->setImageFormat('png');

        $outputPath = $tempDir.'/'.uniqid('img_sec_').'.png';
        $combined->writeImage($outputPath);

        foreach ($batch as $pageIm) {
            $pageIm->clear();
            $pageIm->destroy();
        }
        $stack->clear();
        $stack->destroy();
        $combined->clear();
        $combined->destroy();

        return $outputPath;
    }
}
