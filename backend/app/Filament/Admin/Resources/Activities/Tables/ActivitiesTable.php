<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Activities\Tables;

use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Store;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ActivitiesTable
{
    /**
     * Readable names for the models that are logged.
     *
     * @var array<class-string, string>
     */
    private const array SUBJECTS = [
        Store::class => 'Toko',
        Category::class => 'Kategori',
        CategoryAttribute::class => 'Atribut kategori',
    ];

    /**
     * @var array<string, string>
     */
    private const array EVENTS = [
        'created' => 'Dibuat',
        'updated' => 'Diubah',
        'deleted' => 'Dihapus',
    ];

    /**
     * Newest first, without actions: the log is read only.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['causer', 'subject']))
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime()->sortable(),
                TextColumn::make('causer.name')->label('Pelaku')->placeholder('Sistem'),
                TextColumn::make('event')
                    ->label('Kejadian')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::EVENTS[$state] ?? (string) $state),
                TextColumn::make('subject_type')
                    ->label('Data')
                    ->formatStateUsing(fn (Activity $record): string => self::describeSubject($record)),
                TextColumn::make('changes')
                    ->label('Perubahan')
                    ->state(fn (Activity $record): array => self::describeChanges($record))
                    ->listWithLineBreaks()
                    ->placeholder('-'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('subject_type')->label('Data')->options(self::SUBJECTS),
                SelectFilter::make('event')->label('Kejadian')->options(self::EVENTS),
            ]);
    }

    /**
     * "Toko #3 · UD Kepiting Pak Budi", or only the type and id when the record is gone.
     */
    private static function describeSubject(Activity $activity): string
    {
        $type = self::SUBJECTS[$activity->subject_type] ?? class_basename((string) $activity->subject_type);
        $name = $activity->subject?->getAttribute('name');

        return trim("{$type} #{$activity->subject_id}".($name !== null ? " · {$name}" : ''));
    }

    /**
     * One line per changed field: "name: Lama → Baru".
     *
     * @return list<string>
     */
    private static function describeChanges(Activity $activity): array
    {
        $new = $activity->attribute_changes['attributes'] ?? [];
        $old = $activity->attribute_changes['old'] ?? [];
        $lines = [];

        foreach ($new as $field => $value) {
            $lines[] = array_key_exists($field, $old)
                ? "{$field}: ".self::show($old[$field]).' → '.self::show($value)
                : "{$field}: ".self::show($value);
        }

        return $lines;
    }

    private static function show(mixed $value): string
    {
        return match (true) {
            $value === null => '-',
            is_bool($value) => $value ? 'ya' : 'tidak',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
        };
    }
}
