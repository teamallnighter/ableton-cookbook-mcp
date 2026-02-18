<?php

namespace App\Services;

use App\Models\Rack;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ConfigurableExtensionInterface;
use League\Config\ConfigurationBuilderInterface;
use Nette\Schema\Expect;
use League\CommonMark\Node\Inline\AbstractInline;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use League\CommonMark\Parser\MarkdownParserStateInterface;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use Illuminate\Support\Facades\Cache;

/**
 * Ableton Cookbook Markdown Extension
 * Adds music production-specific markdown syntax for racks, devices, and widgets
 */
class AbletonMarkdownExtension implements ConfigurableExtensionInterface
{
    public function configureSchema(ConfigurationBuilderInterface $builder): void
    {
        $builder->addSchema('ableton', Expect::structure([
            'enable_rack_embeds' => Expect::bool()->default(true),
            'enable_device_refs' => Expect::bool()->default(true),
            'enable_parameter_controls' => Expect::bool()->default(true),
            'enable_audio_player' => Expect::bool()->default(true),
            'cache_widgets' => Expect::bool()->default(true),
            'widget_cache_ttl' => Expect::int()->default(3600),
        ]));
    }

    public function register(EnvironmentBuilderInterface $environment): void
    {
        // Get configuration from environment with defaults
        $config = $environment->getConfiguration()->get('ableton');

        // Register inline parsers for Ableton-specific syntax
        if ($config['enable_rack_embeds']) {
            $environment->addInlineParser(new RackEmbedParser());
            $environment->addRenderer(RackEmbedInline::class, new RackEmbedRenderer($config));
        }

        if ($config['enable_device_refs']) {
            $environment->addInlineParser(new DeviceReferenceParser());
            $environment->addRenderer(DeviceReferenceInline::class, new DeviceReferenceRenderer($config));
        }

        if ($config['enable_parameter_controls']) {
            $environment->addInlineParser(new ParameterControlParser());
            $environment->addRenderer(ParameterControlInline::class, new ParameterControlRenderer($config));
        }

        if ($config['enable_audio_player']) {
            $environment->addBlockStartParser(new AudioPlayerBlockParser());
            $environment->addRenderer(AudioPlayerBlock::class, new AudioPlayerRenderer($config));
        }
    }
}

/**
 * Rack Embed Inline Node - [[rack:uuid]]
 */
class RackEmbedInline extends AbstractInline
{
    public string $rackUuid;
    public array $options;

    public function __construct(string $rackUuid, array $options = [])
    {
        parent::__construct();
        $this->rackUuid = $rackUuid;
        $this->options = $options;
    }
}

/**
 * Device Reference Inline Node - {{device:Operator|param:Volume}}
 */
class DeviceReferenceInline extends AbstractInline
{
    public string $deviceName;
    public ?string $parameter;
    public array $options;

    public function __construct(string $deviceName, ?string $parameter = null, array $options = [])
    {
        parent::__construct();
        $this->deviceName = $deviceName;
        $this->parameter = $parameter;
        $this->options = $options;
    }
}

/**
 * Parameter Control Inline Node - {param:cutoff|min:0|max:127|value:64}
 */
class ParameterControlInline extends AbstractInline
{
    public string $paramName;
    public int $min;
    public int $max;
    public int $value;
    public array $options;

    public function __construct(string $paramName, int $min = 0, int $max = 127, int $value = 64, array $options = [])
    {
        parent::__construct();
        $this->paramName = $paramName;
        $this->min = $min;
        $this->max = $max;
        $this->value = $value;
        $this->options = $options;
    }
}

/**
 * Audio Player Block Node - :::audio[title](url)
 */
class AudioPlayerBlock extends AbstractBlock
{
    public string $title;
    public string $url;
    public array $options;

    public function __construct(string $title, string $url, array $options = [])
    {
        parent::__construct();
        $this->title = $title;
        $this->url = $url;
        $this->options = $options;
    }
}

/**
 * Parser for Rack Embeds: [[rack:uuid]] or [[rack:uuid|preset:compact]]
 */
class RackEmbedParser implements InlineParserInterface
{
    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('\[\[rack:([a-f0-9-]{36})(?:\|([^\]]+))?\]\]', 'i');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();
        $cursor->advanceBy($inlineContext->getFullMatchLength());

