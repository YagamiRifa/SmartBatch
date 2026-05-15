<?php

namespace App\Filament\Widgets;

use App\Models\Item;
use App\Models\Batch;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ExpiringBatchesStats extends BaseWidget
{
    // protected static ?int $sort = 1; // Memastikan widget ini muncul paling atas di Dashboard
    protected ?string $pollingInterval = '1';
    protected function getStats(): array
    {

        // Mendapatkan tanggal hari ini dan batas H-7
        $today = now()->startOfDay();
        $warningDate = now()->addDays(7)->endOfDay();

        // Menghitung jumlah data
        $totalItems = Item::count();
        $expiredBatches = Batch::whereDate('expiry_date', '<', $today)->count();
        $warningBatches = Batch::whereBetween('expiry_date', [$today, $warningDate])->count();
        $safeBatches = Batch::whereDate('expiry_date', '>', $warningDate)->count();

        return [
            Stat::make('Total Jenis Barang', $totalItems)
                ->description('Barang terdaftar')
                ->icon('heroicon-m-cube')
                ->color('gray'),

            Stat::make('Aman', $safeBatches)
                ->description('Masa simpan masih panjang')
                ->icon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Mendekati Expired', $warningBatches)
                ->description('Segera cek rak/gudang')
                ->icon('heroicon-m-exclamation-triangle')
                ->color('warning'),

            Stat::make('Expired', $expiredBatches)
                ->description('Perlu ditarik atau retur')
                ->icon('heroicon-m-x-circle')
                ->color('danger'),
        ];
    }
}
