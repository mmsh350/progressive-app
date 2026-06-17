<?php

namespace App\Services;

use App\DTOs\SubmissionDTO;
use App\Models\Submission;
use App\Repositories\SubmissionRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;

class SubmissionService
{
    public function __construct(
        protected SubmissionRepositoryInterface $submissionRepository
    ) {}

    /**
     * Process and store a support declaration submission.
     */
    public function createSubmission(SubmissionDTO $dto, ?UploadedFile $file): Submission
    {
        return DB::transaction(function () use ($dto, $file) {
            // 1. Process PVC image if present
            $finalImagePath = null;
            if ($file) {
                $finalImagePath = $this->processAndStorePvcSelfie($file);
            }

            // 2. Prepare data with temporary reference
            $dtoData = $dto->toArray();
            $dtoData['reference_number'] = 'TEMP-' . uniqid();
            
            if ($finalImagePath) {
                $dtoData['image_path'] = $finalImagePath;
            }

            $submissionDTO = SubmissionDTO::fromArray(array_merge($dtoData, [
                'wish' => $dto->wish ? $dto->wish->toArray() : null,
            ]));

            // 3. Save to database using Repository
            $submission = $this->submissionRepository->create($submissionDTO);

            // 4. Update with actual reference number based on ID
            $referenceNumber = 'APC-2027-' . sprintf('%08d', $submission->id);
            $submission->reference_number = $referenceNumber;
            $submission->save();

            // 5. Update submission image watermark status if image exists
            if ($submission->image) {
                $submission->image->update([
                    'image_path' => $finalImagePath,
                    'watermark_applied' => true,
                ]);
            }

            // 6. Dispatch events
            event(new \App\Events\SubmissionCreated($submission));

            return $submission;
        });
    }

    /**
     * Compress, watermark and store citizen PVC selfie.
     */
    protected function processAndStorePvcSelfie(UploadedFile $file): string
    {
        // Generate secure filename
        $fileName = 'pvc_' . strtolower(bin2hex(random_bytes(16))) . '.jpg';
        $directory = 'secure_pvc';

        // Ensure directory exists
        Storage::makeDirectory($directory);
        $fullPath = Storage::path($directory . '/' . $fileName);

        try {
            // Instantiate Intervention Image v4 manager with GD driver
            $manager = new ImageManager(new Driver());
            $image = $manager->decode($file->getRealPath());

            // Scale image if it's too large (max width 1200px)
            $image->scale(width: 1200);

            // Overlay Watermark text
            $image->text('APC 2027 SUPPORT - FOR VERIFICATION ONLY', 30, 50, function ($font) {
                $font->size(20);
                $font->color('#e11d48'); // rose-600 color hex
            });

            // Convert to JPEG format with 80% compression and save
            $image->encode(new JpegEncoder(80))->save($fullPath);
            
            return $directory . '/' . $fileName;
        } catch (\Exception $e) {
            // Fallback: If image library fails, store raw uploaded file securely
            $path = $file->storeAs($directory, $fileName);
            return $path;
        }
    }

    /**
     * Find a submission by reference number.
     */
    public function getSubmissionStatus(string $reference): ?Submission
    {
        return $this->submissionRepository->findByReference($reference);
    }
}