        $matches = $inlineContext->getMatches();
        $uuid = $matches[1];
        $optionsString = $matches[2] ?? '';

        // Parse options (preset:compact, theme:dark, etc.)
        $options = $this->parseOptions($optionsString);

        $inlineContext->getContainer()->appendChild(new RackEmbedInline($uuid, $options));

        return true;
    }

    private function parseOptions(string $optionsString): array
    {
        $options = [];
        if (empty($optionsString)) {
            return $options;
        }

        $pairs = explode('|', $optionsString);
        foreach ($pairs as $pair) {
            if (strpos($pair, ':') !== false) {
                [$key, $value] = explode(':', $pair, 2);
                $options[trim($key)] = trim($value);
            }
        }

        return $options;
    }
}

/**
 * Parser for Device References: {{device:Operator}} or {{device:Operator|param:Volume}}
 */
class DeviceReferenceParser implements InlineParserInterface
{
    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('\{\{device:([^}|]+)(?:\|param:([^}]+))?\}\}');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();
        $cursor->advanceBy($inlineContext->getFullMatchLength());

        $matches = $inlineContext->getMatches();
        $deviceName = trim($matches[1]);
        $parameter = isset($matches[2]) ? trim($matches[2]) : null;

        $inlineContext->getContainer()->appendChild(new DeviceReferenceInline($deviceName, $parameter));

        return true;
    }
}

/**
 * Parser for Parameter Controls: {param:cutoff|min:0|max:127|value:64}
 */
class ParameterControlParser implements InlineParserInterface
{
    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('\{param:([^}|]+)(?:\|min:(\d+))?(?:\|max:(\d+))?(?:\|value:(\d+))?\}');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();
        $cursor->advanceBy($inlineContext->getFullMatchLength());

        $matches = $inlineContext->getMatches();
        $paramName = trim($matches[1]);
        $min = isset($matches[2]) ? (int)$matches[2] : 0;
        $max = isset($matches[3]) ? (int)$matches[3] : 127;
        $value = isset($matches[4]) ? (int)$matches[4] : 64;

        $inlineContext->getContainer()->appendChild(new ParameterControlInline($paramName, $min, $max, $value));

        return true;
    }
}

/**
 * Parser for Audio Player Blocks: :::audio[title](url)
 */
class AudioPlayerBlockParser implements BlockStartParserInterface
{
    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented()) {
            return null;
        }

        if (!$cursor->match('/^:::audio\[([^\]]+)\]\(([^)]+)\)/')) {
            return null;
        }

        $matches = $cursor->getMatches();
        $title = trim($matches[1]);
        $url = trim($matches[2]);

        return BlockStart::of(new AudioPlayerBlock($title, $url))->at($cursor);
    }
}

/**
 * Renderer for Rack Embeds
 */
