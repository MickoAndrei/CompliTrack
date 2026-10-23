<?php

namespace Database\Seeders;

use App\Models\ArchiveDocument;
use App\Models\Department;
use App\Models\User;
use App\Services\ArchiveEncryptionService;
use Illuminate\Database\Seeder;

class ArchiveDemoSeeder extends Seeder
{
    public function run(): void
    {
        $documents = [
            [
                'fileCode' => 'DEMO-ARCH-IT-001',
                'departmentCode' => 'DEMO-IT',
                'uploaderEmail' => 'demo.auditee.it@example.test',
                'name' => 'DEMO-IT-Information-Security-Policy.pdf',
                'standard' => 'ISO9001-9.2',
                'tags' => ['[DEMO]', 'information security', 'policy'],
                'title' => 'Information Security Policy',
                'body' => 'Sample evidence for the Information Technology department.\nApproved policy review: 2026-01-15.',
            ],
            [
                'fileCode' => 'DEMO-ARCH-IT-002',
                'departmentCode' => 'DEMO-IT',
                'uploaderEmail' => 'demo.auditee.it@example.test',
                'name' => 'DEMO-IT-Procedure-Review-Record.pdf',
                'standard' => 'ISO9001-9.2',
                'tags' => ['[DEMO]', 'procedure', 'review'],
                'title' => 'Procedure Review Record',
                'body' => 'Sample evidence for the Information Technology department.\nNext review due: 2026-12-01.',
            ],
            [
                'fileCode' => 'DEMO-ARCH-ENG-001',
                'departmentCode' => 'DEMO-ENG',
                'uploaderEmail' => 'demo.auditee.eng@example.test',
                'name' => 'DEMO-ENG-Equipment-Calibration-Log.pdf',
                'standard' => 'ISO9001-9.2',
                'tags' => ['[DEMO]', 'equipment', 'calibration'],
                'title' => 'Equipment Calibration Log',
                'body' => 'Sample evidence for the Engineering department.\nCalibration records reviewed: 2026-02-03.',
            ],
        ];

        $encryption = app(ArchiveEncryptionService::class);

        foreach ($documents as $document) {
            $department = Department::where('departmentCode', $document['departmentCode'])->firstOrFail();
            $uploader = User::where('email', $document['uploaderEmail'])->firstOrFail();
            $archive = ArchiveDocument::firstOrNew(['fileCode' => $document['fileCode']]);

            $storageRoot = config('filesystems.disks.local.root');
            $archivePath = $archive->encryptedPath
                ? rtrim($storageRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$archive->encryptedPath
                : null;
            if (! $archive->exists || ! $archivePath || ! is_file($archivePath)) {
                $plaintext = $this->makePdf($document['title'], $document['body']);
                $encrypted = $encryption->encrypt($plaintext);
                $path = "archives/{$document['fileCode']}.enc";

                $archiveDirectory = dirname(rtrim($storageRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$path);
                if (! is_dir($archiveDirectory) && ! mkdir($archiveDirectory, 0775, true) && ! is_dir($archiveDirectory)) {
                    throw new \RuntimeException('Unable to create the demo archive directory.');
                }
                if (file_put_contents(rtrim($storageRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$path, $encrypted['ciphertext']) === false) {
                    throw new \RuntimeException("Unable to write demo archive {$document['fileCode']}.");
                }

                $archive->fill([
                    'originalName' => $document['name'],
                    'encryptedPath' => $path,
                    'mimeType' => 'application/pdf',
                    'fileSize' => strlen($plaintext),
                    'checksum' => hash('sha256', $plaintext),
                    'departmentId' => $department->getKey(),
                    'uploadedByUserId' => $uploader->getKey(),
                    'referenceStandard' => $document['standard'],
                    'tags' => $document['tags'],
                    'isEncrypted' => true,
                    'encryptionIv' => $encrypted['iv'],
                    'encryptionTag' => $encrypted['tag'],
                ]);
            }

            $archive->save();
        }

        $this->command?->info('Seeded 3 encrypted demo archive documents.');
    }

    private function makePdf(string $title, string $body): string
    {
        $escape = static fn (string $value): string => str_replace(
            ['\\', '(', ')', "\r", "\n"],
            ['\\\\', '\\(', '\\)', '', ' '],
            $value,
        );
        $stream = "BT\n/F1 18 Tf\n72 740 Td\n(".$escape($title).") Tj\n/F1 11 Tf\n0 -32 Td\n(".$escape($body).") Tj\nET\n";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}endstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }
}
