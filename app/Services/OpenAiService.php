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
        if (empty($imagePaths)) {
            throw new InvalidArgumentException('No images were provided or extracted to upload to the AI model.');
        }

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
     * Stream a prompt with multiple local images (base64 encoded) to the chat completion API.
     *
     * @param  array<int, string>  $imagePaths
     * @param  callable(string): void  $callback
     *
     * @throws Exception
     */
    public function streamChatWithImages(
        string $prompt,
        array $imagePaths,
        callable $callback,
        ?string $model = null
    ): void {
        if (empty($imagePaths)) {
            throw new InvalidArgumentException('No images were provided or extracted to upload to the AI model.');
        }

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
            ->withOptions(['stream' => true])
            ->post('/chat/completions', [
                'model' => $model ?? $this->defaultModel,
                'stream' => true,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $content,
                    ],
                ],
            ]);

        if ($response->failed()) {
            $errorMsg = $response->json('error.message') ?? $response->body() ?? 'API Request Failed';
            throw new Exception("OpenAI API Error ({$response->status()}): {$errorMsg}");
        }

        $stream = $response->toPsrResponse()->getBody();
        $buffer = '';

        while (! $stream->eof()) {
            $chunk = $stream->read(1024);
            $buffer .= $chunk;

            while (($lineEnd = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $lineEnd);
                $buffer = substr($buffer, $lineEnd + 1);
                $line = trim($line);

                if (! str_starts_with($line, 'data:')) {
                    continue;
                }

                $jsonStr = trim(substr($line, 5));
                if ($jsonStr === '[DONE]') {
                    break 2;
                }

                $json = json_decode($jsonStr, true);
                if (is_array($json)) {
                    $delta = $json['choices'][0]['delta']['content'] ?? $json['choices'][0]['message']['content'] ?? null;
                    if ($delta !== null && $delta !== '') {
                        $callback($delta);
                    }
                }
            }
        }
    }

    /**
     * Send a full chat conversation with multiple local images attached to the initial turn.
     *
     * @param  array<int, string>  $imagePaths
     * @param  array<int, array{role: string, content: string}>  $chatHistory
     *
     * @throws Exception
     */
    public function chatConversationWithImages(
        string $initialPrompt,
        array $imagePaths,
        array $chatHistory,
        ?string $model = null
    ): string {
        if (empty($imagePaths)) {
            throw new InvalidArgumentException('No images were provided or extracted to upload to the AI model.');
        }

        $firstContent = [
            [
                'type' => 'text',
                'text' => $initialPrompt,
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
            $firstContent[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => "data:{$mimeType};base64,{$base64Data}",
                ],
            ];
        }

        $messages = [];
        $messages[] = [
            'role' => 'user',
            'content' => $firstContent,
        ];

        foreach ($chatHistory as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        $response = $this->request()
            ->post('/chat/completions', [
                'model' => $model ?? $this->defaultModel,
                'messages' => $messages,
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
     * Send a prompt with multiple local EPUB files to the chat completion API.
     *
     * Supports multiple document format payloads for different custom OpenAI-compatible endpoints:
     * - 'file_url': standard Data URI structure (e.g. data:application/epub+zip;base64,...)
     * - 'document': Anthropic/Gemini-compatible document block format
     *
     * @param  array<int, string>  $epubPaths
     * @param  string  $format  The format to send the EPUBs ('file_url' or 'document')
     *
     * @throws Exception
     */
    public function chatWithEpubs(string $prompt, array $epubPaths, ?string $model = null, string $format = 'file_url'): string
    {
        if (! in_array($format, ['file_url', 'document', 'file'])) {
            throw new InvalidArgumentException("Unsupported EPUB payload format: {$format}");
        }

        $content = [
            [
                'type' => 'text',
                'text' => $prompt,
            ],
        ];

        foreach ($epubPaths as $path) {
            if (! file_exists($path)) {
                throw new InvalidArgumentException("EPUB file not found: {$path}");
            }

            $mimeType = mime_content_type($path);
            if ($mimeType !== 'application/epub+zip') {
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if ($extension !== 'epub') {
                    throw new InvalidArgumentException("File is not a valid EPUB: {$path}");
                }
                $mimeType = 'application/epub+zip';
            }

            $base64Data = base64_encode(file_get_contents($path));

            if ($format === 'file_url') {
                $content[] = [
                    'type' => 'file_url',
                    'file_url' => [
                        'url' => "data:application/epub+zip;base64,{$base64Data}",
                    ],
                ];
            } elseif ($format === 'document') {
                $content[] = [
                    'type' => 'document',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => 'application/epub+zip',
                        'data' => $base64Data,
                    ],
                ];
            } else {
                $content[] = [
                    'type' => 'file',
                    'file' => [
                        'filename' => basename($path),
                        'file_data' => "data:application/epub+zip;base64,{$base64Data}",
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
     * Send a full chat conversation with an EPUB attached to the first message.
     *
     * @param  array<int, array{role: string, content: string}>  $chatHistory
     * @param  array<int, string>  $epubPaths
     * @param  string  $format  The format to send the EPUBs ('file_url' or 'document')
     *
     * @throws Exception
     */
    public function chatConversationWithEpub(
        string $initialPrompt,
        array $epubPaths,
        array $chatHistory,
        ?string $model = null,
        string $format = 'file_url'
    ): string {
        if (! in_array($format, ['file_url', 'document', 'file'])) {
            throw new InvalidArgumentException("Unsupported EPUB payload format: {$format}");
        }

        $firstContent = [
            [
                'type' => 'text',
                'text' => $initialPrompt,
            ],
        ];

        foreach ($epubPaths as $path) {
            if (! file_exists($path)) {
                throw new InvalidArgumentException("EPUB file not found: {$path}");
            }

            $mimeType = mime_content_type($path);
            if ($mimeType !== 'application/epub+zip') {
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if ($extension !== 'epub') {
                    throw new InvalidArgumentException("File is not a valid EPUB: {$path}");
                }
                $mimeType = 'application/epub+zip';
            }

            $base64Data = base64_encode(file_get_contents($path));

            if ($format === 'file_url') {
                $firstContent[] = [
                    'type' => 'file_url',
                    'file_url' => [
                        'url' => "data:application/epub+zip;base64,{$base64Data}",
                    ],
                ];
            } elseif ($format === 'document') {
                $firstContent[] = [
                    'type' => 'document',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => 'application/epub+zip',
                        'data' => $base64Data,
                    ],
                ];
            } else {
                $firstContent[] = [
                    'type' => 'file',
                    'file' => [
                        'filename' => basename($path),
                        'file_data' => "data:application/epub+zip;base64,{$base64Data}",
                    ],
                ];
            }
        }

        $messages = [];
        $messages[] = [
            'role' => 'user',
            'content' => $firstContent,
        ];

        foreach ($chatHistory as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        $response = $this->request()
            ->post('/chat/completions', [
                'model' => $model ?? $this->defaultModel,
                'messages' => $messages,
            ]);

        return $this->parseResponse($response);
    }

    /**
     * Send a full chat conversation with a PDF attached to the first message.
     *
     * @param  array<int, array{role: string, content: string}>  $chatHistory
     * @param  array<int, string>  $pdfPaths
     * @param  string  $format  The format to send the PDFs ('file_url' or 'document')
     *
     * @throws Exception
     */
    public function chatConversationWithPdf(
        string $initialPrompt,
        array $pdfPaths,
        array $chatHistory,
        ?string $model = null,
        string $format = 'file_url'
    ): string {
        if (! in_array($format, ['file_url', 'document', 'file'])) {
            throw new InvalidArgumentException("Unsupported PDF payload format: {$format}");
        }

        $firstContent = [
            [
                'type' => 'text',
                'text' => $initialPrompt,
            ],
        ];

        foreach ($pdfPaths as $path) {
            if (! file_exists($path)) {
                throw new InvalidArgumentException("PDF file not found: {$path}");
            }

            $mimeType = mime_content_type($path);
            if ($mimeType !== 'application/pdf') {
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if ($extension !== 'pdf') {
                    throw new InvalidArgumentException("File is not a valid PDF: {$path}");
                }
                $mimeType = 'application/pdf';
            }

            $base64Data = base64_encode(file_get_contents($path));

            if ($format === 'file_url') {
                $firstContent[] = [
                    'type' => 'file_url',
                    'file_url' => [
                        'url' => "data:application/pdf;base64,{$base64Data}",
                    ],
                ];
            } elseif ($format === 'document') {
                $firstContent[] = [
                    'type' => 'document',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => 'application/pdf',
                        'data' => $base64Data,
                    ],
                ];
            } else {
                $firstContent[] = [
                    'type' => 'file',
                    'file' => [
                        'filename' => basename($path),
                        'file_data' => "data:application/pdf;base64,{$base64Data}",
                    ],
                ];
            }
        }

        $messages = [];
        $messages[] = [
            'role' => 'user',
            'content' => $firstContent,
        ];

        foreach ($chatHistory as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        $response = $this->request()
            ->post('/chat/completions', [
                'model' => $model ?? $this->defaultModel,
                'messages' => $messages,
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

        $choiceContent = $response->json('choices.0.message.content') ?? $response->json('choices.0.delta.content');
        if ($choiceContent !== null) {
            return $choiceContent;
        }

        // Parse SSE streaming response body if returned by custom proxy endpoints
        $body = $response->body();
        if (str_contains($body, 'data:')) {
            $lines = explode("\n", $body);
            $accumulated = '';
            foreach ($lines as $line) {
                $line = trim($line);
                if (! str_starts_with($line, 'data:')) {
                    continue;
                }
                $jsonStr = trim(substr($line, 5));
                if ($jsonStr === '[DONE]') {
                    break;
                }
                $json = json_decode($jsonStr, true);
                if (is_array($json)) {
                    if (isset($json['choices'][0]['delta']['content'])) {
                        $accumulated .= $json['choices'][0]['delta']['content'];
                    } elseif (isset($json['choices'][0]['message']['content'])) {
                        $accumulated .= $json['choices'][0]['message']['content'];
                    }
                }
            }

            if ($accumulated !== '') {
                return $accumulated;
            }
        }

        throw new Exception('Unexpected API Response format: '.$response->body());
    }
}
