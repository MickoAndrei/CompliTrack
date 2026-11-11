<?php
// Browser fixtures only: render the production dashboard and CAR popup without database writes.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver' => 'array']);
$user = new App\Models\User(['fullName' => 'CAR Browser Test', 'roleId' => 3, 'isActive' => true]);
$user->setAttribute('_id', new MongoDB\BSON\ObjectId('0123456789abcdef01234567'));
Illuminate\Support\Facades\Auth::setUser($user);
view()->share('errors', new Illuminate\Support\ViewErrorBag());
$auditorId = new MongoDB\BSON\ObjectId('1123456789abcdef01234567');
$history = [];
foreach (['open', 'in_progress', 'closed', 'open', 'in_progress'] as $i => $status) {
    $history[] = ['status' => $status, 'timestamp' => sprintf('2026-11-%02dT08:00:00Z', $i + 1), 'changedByUserId' => $auditorId];
}
$check = new App\Models\ComplianceCheck(['checkCode' => 'CAR-BROWSER-TEST', 'referenceStandard' => 'ISO9001-9.2',
    'result' => 'non_compliant', 'correctiveStatus' => 'in_progress', 'auditorId' => $auditorId,
    'auditeeId' => $user->getKey(), 'statusHistory' => $history]);
$check->setAttribute('_id', new MongoDB\BSON\ObjectId('2123456789abcdef01234567'));
$check->setAttribute('createdAt', now());
$check->setAttribute('evidenceResponses', [
    ['method' => 'digital-reference', 'notes' => 'Permitted original reference', 'at' => '2026-11-02T12:00:00Z', 'author' => (string) $user->getKey()],
    ['method' => 'digital-reference', 'notes' => 'Revised permitted reference', 'at' => '2026-11-04T12:00:00Z', 'author' => (string) $user->getKey()],
]);
$directory = storage_path('framework/testing/car-timeline-browser');
Illuminate\Support\Facades\File::ensureDirectoryExists($directory);
$manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
$bundle = 'file:///'.str_replace('\\', '/', public_path('build/'.$manifest['resources/js/app.js']['file']));
$html = view('auditee.dashboard', ['checks' => collect([$check]), 'published' => collect(), 'requests' => collect(), 'notifications' => collect()])->render();
$html = preg_replace('/<script\b[^>]*>.*?<\/script>/s', '', $html);
$html = preg_replace('/<link[^>]+(?:fonts\.googleapis|fonts\.gstatic)[^>]*>/', '', $html);
$html = str_replace('http://localhost', 'file:///'.str_replace('\\', '/', public_path()), $html);
$html = str_replace('</body>', '<script type="module" src="'.$bundle.'"></script></body>', $html);
file_put_contents($directory.'/dashboard.html', $html);
file_put_contents($directory.'/upload.html', view('auditee.upload')->render());
$previewArchives = collect(['preview.pdf', 'preview.png', 'preview.docx'])->map(function ($name, $index) {
    $archive = new App\Models\ArchiveDocument(['originalName' => $name]);
    $archive->setAttribute('_id', new MongoDB\BSON\ObjectId(sprintf('%024d', $index + 1)));
    return $archive;
});
$zip = new \ZipArchive();
$zip->open($directory.'/preview.docx', \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
$zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
$zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
$zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:rPr><w:b/><w:color w:val="071D49"/><w:sz w:val="32"/></w:rPr><w:t>Authorized Word evidence preview</w:t></w:r></w:p><w:p><w:r><w:t>&lt;script&gt;must remain plain text&lt;/script&gt;</w:t></w:r></w:p><w:tbl><w:tblPr><w:tblW w:w="8000" w:type="dxa"/><w:tblBorders><w:top w:val="single" w:sz="8"/><w:bottom w:val="single" w:sz="8"/><w:insideH w:val="single" w:sz="8"/><w:insideV w:val="single" w:sz="8"/></w:tblBorders></w:tblPr><w:tblGrid><w:gridCol w:w="4000"/><w:gridCol w:w="4000"/></w:tblGrid><w:tr><w:tc><w:p><w:r><w:t>Evidence requirement</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Approved syllabus</w:t></w:r></w:p></w:tc></w:tr></w:tbl><w:p><w:r><w:br w:type="page"/><w:t>Second document page</w:t></w:r></w:p><w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720"/></w:sectPr></w:body></w:document>');
$zip->close();
foreach (['cycles', 'closed', 'compliant'] as $state) {
    if ($state === 'closed') { $check->correctiveStatus = 'closed'; }
    if ($state === 'compliant') { $check->result = 'compliant'; $check->correctiveStatus = null; }
    file_put_contents($directory.'/'.$state.'.html', view('auditee.result-detail-content', ['check' => $check,
        'archives' => $previewArchives, 'auditorName' => 'Assigned Auditor', 'departmentName' => 'Engineering'])->render());
}
echo "CAR timeline browser fixtures rendered.\n";
