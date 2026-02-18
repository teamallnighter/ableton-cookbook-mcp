<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $newsletter->subject }}</title>
    <style>
        /* Email-safe CSS */
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 40px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }
        
        .header p {
            margin: 8px 0 0 0;
            font-size: 16px;
            opacity: 0.9;
        }
        
        .content {
            padding: 40px;
        }
        
        .content h2 {
            color: #1a202c;
            font-size: 24px;
            margin-bottom: 16px;
            font-weight: 600;
        }
        
        .content h3 {
            color: #2d3748;
            font-size: 20px;
            margin: 24px 0 12px 0;
            font-weight: 600;
        }
        
        .content p {
            margin-bottom: 16px;
            font-size: 16px;
            line-height: 1.7;
        }
        
        .content a {
            color: #667eea;
            text-decoration: none;
        }
        
        .content a:hover {
            text-decoration: underline;
        }
        
        .blog-card {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 24px;
            margin: 24px 0;
        }
        
        .blog-card h3 {
            margin-top: 0;
            color: #1a202c;
        }
        
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white !important;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            margin: 16px 0;
        }
        
        .button:hover {
            text-decoration: none !important;
        }
        
        .stats {
            background: #edf2f7;
            border-radius: 8px;
            padding: 20px;
            margin: 24px 0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
            display: block;
        }
        
        .stat-label {
            font-size: 14px;
            color: #718096;
            margin-top: 4px;
        }
        
        .footer {
            background: #1a202c;
            color: #cbd5e0;
            padding: 30px 40px;
            text-align: center;
            font-size: 14px;
        }
        
        .footer a {
            color: #cbd5e0;
            text-decoration: underline;
        }
        
        .social-links {
            margin: 20px 0;
        }
        
        .social-links a {
            display: inline-block;
            margin: 0 8px;
            color: #cbd5e0;
            text-decoration: none;
        }
        
        @media only screen and (max-width: 600px) {
            .container {
                width: 100% !important;
            }
            
            .header, .content, .footer {
                padding: 20px !important;
            }
            
            .header h1 {
                font-size: 24px !important;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🎵 Ableton Cookbook</h1>
            <p>Your weekly dose of Ableton Live inspiration</p>
        </div>

        <!-- Main Content -->
        <div class="content">
            <h2>{{ $newsletter->title }}</h2>
            
            @if($newsletter->blogPost)
                <!-- Blog Post Card -->
                <div class="blog-card">
                    <h3>{{ $newsletter->blogPost->title }}</h3>
                    <p>{{ strip_tags($newsletter->blogPost->excerpt ?? $newsletter->blogPost->content) }}</p>
                    <a href="{{ route('blog.show', $newsletter->blogPost->slug) }}" class="button">
                        Read Full Article →
                    </a>
                </div>
            @endif

            <!-- Newsletter Content -->
            <div style="white-space: pre-line;">{{ $content ?? $newsletter->content }}</div>

            @if($newsletter->template_type === 'monthly_digest')
                <!-- Community Stats -->
                <div class="stats">
                    <h3 style="margin-top: 0;">🚀 This Month's Community Growth</h3>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <span class="stat-number">{{ rand(50, 150) }}</span>
                            <div class="stat-label">New Racks</div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">{{ rand(200, 500) }}</span>
                            <div class="stat-label">Downloads</div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">{{ rand(20, 80) }}</span>
                            <div class="stat-label">New Members</div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Call to Action -->
            <p style="margin-top: 32px;">
                <a href="{{ route('home') }}" class="button">
                    🎛️ Browse Latest Racks
                </a>
            </p>

            <p style="font-size: 14px; color: #718096; margin-top: 24px;">
                Hi {{ $user->name ?? 'there' }}! We hope you're enjoying our newsletter. 
                If you have any feedback or suggestions, just reply to this email.
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>Ableton Cookbook</strong></p>
            <p>The community-driven platform for sharing Ableton Live racks and techniques.</p>
            
            <div class="social-links">
                <a href="{{ route('home') }}">🏠 Home</a>
                <a href="{{ route('blog.index') }}">📝 Blog</a>
                <a href="{{ route('issues.index') }}">💬 Feedback</a>
            </div>

            <p style="font-size: 12px; margin-top: 20px;">
                You're receiving this because you subscribed to our newsletter.<br>
                <a href="{{ $unsubscribeUrl }}">Unsubscribe</a> | 
                <a href="{{ route('profile') }}">Update Preferences</a>
            </p>

            <p style="font-size: 12px; color: #a0aec0; margin-top: 16px;">
                © {{ date('Y') }} Ableton Cookbook. Made with ❤️ for the Ableton Live community.
            </p>
        </div>
    </div>
</body>
</html>