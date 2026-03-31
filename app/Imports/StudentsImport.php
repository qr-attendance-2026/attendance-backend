<?php

namespace App\Imports;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use PhpOffice\PhpSpreadsheet\Shared\Date;

 

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
                        'Nam' => 'male',
                        'Nữ'  => 'female',
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

                    //Tao tai khoan sinh vien
                    $user = User::create([
                        'name'=>$row['name'],
                        'email' => $row['email'],
                        'password' => Hash::make($row['student_code']),
                        'role' => 'student',
                    ]);
                    
                    //Tao thong tin sinh vien
                    $student = Student::create([
                        'user_id' => $user->id,
                        'student_code' => $row['student_code'],
                        'name' => $row['name'],
                        'cohort_class' => $row['cohort_class'] ?? null,
                        'date_of_birth' => $dob,
                        'gender' => $dbGender,
                        'phone_number' => $row['phone_number']?? null,
                    ]);
                
                    //Generate personal QR code
                    $qrData= json_encode([
                        'type'=> 'student',
                        'student_code'=> $student->student_code,
                        'name'=> $user->name,
                    ]);

                    $filename = 'student_'.$student->student_code.'.svg';
                    $path = 'qr/students/' . $filename;
                    
                    $directory = storage_path('app/public/qr/students');
                    if (!file_exists($directory)) {
                        mkdir($directory, 0755, true);
                    }
                    
                    QrCode::format('svg')
                        ->size(300)
                        ->errorCorrection('H')
                        ->generate($qrData, storage_path('app/public/' . $path)); //lưu local, sau này có thể đổi sang s3
 
                    $student->update(['qr_code_path' => $path]);
 
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
