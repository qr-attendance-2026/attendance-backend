<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ScheduleImport implements WithMultipleSheets
{
    private ClassesSheetImport $classesSheet;
    private ClassStudentsSheetImport $studentsSheet;

    public function __construct()
    {
        $this->classesSheet  = new ClassesSheetImport();
        $this->studentsSheet = new ClassStudentsSheetImport();
    }

    public function sheets(): array
    {
        return [
            0 => $this->classesSheet,
            1 => $this->studentsSheet,
        ];
    }

    /** Aggregate results from both sheets for the API response. */
    public function results(): array
    {
        return [
            'schedules_created' => $this->classesSheet->results['created'],
            'schedules_skipped' => $this->classesSheet->results['skipped'],
            'students_enrolled' => $this->studentsSheet->results['enrolled'],
            'students_skipped'  => $this->studentsSheet->results['skipped'],
            'errors'            => array_merge(
                $this->classesSheet->results['errors'],
                $this->studentsSheet->results['errors']
            ),
        ];
    }
}