class RackEmbedRenderer implements NodeRendererInterface
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function render($node, ChildNodeRendererInterface $childRenderer)
    {
        if (!$node instanceof RackEmbedInline) {
            throw new \InvalidArgumentException('Incompatible node type');
        }

        // Use caching if enabled
        $cacheKey = "rack_embed_{$node->rackUuid}_{md5(serialize($node->options))}";

        if ($this->config['cache_widgets'] ?? true) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        $rack = Rack::where('uuid', $node->rackUuid)->first();
        if (!$rack) {
            return new HtmlElement(
                'div',
                ['class' => 'rack-embed-error'],
                'Rack not found: ' . htmlspecialchars($node->rackUuid)
            );
        }

        $preset = $node->options['preset'] ?? 'default';
        $theme = $node->options['theme'] ?? 'light';

        $html = $this->renderRackEmbed($rack, $preset, $theme);

        // Cache the result if caching is enabled
        if ($this->config['cache_widgets'] ?? true) {
            $ttl = $this->config['widget_cache_ttl'] ?? 3600;
            Cache::put($cacheKey, $html, $ttl);
        }

        return $html;
    }

    private function renderRackEmbed(Rack $rack, string $preset, string $theme): HtmlElement
    {
        $classes = ['rack-embed', "rack-embed--{$preset}", "rack-embed--{$theme}"];

        switch ($preset) {
            case 'compact':
                return new HtmlElement('div', ['class' => implode(' ', $classes)], [
                    new HtmlElement('div', ['class' => 'rack-embed__header'], [
                        new HtmlElement('h4', ['class' => 'rack-embed__title'], htmlspecialchars($rack->title)),
                        new HtmlElement('span', ['class' => 'rack-embed__by'], 'by ' . htmlspecialchars($rack->user->name)),
                    ]),
                    new HtmlElement('div', ['class' => 'rack-embed__meta'], [
                        new HtmlElement('span', ['class' => 'rack-embed__devices'], $rack->device_count . ' devices'),
                        new HtmlElement('a', [
                            'href' => route('racks.show', $rack),
                            'class' => 'rack-embed__link'
                        ], 'View Rack →'),
                    ]),
                ]);

            case 'detailed':
                return new HtmlElement('div', ['class' => implode(' ', $classes)], [
                    new HtmlElement('div', ['class' => 'rack-embed__header'], [
                        new HtmlElement('h3', ['class' => 'rack-embed__title'], htmlspecialchars($rack->title)),
                        new HtmlElement('div', ['class' => 'rack-embed__author'], [
                            new HtmlElement('span', [], 'by '),
                            new HtmlElement('a', [
                                'href' => route('users.show', $rack->user),
                                'class' => 'rack-embed__author-link'
                            ], htmlspecialchars($rack->user->name)),
                        ]),
                    ]),
                    new HtmlElement(
                        'div',
                        ['class' => 'rack-embed__description'],
                        $rack->description ? htmlspecialchars($rack->description) : 'No description available'
                    ),
                    new HtmlElement(
                        'div',
                        ['class' => 'rack-embed__devices'],
                        $this->renderDeviceList($rack)
                    ),
                    new HtmlElement('div', ['class' => 'rack-embed__actions'], [
                        new HtmlElement('a', [
                            'href' => route('racks.download', $rack),
                            'class' => 'rack-embed__download btn btn-primary'
                        ], 'Download'),
                        new HtmlElement('a', [
                            'href' => route('racks.show', $rack),
                            'class' => 'rack-embed__view btn btn-secondary'
                        ], 'View Details'),
                    ]),
                ]);

            default: // 'default'
                return new HtmlElement('div', ['class' => implode(' ', $classes)], [
                    new HtmlElement('div', ['class' => 'rack-embed__header'], [
                        new HtmlElement(
                            'h4',
                            ['class' => 'rack-embed__title'],
                            htmlspecialchars($rack->title)
                        ),
                        new HtmlElement(
                            'span',
                            ['class' => 'rack-embed__by'],
                            'by ' . htmlspecialchars($rack->user->name)
                        ),
                    ]),
                    new HtmlElement('div', ['class' => 'rack-embed__meta'], [
                        new HtmlElement(
                            'span',
                            ['class' => 'rack-embed__devices'],
                            $rack->device_count . ' devices'
                        ),
                        new HtmlElement(
                            'span',
                            ['class' => 'rack-embed__downloads'],
                            $rack->download_count . ' downloads'
                        ),
                    ]),
                    new HtmlElement('div', ['class' => 'rack-embed__actions'], [
                        new HtmlElement('a', [
                            'href' => route('racks.show', $rack),
                            'class' => 'rack-embed__link'
                        ], 'View Rack →'),
                    ]),
                ]);
        }
    }

    private function renderDeviceList(Rack $rack): string
    {
        if (!$rack->devices || empty($rack->devices)) {
            return '<em>No devices listed</em>';
        }

        $deviceNames = [];
        foreach ($rack->devices as $device) {
            if (isset($device['name'])) {
                $deviceNames[] = htmlspecialchars($device['name']);
            }
        }

        return implode(', ', array_slice($deviceNames, 0, 5)) .
            (count($deviceNames) > 5 ? '... and ' . (count($deviceNames) - 5) . ' more' : '');
    }
}

/**
 * Renderer for Device References
 */
