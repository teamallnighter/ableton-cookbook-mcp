<?php

namespace App\Services;

use App\Models\LearningPath;
use App\Models\User;
use App\Models\UserProgress;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Service for generating and managing learning path certificates
 */
class CertificateService
{
    protected string $certificatesDisk = 'private';
    protected string $certificatesPath = 'certificates';
    protected string $templatesPath = 'certificate-templates';

    /**
     * Generate certificate for user
     */
    public function generateCertificate(LearningPath $learningPath, User $user): ?array
    {
        $progress = $learningPath->getUserProgress($user);

        if (!$progress || !$progress->certificate_earned) {
            return null;
        }

        $certificateData = [
            'certificate_id' => $progress->certificate_id,
            'user_name' => $user->name,
            'path_title' => $learningPath->title,
            'completion_date' => $progress->completed_at->format('Y-m-d'),
            'issue_date' => $progress->certificate_issued_at->format('Y-m-d'),
            'final_score' => $progress->best_score,
            'time_spent' => $progress->time_spent,
            'instructor_name' => $learningPath->user->name,
        ];

        // Generate PDF certificate
        $pdfPath = $this->generatePdfCertificate($learningPath, $certificateData);
        
        // Generate verification URL
        $verificationUrl = $this->generateVerificationUrl($progress->certificate_id);

        return [
            'certificate_id' => $progress->certificate_id,
            'pdf_path' => $pdfPath,
            'verification_url' => $verificationUrl,
            'certificate_data' => $certificateData,
        ];
    }

    /**
     * Generate PDF certificate
     */
    protected function generatePdfCertificate(LearningPath $learningPath, array $data): string
    {
        // Use certificate template if specified, otherwise use default
        $templateName = $learningPath->certificate_template ?? 'default';
        $template = $this->getCertificateTemplate($templateName);

        // Replace placeholders with actual data
        $html = $this->replacePlaceholders($template, $data);

        // Generate PDF (you would use a proper PDF library like DomPDF or Snappy)
        $pdf = $this->generatePdfFromHtml($html);

        // Save to storage
        $fileName = "certificate_{$data['certificate_id']}.pdf";
        $filePath = $this->certificatesPath . '/' . $fileName;

        Storage::disk($this->certificatesDisk)->put($filePath, $pdf);

        return $filePath;
    }

    /**
     * Get certificate template
     */
    protected function getCertificateTemplate(string $templateName): string
    {
        $templatePath = $this->templatesPath . '/' . $templateName . '.html';

        if (Storage::disk($this->certificatesDisk)->exists($templatePath)) {
            return Storage::disk($this->certificatesDisk)->get($templatePath);
        }

        // Return default template if specific template not found
        return $this->getDefaultTemplate();
    }

