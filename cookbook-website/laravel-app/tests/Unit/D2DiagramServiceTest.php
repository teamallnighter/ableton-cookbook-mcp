<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\D2DiagramService;
use App\Services\RackProcessingService;
use App\Models\Rack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class D2DiagramServiceTest extends TestCase
{
    use RefreshDatabase;

    protected D2DiagramService $d2Service;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->d2Service = app(D2DiagramService::class);
    }

    /** @test */
    public function it_can_generate_drum_rack_diagram()
    {
        // Create test drum rack data
        $drumRackData = [
            'uuid' => 'test-drum-rack-uuid',
            'title' => 'Test 808 Kit',
            'rack_type' => 'drum_rack',
            'devices' => [
                ['name' => 'Kick', 'type' => 'synthesizer', 'midi_note' => 36],
                ['name' => 'Snare', 'type' => 'sampler', 'midi_note' => 38],
                ['name' => 'Hi-Hat', 'type' => 'synthesizer', 'midi_note' => 42]
            ],
            'chains' => [
                ['name' => 'Kick Chain', 'devices' => [['name' => 'Kick', 'type' => 'synthesizer']]],
                ['name' => 'Snare Chain', 'devices' => [['name' => 'Snare', 'type' => 'sampler']]]
            ]
        ];

        $result = $this->d2Service->generateDrumRackDiagram($drumRackData, [
            'style' => 'sketch',
            'format' => 'd2',
            'include_tooltips' => true
        ]);

        $this->assertNotNull($result);
        $this->assertStringContainsString('drum_rack:', $result);
        $this->assertStringContainsString('Test 808 Kit', $result);
        $this->assertStringContainsString('C1 (Kick)', $result);
        $this->assertStringContainsString('D1 (Snare)', $result);
    }

    /** @test */
    public function it_can_generate_general_rack_diagram()
    {
        // Create test general rack data
        $rackData = [
            'uuid' => 'test-general-rack-uuid',
            'title' => 'Bass Processing Rack',
            'rack_type' => 'AudioEffectGroupDevice',
            'devices' => [
                ['name' => 'EQ Eight', 'type' => 'audio_effect'],
                ['name' => 'Compressor', 'type' => 'audio_effect'],
                ['name' => 'Saturator', 'type' => 'audio_effect']
            ],
            'chains' => [
                [
                    'name' => 'Main Chain',
                    'devices' => [
                        ['name' => 'EQ Eight', 'type' => 'audio_effect'],
                        ['name' => 'Compressor', 'type' => 'audio_effect'],
                        ['name' => 'Saturator', 'type' => 'audio_effect']
                    ]
                ]
            ]
        ];

        $result = $this->d2Service->generateRackDiagram($rackData, [
            'style' => 'technical',
            'format' => 'd2',
            'include_tooltips' => true
        ]);

        $this->assertNotNull($result);
        $this->assertStringContainsString('rack:', $result);
        $this->assertStringContainsString('Bass Processing Rack', $result);
        $this->assertStringContainsString('EQ Eight', $result);
        $this->assertStringContainsString('Compressor', $result);
        $this->assertStringContainsString('Saturator', $result);
    }

    /** @test */
    public function it_can_handle_different_diagram_styles()
    {
        $rackData = [
            'uuid' => 'test-uuid',
            'title' => 'Style Test Rack',
            'rack_type' => 'AudioEffectGroupDevice',
            'devices' => [['name' => 'Test Device', 'type' => 'audio_effect']],
            'chains' => [['name' => 'Test Chain', 'devices' => [['name' => 'Test Device']]]]
        ];

        $styles = ['sketch', 'technical', 'minimal', 'neon'];

        foreach ($styles as $style) {
            $result = $this->d2Service->generateRackDiagram($rackData, [
                'style' => $style,
                'format' => 'd2'
            ]);

            $this->assertNotNull($result, "Failed to generate diagram for style: {$style}");
            $this->assertStringContainsString('rack:', $result);
        }
    }

    /** @test */
    public function it_includes_tooltips_when_requested()
    {
        $rackData = [
            'uuid' => 'test-tooltip-uuid',
            'title' => 'Tooltip Test Rack',
            'rack_type' => 'drum_rack',
            'devices' => [
                ['name' => 'Kick', 'type' => 'synthesizer', 'midi_note' => 36]
            ]
        ];

        $result = $this->d2Service->generateDrumRackDiagram($rackData, [
            'style' => 'sketch',
            'format' => 'd2',
            'include_tooltips' => true
        ]);

        $this->assertStringContainsString('tooltip:', $result);
        $this->assertStringContainsString('MIDI Note: 36', $result);
        $this->assertStringContainsString('Device Type:', $result);
    }

    /** @test */
    public function it_handles_empty_rack_data_gracefully()
    {
        $emptyRackData = [
            'uuid' => 'empty-test-uuid',
            'title' => 'Empty Rack',
            'rack_type' => 'AudioEffectGroupDevice',
            'devices' => [],
            'chains' => []
        ];

        $result = $this->d2Service->generateRackDiagram($emptyRackData, [
            'style' => 'minimal',
            'format' => 'd2'
        ]);

        $this->assertNotNull($result);
        $this->assertStringContainsString('Empty Rack', $result);
        $this->assertStringContainsString('No devices', $result);
    }
}