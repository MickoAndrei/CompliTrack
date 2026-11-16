<?php
namespace App\Services;

/** Reference data lives on the HTTP request, never in a process or shared cache. */
class QmsRequestReferences
{
    public function remember(iterable $models): void
    {
        $groups = [];
        foreach ($models as $model) { $groups[$model::class][(string) $model->getKey()] = $model; }
        foreach ($groups as $class => $records) {
            $key = 'qms.references.'.$class;
            request()->attributes->set($key, array_replace(request()->attributes->get($key, []), $records));
        }
    }

    public function prime(string $model, array $ids): void
    {
        $attributes = request()->attributes;
        $key = 'qms.references.'.$model;
        $records = $attributes->get($key, []);
        $missing = array_values(array_filter(array_unique(array_map('strval', $ids)),
            fn ($id) => preg_match('/^[a-f0-9]{24}$/i', $id) && !array_key_exists($id, $records)));
        if (!$missing) { return; }
        foreach ($missing as $id) { $records[$id] = null; }
        foreach ($model::whereIn('_id', app(QmsArchiveAccess::class)->ids($missing))->get() as $record) {
            $records[(string) $record->getKey()] = $record;
        }
        $attributes->set($key, $records);
    }

    public function find(string $model, mixed $id): mixed
    {
        $this->prime($model, [$id]);
        return request()->attributes->get('qms.references.'.$model, [])[(string) $id] ?? null;
    }
}
