<?php

namespace App\Filament\Widgets;

use App\Models\Batch;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ExpiringBatchesTable extends TableWidget
{
    protected int | string | array $columnSpan = 'full';
    public function table(Table $table): Table
    {
        return $table
            ->poll(1) // Refresh data setiap 1 detik untuk update jumlah batch secara real-time
            ->query(fn(): Builder => Batch::query()
                ->whereDate('expiry_date', '<=', now()->addDays(7)->endOfDay())
                ->orderBy('expiry_date', 'asc'))
            ->paginated([4])
            ->defaultPaginationPageOption(4)
            ->columns([
                TextColumn::make('batch_number')
                    ->label('Nomor Batch')
                    ->searchable(),
                TextColumn::make('item.nama_barang')
                    ->label('Nama Barang')
                    ->searchable(),
                TextColumn::make('item.satuan')
                    ->label('Satuan')
                    ->badge(),
                TextColumn::make('expiry_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                DeleteAction::make()
                    ->hiddenLabel()
                    ->requiresConfirmation()
                    ->modalButton('Ya, Tarik')
                    ->modalHeading('Tarik Batch')
                    ->modalDescription('Yakin ingin menarik/retur batch ini? Data akan dihapus.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hiddenLabel()
                        ->requiresConfirmation()
                        ->modalButton('Ya, Tarik Semua')
                        ->modalHeading('Tarik Batch')
                        ->modalDescription('Yakin ingin menarik/retur semua batch yang dipilih? Data akan dihapus.')
                ]),
            ]);
    }
}
