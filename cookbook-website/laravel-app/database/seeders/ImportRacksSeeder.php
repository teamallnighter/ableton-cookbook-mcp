<?php

namespace Database\Seeders;

use App\Models\Rack;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportRacksSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Importing existing racks data...');
        
        // Create test users first
        $users = $this->createTestUsers();
        
        // Load the existing JSON data
        $jsonPath = '/Volumes/BassDaddy/projects/abletonCookbookPHP/racks/db.json';
        $racksPath = '/Volumes/BassDaddy/projects/abletonCookbookPHP/racks';
        
        if (!file_exists($jsonPath)) {
            $this->command->error("JSON file not found: {$jsonPath}");
            return;
        }
        
        $data = json_decode(file_get_contents($jsonPath), true);
        
        if (!isset($data['racks'])) {
            $this->command->error("No 'racks' key found in JSON data");
            return;
        }
        
        $count = 0;
        foreach ($data['racks'] as $id => $rackData) {
            try {
                $rack = $this->importRack($rackData, $racksPath, $users);
                if ($rack) {
                    $count++;
                    $this->command->info("Imported: {$rack->title}");
                }
            } catch (\Exception $e) {
                $this->command->warn("Failed to import rack {$id}: " . $e->getMessage());
            }
        }
        
        $this->command->info("Successfully imported {$count} racks!");
    }
    
    private function createTestUsers(): array
    {
        $users = [];
        
        // Create main user (bassdaddy)
        $users['bassdaddy'] = User::create([
            'name' => 'Bass Daddy',
            'email' => 'bassdaddy@abletoncookbook.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $users['bassdaddy']->assignRole('admin');
        
        // Create additional test users
        $testUsers = [
            'producer1' => ['Electronic Producer', 'producer1@example.com'],
            'beatmaker' => ['Beat Maker', 'beatmaker@example.com'],
            'synthguru' => ['Synth Guru', 'synthguru@example.com'],
            'mixmaster' => ['Mix Master', 'mixmaster@example.com'],
        ];
        
        foreach ($testUsers as $username => $userData) {
            $users[$username] = User::create([
                'name' => $userData[0],
                'email' => $userData[1],
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
            $users[$username]->assignRole('user');
        }
        
        return $users;
    }
    
    private function importRack(array $rackData, string $racksPath, array $users): ?Rack
    {
        // Get the .adg file path
        $adgFile = $racksPath . '/' . $rackData['original_filename'];
        
        if (!file_exists($adgFile)) {
            $this->command->warn("ADG file not found: {$adgFile}");
            return null;
        }
        
        // Copy file to Laravel storage
        $fileName = Str::uuid() . '.adg';
        $storagePath = "racks/{$fileName}";
        
        // Copy to private storage
        Storage::disk('private')->put($storagePath, file_get_contents($adgFile));
        
        // Get file info
        $fileSize = filesize($adgFile);
        $fileHash = hash_file('sha256', $adgFile);
        
        // Select random user or use specified user
        $username = $rackData['user'] ?? 'bassdaddy';
        $user = $users[$username] ?? $users['bassdaddy'];
        
        // Create rack record
        $rack = Rack::create([
            'uuid' => Str::uuid(),
            'user_id' => $user->id,
            'title' => $rackData['display_title'] ?? pathinfo($rackData['original_filename'], PATHINFO_FILENAME),
            'description' => $this->generateDescription($rackData),
            'slug' => $this->generateUniqueSlug($rackData['display_title'] ?? $rackData['original_filename']),
            'file_path' => $storagePath,
            'file_hash' => $fileHash,
            'file_size' => $fileSize,
            'original_filename' => $rackData['original_filename'],
            'rack_type' => $rackData['raw_rack_type'] ?? 'AudioEffectGroupDevice',
            'device_count' => rand(2, 8), // Mock device count
            'chain_count' => rand(1, 4), // Mock chain count
            'ableton_version' => '11.' . rand(0, 3) . '.' . rand(0, 5),
            'status' => 'approved',
            'published_at' => now()->subDays(rand(0, 365)),
            'is_public' => true,
            'views_count' => rand(50, 2000),
            'downloads_count' => rand(10, 500),
            'average_rating' => rand(35, 50) / 10, // 3.5 to 5.0
            'ratings_count' => rand(1, 25),
        ]);
        
        // Add tags
        $this->addTags($rack, $rackData);
        
        return $rack;
    }
    
    private function generateDescription(array $rackData): string
    {
        $descriptions = [
            "A powerful effect chain perfect for modern electronic music production.",
            "Professional-grade processing with vintage analog character.",
            "Carefully crafted rack for adding depth and movement to your tracks.",
            "Essential tool for producers looking to add that special something.",
            "Warm, musical processing that enhances any audio source.",
            "Creative effect combination that adds unique character.",
            "Studio-quality processing chain for professional results.",
        ];
        
        $baseDescription = $descriptions[array_rand($descriptions)];
        
        // Add specific info if available
        if (isset($rackData['category'])) {
            $baseDescription .= " Category: " . $rackData['category'] . ".";
        }
        
        return $baseDescription;
    }
    
    private function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
        $count = 1;
        
        while (Rack::where('slug', $slug)->exists()) {
            $slug = Str::slug($title) . '-' . $count;
            $count++;
        }
        
        return $slug;
    }
    
    private function addTags(Rack $rack, array $rackData): void
    {
        $tags = [];
        
        // Add existing tags from data
        if (isset($rackData['tags']) && is_array($rackData['tags'])) {
            $tags = array_merge($tags, $rackData['tags']);
        }
        
        // Add category as tag
        if (isset($rackData['category'])) {
            $tags[] = strtolower($rackData['category']);
        }
        
        // Add rack type as tag
        if (isset($rackData['rack_type'])) {
            $tags[] = strtolower(str_replace(' ', '-', $rackData['rack_type']));
        }
        
        // Add some generic tags based on filename
        $filename = strtolower($rackData['display_title'] ?? '');
        $genericTags = [
            'bass' => ['bass', 'low-end', 'sub'],
            'drum' => ['drums', 'percussion', 'rhythm'],
            'vocal' => ['vocals', 'voice', 'singing'],
            'delay' => ['delay', 'echo', 'time'],
            'reverb' => ['reverb', 'space', 'room'],
            'filter' => ['filter', 'sweep', 'cutoff'],
            'distortion' => ['distortion', 'overdrive', 'saturation'],
            'chorus' => ['chorus', 'modulation', 'width'],
        ];
        
        foreach ($genericTags as $keyword => $keywordTags) {
            if (str_contains($filename, $keyword)) {
                $tags[] = $keywordTags[array_rand($keywordTags)];
                break;
            }
        }
        
        // Remove duplicates and create tags
        $tags = array_unique($tags);
        $tagIds = [];
        
        foreach ($tags as $tagName) {
            if (empty(trim($tagName))) continue;
            
            $tag = Tag::firstOrCreate(
                ['slug' => Str::slug($tagName)],
                ['name' => $tagName, 'usage_count' => 0]
            );
            
            $tagIds[] = $tag->id;
            $tag->increment('usage_count');
        }
        
        if (!empty($tagIds)) {
            $rack->tags()->sync($tagIds);
        }
    }
}