    /**
     * Get default certificate template
     */
    protected function getDefaultTemplate(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Certificate of Completion</title>
    <style>
        body {
            font-family: 'Georgia', serif;
            margin: 0;
            padding: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
        }
        .certificate {
            background: white;
            padding: 60px;
            margin: 0 auto;
            max-width: 800px;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
        }
        .header {
            border-bottom: 3px solid #667eea;
            padding-bottom: 30px;
            margin-bottom: 40px;
        }
        .title {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 10px;
            font-weight: bold;
        }
        .subtitle {
            font-size: 18px;
            color: #666;
            font-style: italic;
        }
        .content {
            margin: 40px 0;
            line-height: 2;
        }
        .recipient-name {
            font-size: 36px;
            color: #333;
            font-weight: bold;
            border-bottom: 2px solid #667eea;
            display: inline-block;
            padding-bottom: 10px;
            margin: 20px 0;
        }
        .course-title {
            font-size: 24px;
            color: #764ba2;
            font-style: italic;
            margin: 30px 0;
        }
        .details {
            display: flex;
            justify-content: space-between;
            margin-top: 60px;
            padding-top: 30px;
            border-top: 1px solid #eee;
        }
        .signature-block {
            text-align: center;
            flex: 1;
        }
        .signature-line {
            border-bottom: 2px solid #333;
            width: 200px;
            margin: 20px auto;
        }
        .verification {
            margin-top: 40px;
            font-size: 12px;
            color: #888;
        }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="header">
            <div class="title">Certificate of Completion</div>
            <div class="subtitle">Ableton Cookbook Learning Platform</div>
        </div>
        
        <div class="content">
            <p style="font-size: 20px;">This is to certify that</p>
            
            <div class="recipient-name">{{USER_NAME}}</div>
            
            <p style="font-size: 18px;">has successfully completed the learning path</p>
            
            <div class="course-title">{{PATH_TITLE}}</div>
            
            <p style="font-size: 16px;">with a final score of <strong>{{FINAL_SCORE}}%</strong></p>
            <p style="font-size: 16px;">Total time invested: <strong>{{TIME_SPENT}} hours</strong></p>
        </div>
        
        <div class="details">
            <div class="signature-block">
                <div class="signature-line"></div>
                <p><strong>{{INSTRUCTOR_NAME}}</strong></p>
                <p>Course Instructor</p>
            </div>
            
            <div class="signature-block">
                <div class="signature-line"></div>
                <p><strong>{{COMPLETION_DATE}}</strong></p>
                <p>Date of Completion</p>
            </div>
        </div>
        
        <div class="verification">
            <p>Certificate ID: {{CERTIFICATE_ID}}</p>
            <p>Verify authenticity at: abletonc ookbook.com/verify/{{CERTIFICATE_ID}}</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Replace placeholders in template
     */
    protected function replacePlaceholders(string $template, array $data): string
    {
        $placeholders = [
            '{{USER_NAME}}' => $data['user_name'],
            '{{PATH_TITLE}}' => $data['path_title'],
            '{{COMPLETION_DATE}}' => $data['completion_date'],
            '{{ISSUE_DATE}}' => $data['issue_date'],
            '{{FINAL_SCORE}}' => number_format($data['final_score'], 1),
            '{{TIME_SPENT}}' => number_format($data['time_spent'], 1),
            '{{INSTRUCTOR_NAME}}' => $data['instructor_name'],
            '{{CERTIFICATE_ID}}' => $data['certificate_id'],
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }

    /**
     * Generate PDF from HTML (placeholder - would use actual PDF library)
     */
    protected function generatePdfFromHtml(string $html): string
    {
        // In a real implementation, you would use DomPDF, Snappy, or similar
        // For now, this is a placeholder that would return the actual PDF binary
        
        // Example with DomPDF:
        // $dompdf = new \Dompdf\Dompdf();
        // $dompdf->loadHtml($html);
        // $dompdf->setPaper('A4', 'landscape');
        // $dompdf->render();
        // return $dompdf->output();
        
        return $html; // Placeholder
    }

    /**
     * Generate verification URL
     */
    protected function generateVerificationUrl(string $certificateId): string
    {
        return route('certificates.verify', ['id' => $certificateId]);
    }

    /**
     * Verify certificate authenticity
     */
    public function verifyCertificate(string $certificateId): ?array
    {
        $progress = UserProgress::where('certificate_id', $certificateId)
            ->where('certificate_earned', true)
            ->with(['user:id,name', 'progressable'])
            ->first();

        if (!$progress) {
            return null;
        }

        return [
            'valid' => true,
            'certificate_id' => $certificateId,
            'user_name' => $progress->user->name,
            'path_title' => $progress->progressable->title,
            'completion_date' => $progress->completed_at->format('Y-m-d'),
            'issue_date' => $progress->certificate_issued_at->format('Y-m-d'),
            'final_score' => $progress->best_score,
        ];
    }

    /**
     * Get user's certificates
     */
    public function getUserCertificates(User $user): array
    {
        $certificates = UserProgress::where('user_id', $user->id)
            ->where('certificate_earned', true)
            ->with('progressable:id,title,path_type')
            ->orderBy('certificate_issued_at', 'desc')
            ->get();

        return $certificates->map(function ($progress) {
            return [
                'certificate_id' => $progress->certificate_id,
                'path_title' => $progress->progressable->title,
                'path_type' => $progress->progressable->path_type,
                'completion_date' => $progress->completed_at->format('Y-m-d'),
                'issue_date' => $progress->certificate_issued_at->format('Y-m-d'),
                'final_score' => $progress->best_score,
                'verification_url' => $this->generateVerificationUrl($progress->certificate_id),
                'download_url' => route('certificates.download', ['id' => $progress->certificate_id]),
            ];
        })->toArray();
    }

    /**
     * Download certificate PDF
     */
    public function downloadCertificate(string $certificateId): ?\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $progress = UserProgress::where('certificate_id', $certificateId)
            ->where('certificate_earned', true)
            ->first();

        if (!$progress) {
            return null;
        }

        $certificatePath = $this->certificatesPath . "/certificate_{$certificateId}.pdf";

        if (!Storage::disk($this->certificatesDisk)->exists($certificatePath)) {
            // Regenerate certificate if it doesn't exist
            $learningPath = $progress->progressable;
            $this->generateCertificate($learningPath, $progress->user);
        }

        return Storage::disk($this->certificatesDisk)->download(
            $certificatePath,
            "certificate_{$progress->progressable->title}_{$certificateId}.pdf"
        );
    }

    /**
     * Get certificate statistics
     */
    public function getCertificateStatistics(): array
    {
        $totalCertificates = UserProgress::where('certificate_earned', true)->count();
        
        $certificatesByPath = UserProgress::where('certificate_earned', true)
            ->with('progressable:id,title')
            ->get()
            ->groupBy('progressable.title')
            ->map->count()
            ->sortDesc();

        $recentCertificates = UserProgress::where('certificate_earned', true)
            ->with(['user:id,name', 'progressable:id,title'])
            ->orderBy('certificate_issued_at', 'desc')
            ->limit(10)
            ->get();

        return [
            'total_certificates' => $totalCertificates,
            'certificates_this_month' => UserProgress::where('certificate_earned', true)
                ->where('certificate_issued_at', '>=', now()->startOfMonth())
                ->count(),
            'certificates_by_path' => $certificatesByPath->toArray(),
            'recent_certificates' => $recentCertificates->map(function ($progress) {
                return [
                    'user_name' => $progress->user->name,
                    'path_title' => $progress->progressable->title,
                    'issued_at' => $progress->certificate_issued_at->format('Y-m-d H:i'),
                ];
            })->toArray(),
        ];
    }

    /**
     * Revoke certificate (in case of policy violations)
     */
    public function revokeCertificate(string $certificateId, string $reason = ''): bool
    {
        $progress = UserProgress::where('certificate_id', $certificateId)
            ->where('certificate_earned', true)
            ->first();

        if (!$progress) {
            return false;
        }

        // Mark as revoked
        $progress->update([
            'certificate_earned' => false,
            'certificate_data' => array_merge($progress->certificate_data ?? [], [
                'revoked' => true,
                'revoked_at' => now()->toISOString(),
                'revoked_reason' => $reason,
            ]),
        ]);

        // Delete certificate file if it exists
        $certificatePath = $this->certificatesPath . "/certificate_{$certificateId}.pdf";
        if (Storage::disk($this->certificatesDisk)->exists($certificatePath)) {
            Storage::disk($this->certificatesDisk)->delete($certificatePath);
        }

        return true;
    }

    /**
     * Bulk generate certificates for learning path completions
     */
    public function bulkGenerateCertificates(LearningPath $learningPath): int
    {
        if (!$learningPath->has_certificate) {
            return 0;
        }

        $eligibleProgress = UserProgress::where('progressable_type', LearningPath::class)
            ->where('progressable_id', $learningPath->id)
            ->where('status', 'completed')
            ->where('passed', true)
            ->where('certificate_earned', false)
            ->with('user')
            ->get();

        $generated = 0;

        foreach ($eligibleProgress as $progress) {
            $certificateId = 'CERT-' . strtoupper(Str::random(12));
            
            $progress->update([
                'certificate_earned' => true,
                'certificate_issued_at' => now(),
                'certificate_id' => $certificateId,
            ]);

            $this->generateCertificate($learningPath, $progress->user);
            $generated++;
        }

        return $generated;
    }
}