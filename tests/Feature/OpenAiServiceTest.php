<?php

namespace Tests\Feature;

use App\Services\OpenAiService;
use Exception;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiServiceTest extends TestCase
{
    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir().'/openai_service_tests_'.uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob("{$this->tempDir}/*"));
            rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    /**
     * Test basic text chat completion.
     */
    public function test_chat_sends_correct_payload_and_returns_content(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Hello user! I am an AI.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new OpenAiService('test-api-key');
        $result = $service->chat('Hi there');

        $this->assertEquals('Hello user! I am an AI.', $result);

        Http::assertSent(function (Request $request) {
            $this->assertEquals('https://api.openai.com/v1/chat/completions', $request->url());
            $this->assertEquals('Bearer test-api-key', $request->header('Authorization')[0]);

            $data = $request->data();
            $this->assertEquals('gpt-4o', $data['model']);
            $this->assertEquals('Hi there', $data['messages'][0]['content']);
            $this->assertEquals('user', $data['messages'][0]['role']);

            return true;
        });
    }

    /**
     * Test chat with images sends base64 image_url.
     */
    public function test_chat_with_images_sends_base64_payload(): void
    {
        $mockImage = "{$this->tempDir}/test.png";
        // Write valid 1x1 PNG bytes so mime_content_type detects it as an image
        file_put_contents($mockImage, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

        Http::fake([
            'custom-url.com/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Identified the image content.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new OpenAiService('custom-key', 'https://custom-url.com', 'custom-model');
        $result = $service->chatWithImages('What is this?', [$mockImage]);

        $this->assertEquals('Identified the image content.', $result);

        Http::assertSent(function (Request $request) {
            $data = $request->data();
            $content = $data['messages'][0]['content'] ?? [];

            $expectedBase64 = base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

            return $data['model'] === 'custom-model'
                && $data['messages'][0]['role'] === 'user'
                && count($content) === 2
                && $content[0]['type'] === 'text'
                && $content[0]['text'] === 'What is this?'
                && $content[1]['type'] === 'image_url'
                && str_contains($content[1]['image_url']['url'] ?? '', "data:image/png;base64,{$expectedBase64}");
        });
    }

    /**
     * Test chat with PDFs.
     */
    public function test_chat_with_pdfs_sends_payload_in_specified_format(): void
    {
        $mockPdf = "{$this->tempDir}/test.pdf";
        file_put_contents($mockPdf, 'fake pdf data');

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Summary of the PDF.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new OpenAiService('test-key');

        // Test file_url format (default)
        $result1 = $service->chatWithPdfs('Summarize this', [$mockPdf]);
        $this->assertEquals('Summary of the PDF.', $result1);

        Http::assertSent(function (Request $request) {
            $data = $request->data();
            $content = $data['messages'][0]['content'] ?? [];
            $expectedBase64 = base64_encode('fake pdf data');

            return isset($content[1]['type'])
                && $content[1]['type'] === 'file_url'
                && ($content[1]['file_url']['url'] ?? '') === "data:application/pdf;base64,{$expectedBase64}";
        });

        // Test document format
        $result2 = $service->chatWithPdfs('Summarize this', [$mockPdf], null, 'document');
        $this->assertEquals('Summary of the PDF.', $result2);

        Http::assertSent(function (Request $request) {
            $data = $request->data();
            $content = $data['messages'][0]['content'] ?? [];
            $expectedBase64 = base64_encode('fake pdf data');

            return isset($content[1]['type'])
                && $content[1]['type'] === 'document'
                && ($content[1]['source']['type'] ?? '') === 'base64'
                && ($content[1]['source']['media_type'] ?? '') === 'application/pdf'
                && ($content[1]['source']['data'] ?? '') === $expectedBase64;
        });
    }

    /**
     * Test error handling when API fails.
     */
    public function test_chat_throws_exception_on_api_failure(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'error' => [
                    'message' => 'Rate limit exceeded.',
                ],
            ], 429),
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('OpenAI API Error (429): Rate limit exceeded.');

        $service = new OpenAiService('test-key');
        $service->chat('Test prompt');
    }
}