class DeviceReferenceRenderer implements NodeRendererInterface
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function render($node, ChildNodeRendererInterface $childRenderer)
    {
        if (!$node instanceof DeviceReferenceInline) {
            throw new \InvalidArgumentException('Incompatible node type');
        }

        $classes = ['device-ref'];
        $content = htmlspecialchars($node->deviceName);

        if ($node->parameter) {
            $classes[] = 'device-ref--with-param';
            $content .= ' → ' . htmlspecialchars($node->parameter);
        }

        return new HtmlElement('span', [
            'class' => implode(' ', $classes),
            'data-device' => $node->deviceName,
            'data-parameter' => $node->parameter,
            'title' => $node->parameter ?
                "Device: {$node->deviceName}, Parameter: {$node->parameter}" :
                "Device: {$node->deviceName}"
        ], $content);
    }
}

/**
 * Renderer for Parameter Controls
 */
class ParameterControlRenderer implements NodeRendererInterface
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function render($node, ChildNodeRendererInterface $childRenderer)
    {
        if (!$node instanceof ParameterControlInline) {
            throw new \InvalidArgumentException('Incompatible node type');
        }

        $percentage = (($node->value - $node->min) / ($node->max - $node->min)) * 100;
        $controllerId = 'param_' . md5($node->paramName . microtime());

        return new HtmlElement('div', ['class' => 'parameter-control'], [
            new HtmlElement('label', [
                'for' => $controllerId,
                'class' => 'parameter-control__label'
            ], htmlspecialchars($node->paramName)),
            new HtmlElement('div', ['class' => 'parameter-control__slider'], [
                new HtmlElement('input', [
                    'type' => 'range',
                    'id' => $controllerId,
                    'min' => $node->min,
                    'max' => $node->max,
                    'value' => $node->value,
                    'class' => 'parameter-control__input',
                    'data-param' => $node->paramName,
                    'disabled' => 'disabled' // Read-only for now
                ]),
                new HtmlElement('div', [
                    'class' => 'parameter-control__track',
                    'style' => "background: linear-gradient(to right, #3b82f6 {$percentage}%, #e5e7eb {$percentage}%)"
                ]),
            ]),
            new HtmlElement('span', [
                'class' => 'parameter-control__value'
            ], (string)$node->value),
        ]);
    }
}

/**
 * Renderer for Audio Player Blocks
 */
class AudioPlayerRenderer implements NodeRendererInterface
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function render($node, ChildNodeRendererInterface $childRenderer)
    {
        if (!$node instanceof AudioPlayerBlock) {
            throw new \InvalidArgumentException('Incompatible node type');
        }

        // Validate URL for security
        if (!$this->isValidAudioUrl($node->url)) {
            return new HtmlElement(
                'div',
                ['class' => 'audio-player-error'],
                'Invalid audio URL: ' . htmlspecialchars($node->url)
            );
        }

        $playerId = 'audio_' . md5($node->url . microtime());

        return new HtmlElement('div', [
            'class' => 'audio-player',
            'data-audio-player' => $playerId
        ], [
            new HtmlElement('div', ['class' => 'audio-player__header'], [
                new HtmlElement(
                    'h4',
                    ['class' => 'audio-player__title'],
                    htmlspecialchars($node->title)
                ),
            ]),
            new HtmlElement('div', ['class' => 'audio-player__controls'], [
                new HtmlElement('button', [
                    'class' => 'audio-player__play',
                    'data-action' => 'play',
                    'aria-label' => 'Play'
                ], '▶'),
                new HtmlElement('div', ['class' => 'audio-player__progress'], [
                    new HtmlElement('div', ['class' => 'audio-player__track']),
                    new HtmlElement('div', ['class' => 'audio-player__thumb']),
                ]),
                new HtmlElement('span', ['class' => 'audio-player__time'], '0:00'),
            ]),
            new HtmlElement('canvas', [
                'class' => 'audio-player__waveform',
                'width' => '400',
                'height' => '80'
            ]),
            new HtmlElement('audio', [
                'src' => htmlspecialchars($node->url),
                'preload' => 'metadata',
                'class' => 'audio-player__element'
            ]),
        ]);
    }

    private function isValidAudioUrl(string $url): bool
    {
        // Basic URL validation
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Check if it's an allowed audio format
        $allowedExtensions = ['mp3', 'wav', 'ogg', 'aac', 'm4a'];
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return in_array($extension, $allowedExtensions);
    }
}
