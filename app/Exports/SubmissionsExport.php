<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SubmissionsExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(protected $submissions) {}

    /**
     * Return collection of submissions.
     */
    public function collection()
    {
        return $this->submissions;
    }

    /**
     * Map rows for excel output.
     */
    public function map($row): array
    {
        return [
            $row->reference_number,
            $row->full_name,
            $row->phone_number,
            $row->email ?? 'N/A',
            $row->gender,
            $row->age_group,
            $row->state?->name ?? 'N/A',
            $row->lga?->name ?? 'N/A',
            $row->polling_unit ?? 'N/A',
            $row->voted_2023 ? 'Yes' : 'No',
            $row->vote_2027 ? 'Yes' : 'No',
            $row->occupation?->name ?? 'N/A',
            $row->status,
            $row->created_at?->toDateTimeString() ?? 'N/A',
        ];
    }

    /**
     * Headings columns.
     */
    public function headings(): array
    {
        return [
            'Reference Number',
            'Full Name',
            'Phone Number',
            'Email',
            'Gender',
            'Age Group',
            'State',
            'LGA',
            'Polling Unit',
            'Voted 2023',
            'Vote 2027',
            'Occupation',
            'Status',
            'Created At',
        ];
    }
}
