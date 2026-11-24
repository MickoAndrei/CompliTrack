<?php
namespace App\Services;

use App\Models\{Campus, College, Department};
use App\Support\MongoId;

class QmsCampusReferences
{
    public function officialName(?string $label): ?string
    {
        $label = preg_replace('/\s+campus$/i', '', preg_replace('/^ustp\s+/i', '', trim((string) $label)));
        return collect(config('qms.campuses', []))->first(fn ($name) => mb_strtolower($name) === mb_strtolower($label));
    }
    public function migrate(): void
    {
        (new Campus)->getConnection()->getMongoDB()->selectCollection('campuses')->createIndex(['campusKey' => 1], ['unique' => true, 'name' => 'campus_key_unique']);
        foreach (College::all() as $college) { $this->attach($college); }
    }

    public function attach(College $college): ?Campus
    {
        if ($college->campusId) { return Campus::find($college->campusId); }
        $official = $this->officialName($college->campusName);
        if (!$official) { return null; } // Unknown ownership needs review; never guess a campus.
        $campus = Campus::firstOrCreate(['campusKey' => mb_strtolower($official)], ['campusName' => $official, 'isActive' => true]);
        College::where('_id', $college->getKey())->whereNull('campusId')->update(['campusId' => MongoId::parse((string) $campus->getKey())]);
        foreach (Department::where('collegeId', MongoId::parse((string) $college->getKey()))->whereNull('campusId')->get() as $unit) {
            if ($unit->campusName && $this->officialName($unit->campusName) !== $official) { continue; }
            Department::where('_id', $unit->getKey())->whereNull('campusId')->update(['campusId' => MongoId::parse((string) $campus->getKey())]);
        }
        return $campus;
    }
}
