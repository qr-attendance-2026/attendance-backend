<?php

namespace App\Jobs;

use App\Models\Student;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class GenerateStudentQrJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public readonly int    $studentId,
        public readonly string $studentCode,
        public readonly string $studentName,
    ) {}

    public function handle(): void
    {
        $student = Student::find($this->studentId);
        if (! $student) return;

        // Skip nếu QR đã tồn tại
        if (! empty($student->qr_code_path)) return;

        $qrData = json_encode([
            'type'         => 'student',
            'student_code' => $this->studentCode,
            'name'         => $this->studentName,
        ], JSON_UNESCAPED_UNICODE);

        $qrSvgString = QrCode::format('svg')
            ->size(300)
            ->encoding('UTF-8')
            ->errorCorrection('H')
            ->generate($qrData);

        $base64Svg = 'data:image/svg+xml;base64,' . base64_encode($qrSvgString);

        $uploadedUrl = Cloudinary::uploadApi()->upload($base64Svg, [
            'folder'    => 'qr/students',
            'public_id' => 'student_' . $this->studentCode,
        ])['secure_url'];

        $student->update(['qr_code_path' => $uploadedUrl]);
    }
}
