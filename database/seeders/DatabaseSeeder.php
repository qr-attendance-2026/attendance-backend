<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

       \App\Models\User::create([
            'name'     => 'Admin Test',
            'email'    => 'admin@test.com',
            'password' => bcrypt('123'),
            'role'     => 'admin',
        ]);
        // Tạo User trước
        $user = \App\Models\User::create([
            'name'     => 'Nguyen Thao Vy',
            'email'    => 'dh52201607@student.stu.edu.vn',
            'password' => bcrypt('123'),
            'role'     => 'student',
        ]);

        // Sau đó tạo Student liên kết với User trên qua user_id
        \App\Models\Student::create([
            'user_id'      => $user->id,
            'student_code' => 'DH52201607',
            'cohort_class' => 'D22_TH01',
            'gender'       => 'Female',
        ]);

        // Tạo một lớp học mẫu
        $class = \App\Models\CourseClass::create([
            'class_name' => 'Lập trình Web nâng cao',
            'room' => 'C.A01'
        ]);

        // Gán sinh viên hiện tại vào lớp này
        $student = \App\Models\Student::first();
        $student->courseClasses()->attach($class->id);


        // Tạo một buổi học cho lớp này
        \App\Models\Attendance::create([
            'student_id' => $student->id, 
            'course_class_id' => $class->id,
            'check_in_time' => now(),
            'status' => 'present'
        ]);
        }

        

}
