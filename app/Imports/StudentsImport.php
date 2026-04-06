<?php

namespace App\Imports;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
 

class StudentsImport implements ToCollection, WithHeadingRow, WithValidation
{
    public array $results = ['created' => 0, 'skipped' => 0, 'errors' => []];
 
    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            try {
                DB::transaction(function () use ($row) {
 
                    // Skip if email already exists
                    if (User::where('email', $row['email'])->exists()) {
                        $this->results['skipped']++;
                        return;
                    }

                    //Xử lý format dữ liệu từ file excel
                    $genderMap = [
                        'nam' => 'male',
                        'nữ'  => 'female',
                    ];
                    $rawGender = mb_strtolower(trim($row['gender']));
                    $dbGender = $genderMap[$rawGender] ?? 'other';

                    $dob = null;
                    if (isset($row['date_of_birth'])) {
     
                        if (is_numeric($row['date_of_birth'])) {
                            $dob = Date::excelToDateTimeObject($row['date_of_birth'])->format('Y-m-d');
                        } else {
                            $dob = date('Y-m-d', strtotime(str_replace('/', '-', $row['date_of_birth'])));
                        }
                    }

                    $user = User::create([
                        'name'=>$row['name'],
                        'email' => $row['email'],
                        'password' => Hash::make($row['student_code']),
                        'role' => 'student',
                    ]);
                    
                    $student = Student::create([
                        'user_id' => $user->id,
                        'student_code' => $row['student_code'],
                        'name' => $row['name'],
                        'cohort_class' => $row['cohort_class'] ?? null,
                        'date_of_birth' => $dob,
                        'gender' => $dbGender,
                        'phone_number' => $row['phone_number']?? null,
                    ]);
                
                    $qrData= json_encode([
                        'type'=> 'student',
                        'student_code'=> $student->student_code,
                        'name'=> $user->name,
                    ]);

                    $qrSvgString = QrCode::format('svg')
                        ->size(300)
                        ->errorCorrection('H')
                        ->generate($qrData); 

                    //Mã hóa chuỗi SVG thành định dạng Data URI (Base64) u
                    $base64Svg = "data:image/svg+xml;base64," . base64_encode($qrSvgString);

                    //Upload Cloudinary
                    $uploadedFileUrl = Cloudinary::uploadApi()->upload($base64Svg, [
                        'folder' => 'qr/students', 
                        'public_id' => 'student_' . $student->student_code 
                    ])['secure_url']; //(https)

                    $student->update(['qr_code_path' => $uploadedFileUrl]);
 
                    $this->results['created']++;

                });
                
            } catch (\Throwable $e) {
                $this->results['errors'][] = [
                    'row' => $index + 2, // +1 for 0-based index, +1 for header
                    'email'  => $row['email'] ?? '?',
                    'reason' => $e->getMessage(),

                ];
            }
        }
    }

    public function rules(): array
    {
        return [
            '*.student_code' => ['required', 'string'],
            '*.name'         => ['required', 'string'],
            '*.email'        => ['required', 'email'],
        ];
    }


}
