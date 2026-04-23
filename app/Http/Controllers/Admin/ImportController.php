<?php

namespace App\Http\Controllers\Admin;

use App\Imports\StudentsImport;
use App\Imports\TeachersImport;
use App\Imports\ScheduleImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ImportController
{
    public function students(Request $request): JsonResponse
    {
        set_time_limit(300); // Limit import cho bulk

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);
 
        try {

        $import = new StudentsImport();
        Excel::import($import, $request->file('file')); //đọc file excel

        return response()->json([
            'success' => true,
            'message' => "Import thành công: {$import->results['created']} sinh viên mới, {$import->results['skipped']} bỏ qua.",
            'data'    => $import->results,
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => 'Import thất bại: ' . $e->getMessage(),
        ], 500);
    }
    }


    public function teachers(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $import = new TeachersImport();
        Excel::import($import, $request->file('file'));

        return response()->json([
            'success' => true,
            'message' => 'Import thành công.',
            'data'    => $import->results,
        ], 200);
    }

    public function schedule(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $import = new ScheduleImport();
        Excel::import($import, $request->file('file'));

        return response()->json([
            'success' => true,
            'message' => 'Import thành công.',
            'data'    => $import->results(),
        ], 200);
    }
}
