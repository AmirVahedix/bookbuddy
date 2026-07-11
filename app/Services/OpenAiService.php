<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class OpenAiService
{
    protected string $apiKey;

    protected string $baseUrl;

    protected string $defaultModel;

    protected int $timeout;

    protected int $connectTimeout;

    /**
     * Create a new OpenAiService instance.
     */
    public function __construct(
        ?string $apiKey = null,
        ?string $baseUrl = null,
        ?string $defaultModel = null,
        int $timeout = 60,
        int $connectTimeout = 10
    ) {
        $this->apiKey = $apiKey ?? config('services.openai.api_key') ?? '';
        $this->baseUrl = rtrim($baseUrl ?? config('services.openai.base_url') ?? 'https://api.openai.com/v1', '/');
        $this->defaultModel = $defaultModel ?? config('services.openai.model') ?? 'gpt-4o';
        $this->timeout = $timeout;
        $this->connectTimeout = $connectTimeout;
    }

    /**
     * Send a standard text prompt to the chat completion API.
     *
     * @throws Exception
     */
    public function chat(string $prompt, ?string $model = null): string
    {
        $response = $this->request()
            ->post('/chat/completions', [
                'model' => $model ?? $this->defaultModel,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ]);

        return $this->parseResponse($response);
    }

    /**
     * Send a prompt with multiple local images (base64 encoded) to the chat completion API.
     *
     * @param  array<int, string>  $imagePaths
     *
     * @throws Exception
     */
    public function chatWithImages(string $prompt, array $imagePaths, ?string $model = null): string
    {
        $content = [
            [
                'type' => 'text',
                'text' => $prompt,
            ],
        ];

        foreach ($imagePaths as $path) {
            if (! file_exists($path)) {
                throw new InvalidArgumentException("Image file not found: {$path}");
            }

            $mimeType = mime_content_type($path);
            if (! $mimeType || ! str_starts_with($mimeType, 'image/')) {
                throw new InvalidArgumentException("File is not a valid image: {$path}");
            }

            $base64Data = base64_encode(file_get_contents($path));
            $content[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => "data:{$mimeType};base64,{$base64Data}",
                ],
            ];
        }

        $response = $this->request()
            ->post('/chat/completions', [
                'model' => $model ?? $this->defaultModel,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $content,
                    ],
                ],
            ]);

        return $this->parseResponse($response);
    }

    /**
     * Send a prompt with multiple local PDF files to the chat completion API.
     *
     * Supports multiple document format payloads for different custom OpenAI-compatible endpoints:
     * - 'file_url': standard Data URI structure (e.g. data:application/pdf;base64,...)
     * - 'document': Anthropic/Gemini-compatible document block format
     *
     * @param  array<int, string>  $pdfPaths
     * @param  string  $format  The format to send the PDFs ('file_url' or 'document')
     *
     * @throws Exception
     */
    public function chatWithPdfs(string $prompt, array $pdfPaths, ?string $model = null, string $format = 'file_url'): string
    {
        if (! in_array($format, ['file_url', 'document', 'file'])) {
            throw new InvalidArgumentException("Unsupported PDF payload format: {$format}");
        }

        $content = [
            [
                'type' => 'text',
                'text' => $prompt,
            ],
        ];

        foreach ($pdfPaths as $path) {
            if (! file_exists($path)) {
                throw new InvalidArgumentException("PDF file not found: {$path}");
            }

            $mimeType = mime_content_type($path);
            if ($mimeType !== 'application/pdf') {
                // Perform simple extension check in case of mock files in testing
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if ($extension !== 'pdf') {
                    throw new InvalidArgumentException("File is not a valid PDF: {$path}");
                }
                $mimeType = 'application/pdf';
            }

            $base64Data = base64_encode(file_get_contents($path));

            if ($format === 'file_url') {
                $content[] = [
                    'type' => 'file_url',
                    'file_url' => [
                        'url' => "data:application/pdf;base64,{$base64Data}",
                    ],
                ];
            } elseif ($format === 'document') {
                $content[] = [
                    'type' => 'document',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => 'application/pdf',
                        'data' => $base64Data,
                    ],
                ];
            } else {
                $content[] = [
                    'type' => 'file',
                    'file' => [
                        'filename' => basename($path),
                        'file_data' => "data:application/pdf;base64,{$base64Data}",
                    ],
                ];
            }
        }

        $response = $this->request()
            ->post('/chat/completions', [
                'model' => $model ?? $this->defaultModel,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $content,
                    ],
                ],
            ]);

        return $this->parseResponse($response);
    }

    /**
     * Build the HTTP client request instance.
     */
    protected function request(): PendingRequest
    {
        $headers = [];
        if (! empty($this->apiKey)) {
            $headers['Authorization'] = 'Bearer '.$this->apiKey;
        }

        return Http::baseUrl($this->baseUrl)
            ->withHeaders($headers)
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout);
    }

    /**
     * Parse and validate the response from the API.
     *
     * @throws Exception
     */
    protected function parseResponse(Response $response): string
    {
        if ($response->failed()) {
            $errorMsg = $response->json('error.message') ?? $response->body() ?? 'API Request Failed';
            throw new Exception("OpenAI API Error ({$response->status()}): {$errorMsg}");
        }

        $choiceContent = $response->json('choices.0.message.content');
        if ($choiceContent === null) {
            throw new Exception('Unexpected API Response format: '.$response->body());
        }

        return $choiceContent;
    }
}
