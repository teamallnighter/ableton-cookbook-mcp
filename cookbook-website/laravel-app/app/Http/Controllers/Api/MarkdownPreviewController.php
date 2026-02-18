<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MarkdownService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Markdown', description: 'Markdown processing and preview operations')]
class MarkdownPreviewController extends Controller
{
    private MarkdownService $markdownService;

    public function __construct(MarkdownService $markdownService)
    {
        $this->markdownService = $markdownService;
    }

    #[OA\Post(
        path: '/api/markdown/preview',
        summary: 'Generate HTML preview from markdown',
        description: 'Converts markdown content to HTML with Ableton-specific extensions and media embedding',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    required: ['markdown'],
                    properties: [
                        new OA\Property(
                            property: 'markdown',
                            type: 'string',
                            description: 'Markdown content to preview',
                            example: '# Hello World\n\nThis is a **bold** statement with a [[rack:550e8400-e29b-41d4-a716-446655440000]] embed.'
                        ),
                        new OA\Property(
                            property: 'options',
                            type: 'object',
                            description: 'Preview options',
                            properties: [
                                new OA\Property(property: 'enable_ableton_extensions', type: 'boolean', example: true),
                                new OA\Property(property: 'enable_media_embeds', type: 'boolean', example: true),
                                new OA\Property(property: 'cache_result', type: 'boolean', example: false),
                            ]
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Markdown successfully converted to HTML',
                content: new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'html', type: 'string', description: 'Generated HTML content'),
                            new OA\Property(property: 'reading_time', type: 'integer', description: 'Estimated reading time in minutes'),
                            new OA\Property(property: 'word_count', type: 'integer', description: 'Word count of content'),
                            new OA\Property(property: 'headings', type: 'array', items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'level', type: 'integer'),
                                    new OA\Property(property: 'text', type: 'string'),
                                    new OA\Property(property: 'slug', type: 'string')
                                ]
                            )),
                        ]
                    )
                )
            ),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 429, description: 'Rate limit exceeded'),
            new OA\Response(response: 500, description: 'Server error')
        ]
    )]
    public function preview(Request $request): JsonResponse
    {
        // Rate limiting to prevent abuse
        $executed = RateLimiter::attempt(
            'markdown-preview:' . $request->ip(),
            $perMinute = 60,
            function() {}
        );

        if (!$executed) {
            return response()->json([
                'success' => false,
                'error' => 'Too many preview requests. Please wait before trying again.',
                'retry_after' => RateLimiter::availableIn('markdown-preview:' . $request->ip())
            ], 429);
        }

        $validator = Validator::make($request->all(), [
            'markdown' => 'required|string|max:100000', // 100KB limit
            'options' => 'sometimes|array',
            'options.enable_ableton_extensions' => 'sometimes|boolean',
            'options.enable_media_embeds' => 'sometimes|boolean',
            'options.cache_result' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $markdown = $request->input('markdown');
        $options = $request->input('options', []);

        try {
            // Validate content for potential issues
            $issues = $this->markdownService->validateContent($markdown);
            if (!empty($issues)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Content validation failed',
                    'issues' => $issues
                ], 422);
            }

            // Validate embedded media
            $mediaIssues = $this->markdownService->validateEmbeddedMedia($markdown);
            if (!empty($mediaIssues)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Media validation failed',
                    'issues' => $mediaIssues
                ], 422);
            }

            // Generate HTML preview
            $html = $this->markdownService->parseToHtml($markdown);

            // Calculate reading time and word count
            $readingTime = $this->markdownService->getReadingTime($markdown);
            $plainText = $this->markdownService->stripMarkdown($markdown);
            $wordCount = str_word_count($plainText);

            // Extract headings for table of contents
            $headings = $this->markdownService->extractHeadings($markdown);

            return response()->json([
                'success' => true,
                'html' => $html,
                'reading_time' => $readingTime,
                'word_count' => $wordCount,
                'headings' => $headings,
                'meta' => [
                    'processed_at' => now()->toISOString(),
                    'content_length' => strlen($markdown),
                    'html_length' => strlen($html),
                    'ableton_extensions_enabled' => $options['enable_ableton_extensions'] ?? true,
                    'media_embeds_enabled' => $options['enable_media_embeds'] ?? true,
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Markdown preview error', [
                'error' => $e->getMessage(),
                'markdown_length' => strlen($markdown),
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to process markdown content',
                'message' => config('app.debug') ? $e->getMessage() : 'An error occurred while processing your content'
            ], 500);
        }
    }

    #[OA\Post(
        path: '/api/markdown/validate',
        summary: 'Validate markdown content',
        description: 'Validates markdown content for potential issues without generating HTML',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    required: ['markdown'],
                    properties: [
                        new OA\Property(
                            property: 'markdown',
                            type: 'string',
                            description: 'Markdown content to validate'
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Validation results',
                content: new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean'),
                            new OA\Property(property: 'valid', type: 'boolean'),
                            new OA\Property(property: 'issues', type: 'array', items: new OA\Items(type: 'string')),
                            new OA\Property(property: 'media_issues', type: 'array', items: new OA\Items(type: 'string')),
                            new OA\Property(property: 'statistics', type: 'object')
                        ]
                    )
                )
            )
        ]
    )]
    public function validateMarkdown(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'markdown' => 'required|string|max:100000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $markdown = $request->input('markdown');

        try {
            // Validate content
            $issues = $this->markdownService->validateContent($markdown);
            $mediaIssues = $this->markdownService->validateEmbeddedMedia($markdown);

            // Calculate statistics
            $plainText = $this->markdownService->stripMarkdown($markdown);
            $statistics = [
                'character_count' => strlen($markdown),
                'word_count' => str_word_count($plainText),
                'line_count' => substr_count($markdown, "\n") + 1,
                'reading_time' => $this->markdownService->getReadingTime($markdown),
                'heading_count' => count($this->markdownService->extractHeadings($markdown)),
            ];

            return response()->json([
                'success' => true,
                'valid' => empty($issues) && empty($mediaIssues),
                'issues' => $issues,
                'media_issues' => $mediaIssues,
                'statistics' => $statistics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to validate markdown content',
                'message' => config('app.debug') ? $e->getMessage() : 'Validation error occurred'
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/markdown/syntax-help',
        summary: 'Get Ableton markdown syntax help',
        description: 'Returns documentation for Ableton-specific markdown extensions',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Syntax help documentation',
                content: new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean'),
                            new OA\Property(property: 'syntax_help', type: 'object')
                        ]
                    )
                )
            )
        ]
    )]
    public function syntaxHelp(): JsonResponse
    {
        $syntaxHelp = [
            'basic_markdown' => [
                'title' => 'Basic Markdown',
                'examples' => [
                    '**bold text**',
                    '*italic text*',
                    '# Heading 1',
                    '## Heading 2',
                    '- Bullet point',
                    '1. Numbered list',
                    '[Link text](https://example.com)',
                    '![Image alt](https://example.com/image.jpg)',
                    '`inline code`',
                    "```\ncode block\n```"
                ]
            ],
            'ableton_extensions' => [
                'title' => 'Ableton-Specific Extensions',
                'rack_embeds' => [
                    'description' => 'Embed rack previews in your content',
                    'examples' => [
                        '[[rack:550e8400-e29b-41d4-a716-446655440000]]',
                        '[[rack:550e8400-e29b-41d4-a716-446655440000|preset:compact]]',
                        '[[rack:550e8400-e29b-41d4-a716-446655440000|preset:detailed|theme:dark]]'
                    ]
                ],
                'device_references' => [
                    'description' => 'Reference Ableton devices and parameters',
                    'examples' => [
                        '{{device:Operator}}',
                        '{{device:Operator|param:Volume}}',
                        '{{device:EQ Eight|param:Frequency}}'
                    ]
                ],
                'parameter_controls' => [
                    'description' => 'Interactive parameter visualizations',
                    'examples' => [
                        '{param:Cutoff}',
                        '{param:Resonance|min:0|max:127|value:64}',
                        '{param:Attack|min:0|max:10000|value:100}'
                    ]
                ],
                'audio_player' => [
                    'description' => 'Embed audio players with waveform visualization',
                    'examples' => [
                        ':::audio[My Track](https://example.com/audio.mp3)',
                        ':::audio[Demo Jam](https://example.com/demo.wav)'
                    ]
                ]
            ],
            'media_embeds' => [
                'title' => 'Media Embeds',
                'examples' => [
                    '[Video Title](https://youtube.com/watch?v=VIDEO_ID)',
                    '[SoundCloud - Track Name](https://soundcloud.com/user/track)',
                    '[Vimeo Video](https://vimeo.com/123456789)',
                    '[embed](https://supported-site.com/content)'
                ]
            ],
            'best_practices' => [
                'title' => 'Best Practices',
                'tips' => [
                    'Use rack embeds to showcase specific configurations',
                    'Reference devices by their exact Ableton names',
                    'Keep parameter controls simple and focused',
                    'Provide meaningful titles for audio players',
                    'Test media embeds before publishing',
                    'Use headings to structure your content',
                    'Keep content under 100KB for best performance'
                ]
            ]
        ];

        return response()->json([
            'success' => true,
            'syntax_help' => $syntaxHelp
        ]);
    }
}