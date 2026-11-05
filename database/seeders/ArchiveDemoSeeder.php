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
                'fileCode' => 'FM-USTP-IT-01',
                'departmentCode' => 'DEMO-IT',
                'uploaderEmail' => 'demo.auditee.it@example.test',
                'name' => 'ISO-9001-Cert-2026.pdf',
                'standard' => 'ISO 9001',
                'tags' => ['[DEMO]', 'ISO 9001', 'certificate'],
                'title' => 'ISO 9001 Quality Management Certification 2026',
                'body' => 'FM-USTP-IT-01\nUNIVERSITY OF SCIENCE AND TECHNOLOGY OF SOUTHERN PHILIPPINES\nQuality Management System Certification Record.',
            ],
            [
                'fileCode' => 'FM-USTP-ENVI-07',
                'departmentCode' => 'DEMO-IT',
                'uploaderEmail' => 'demo.auditee.it@example.test',
                'name' => 'ARTA-Checklist-Q3.docx',
                'standard' => 'ARTA 7',
                'tags' => ['[DEMO]', 'ARTA', 'checklist'],
                'title' => 'ARTA Q3 Compliance Checklist',
                'body' => 'FM-USTP-ENVI-07\nUNIVERSITY OF SCIENCE AND TECHNOLOGY OF SOUTHERN PHILIPPINES\nAnti-Red Tape Act Q3 Compliance Verification.',
            ],
            [
                'fileCode' => 'FM-USTP-FOOD-03',
                'departmentCode' => 'DEMO-ENG',
                'uploaderEmail' => 'demo.auditee.eng@example.test',
                'name' => 'Fire-Safety-Report.pdf',
                'standard' => 'ARTA 3',
                'tags' => ['[DEMO]', 'fire safety', 'report'],
                'title' => 'Campus Fire Safety & Compliance Inspection Report',
                'body' => 'FM-USTP-FOOD-03\nUNIVERSITY OF SCIENCE AND TECHNOLOGY OF SOUTHERN PHILIPPINES\nSafety Audit & Incident Prevention Report.',
            ],
            [
                'fileCode' => 'FM-USTP-AGRO-14',
                'departmentCode' => 'DEMO-ENG',
                'uploaderEmail' => 'demo.auditee.eng@example.test',
                'name' => 'Lab-Inspection-Photos.jpg',
                'standard' => 'ISO 14001',
                'tags' => ['[DEMO]', 'laboratory', 'inspection'],
                'title' => 'Laboratory Equipment & Environmental Inspection Log',
                'body' => 'FM-USTP-AGRO-14\nUNIVERSITY OF SCIENCE AND TECHNOLOGY OF SOUTHERN PHILIPPINES\nEnvironmental Management System Audit Trail.',
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
                    'mimeType' => str_ends_with(strtolower($document['name']), '.pdf') ? 'application/pdf' : (str_ends_with(strtolower($document['name']), '.jpg') ? 'image/jpeg' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
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

        $this->command?->info('Seeded 4 USTP QMS coded demo archive documents.');
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
