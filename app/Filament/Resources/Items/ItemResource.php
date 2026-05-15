<?php

namespace App\Filament\Resources\Items;

use App\Filament\Resources\Items\Pages\ManageItems;
use App\Models\Item;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
// use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
// use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;


class ItemResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'nama_barang';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('kode_barang')
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('barcode')
                    ->unique(ignoreRecord: true),
                TextInput::make('nama_barang')
                    ->required(),
                TextInput::make('satuan')
                    ->required(),
                Textarea::make('deskripsi')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll(1) // Refresh data setiap 1 detik untuk update jumlah batch secara real-time
            ->recordTitleAttribute('nama_barang')
            ->columns([
                TextColumn::make('kode_barang')
                    ->searchable(),
                TextColumn::make('barcode')
                    ->searchable(),
                TextColumn::make('nama_barang')
                    ->searchable(),
                TextColumn::make('satuan')
                    ->badge(),
                TextColumn::make('batches_count')
                    ->counts('batches')
                    ->label('Total Batch')
                    ->badge()
                    ->color('secondary'),
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
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel(),
                EditAction::make('manage-batches')
                    ->hiddenLabel()
                    ->icon('heroicon-o-list-bullet')
                    ->color('info')
                    ->modalHeading(fn(Item $record) => 'Daftar Batch: ' . $record->nama_barang)
                    ->modalDescription('Cek hasil OCR Raspi atau tambah batch secara manual.')
                    ->modalSubmitActionLabel('Simpan Perubahan')
                    ->form([
                        Repeater::make('batches')
                            // Refresh data setiap 1 detik untuk update jumlah batch secara real-time
                            ->relationship()
                            ->table([
                                TableColumn::make('No Batch'),
                                TableColumn::make('Expiry'),
                                TableColumn::make('Status'),

                            ])
                            ->schema([
                                Placeholder::make('batches_number_display')
                                    ->disabled()
                                    ->content(fn($record) => $record?->batch_number ?? 'AUTO-GENERATED')
                                    ->columnSpan(2),
                                DatePicker::make('expiry_date')
                                    ->required()
                                    ->displayFormat('d/m/Y')
                                    ->native(false)
                                    ->columnSpan(2)

                                    // 1. Mencegah user mengetik tanggal yang sama di 2 baris form yang sedang terbuka
                                    ->distinct()
                                    ->validationMessages([
                                        'distinct' => 'Tanggal ini sudah Anda input di baris lain pada form ini.',
                                    ])

                                    // 2. Pengecekan ketat langsung ke tabel database
                                    ->rules([
                                        function ($livewire, ?\Illuminate\Database\Eloquent\Model $record) {
                                            return function (string $attribute, $value, \Closure $fail) use ($livewire, $record) {
                                                // Ambil ID Barang (Item) yang sedang dikelola
                                                $itemId = $livewire->getMountedTableActionRecord()->id;

                                                // Ubah format tanggal dari form pembacaan (bisa datetime) menjadi Y-m-d
                                                $parsedDate = Carbon::parse($value)->format('Y-m-d');

                                                // Cek apakah tanggal ini sudah ada di database untuk barang ini
                                                $exists = \App\Models\Batch::where('item_id', $itemId)
                                                    ->whereDate('expiry_date', $parsedDate)
                                                    ->when($record, function ($query) use ($record) {
                                                        // PENTING: Jika admin sedang MENGEDIT batch yang sudah ada,
                                                        // kita abaikan pengecekan pada ID batch itu sendiri
                                                        return $query->where('id', '!=', $record->id);
                                                    })
                                                    ->exists();

                                                if ($exists) {
                                                    $fail('Tanggal kedaluwarsa ini sudah terdaftar.');
                                                }
                                            };
                                        }
                                    ]),
                                Placeholder::make('Status')
                                    ->badge()
                                    ->content(fn($record) => $record?->status ?? 'BARU')
                                    ->color(fn($record): string => match ($record?->status) {
                                        'SAFE' => 'success',
                                        'WARNING' => 'warning',
                                        'EXPIRED' => 'danger',
                                        default => 'secondary', // Warna untuk status 'BARU' atau lainnya
                                    })
                                    ->columnSpan(2),
                            ])
                            ->columns(6)
                            ->addActionLabel('+ Tambah Batch Manual') // Tombol untuk fallback manual web
                            ->deleteAction(
                                fn(Action $action) => $action
                                    ->label('Tarik / Retur')
                                    ->requiresConfirmation()
                                    ->modalHeading('Tarik Batch')
                                    ->modalDescription('Yakin ingin menarik/retur batch ini? Data akan dihapus.')
                            )
                        // ->createItemButtonLabel('Tambah Batch Baru'),
                    ])
                    ->after(fn() => broadcast(new \App\Events\BatchUpdated())),
                DeleteAction::make()
                    ->hiddenLabel()->before(function ($record, $action) {
                        // Cek apakah barang ini memiliki relasi batch
                        // (Menggunakan exists() lebih cepat secara query daripada count())
                        if ($record->batches()->exists()) {

                            // Kirim notifikasi error yang cantik ke pojok layar
                            Notification::make()
                                ->danger()
                                ->title('Gagal Menghapus Barang!')
                                ->body('Barang ini tidak bisa dihapus karena masih memiliki ' . $record->batches()->count() . ' data batch. Silakan tarik/hapus semua batch terlebih dahulu.')
                                ->send();

                            // Batalkan proses penghapusan (jangan teruskan ke database)
                            $action->halt();
                        }
                    }),
            ])
            ->toolbarActions([
                // BulkActionGroup::make([
                //     DeleteBulkAction::make(),
                // ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageItems::route('/'),
        ];
    }
}
