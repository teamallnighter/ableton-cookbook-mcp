<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class LegalController extends Controller
{
    /**
     * Display Terms of Service page
     */
    public function terms()
    {
        $terms = $this->getMarkdownContent('terms');
        
        return view('legal.terms', [
            'terms' => $terms,
            'title' => 'Terms of Service - Ableton Cookbook',
            'description' => 'Terms of Service for Ableton Cookbook platform. Review our user agreement and community guidelines.',
        ]);
    }

    /**
     * Display Privacy Policy page
     */
    public function privacy()
    {
        $privacy = $this->getMarkdownContent('privacy');
        
        return view('legal.privacy', [
            'privacy' => $privacy,
            'title' => 'Privacy Policy - Ableton Cookbook',
            'description' => 'Privacy Policy for Ableton Cookbook platform. Learn how we protect and handle your personal information in compliance with PIPEDA.',
        ]);
    }

    /**
     * Display Copyright and DMCA Policy page
     */
    public function copyright()
    {
        $copyright = $this->getMarkdownContent('copyright');
        
        return view('legal.copyright', [
            'copyright' => $copyright,
            'title' => 'Copyright & DMCA Policy - Ableton Cookbook',
            'description' => 'Copyright Notice and DMCA Policy for Ableton Cookbook platform. Information about intellectual property rights and takedown procedures.',
        ]);
    }

    /**
     * Display Cookie Policy page
     */
    public function cookies()
    {
        $cookies = $this->getMarkdownContent('cookies', $this->getDefaultCookiePolicy());
        
        return view('legal.cookies', [
            'cookies' => $cookies,
            'title' => 'Cookie Policy - Ableton Cookbook',
            'description' => 'Cookie Policy for Ableton Cookbook platform. Learn how we use cookies and tracking technologies.',
        ]);
    }

    /**
     * Display Legal Overview page with links to all policies
     */
    public function index()
    {
        return view('legal.index', [
            'title' => 'Legal Information - Ableton Cookbook',
            'description' => 'Legal information and policies for Ableton Cookbook platform including Terms of Service, Privacy Policy, and Copyright information.',
        ]);
    }

    /**
     * Get markdown content and convert to HTML
     */
    private function getMarkdownContent(string $filename, string $fallback = null): string
    {
        $path = resource_path("markdown/{$filename}.md");
        
        if (File::exists($path)) {
            $markdown = File::get($path);
            return Str::markdown($markdown, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);
        }
        
        return $fallback ?? "<h1>Legal Document</h1><p>Legal document content is currently being updated. Please check back soon.</p>";
    }

    /**
     * Default cookie policy if markdown file doesn't exist
     */
    private function getDefaultCookiePolicy(): string
    {
        return "# Cookie Policy

**Last Updated: August 26, 2025**

## What Are Cookies

Cookies are small text files that are stored on your device when you visit our website. They help us provide you with a better experience by remembering your preferences and enabling certain functionality.

## Types of Cookies We Use

### Essential Cookies
These cookies are necessary for the website to function properly:
- Authentication cookies to keep you logged in
- Session cookies for security and functionality
- CSRF protection tokens

### Performance Cookies
These cookies help us understand how visitors interact with our website:
- Google Analytics for usage statistics
- Performance monitoring for site optimization

### Functional Cookies
These cookies remember your preferences:
- Language preferences
- Display settings
- User interface preferences

## Managing Cookies

You can control cookies through your browser settings. Note that disabling certain cookies may affect website functionality.

## Contact Us

For questions about our cookie policy, contact us at:

Christopher Connelly  
10 Albion Street  
Sackville, New Brunswick, Canada E4L 1G6  
Email: [contact email]";
    }
}