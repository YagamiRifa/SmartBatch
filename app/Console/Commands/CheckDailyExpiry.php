<?php

namespace App\Console\Commands;

use Filament\Notifications\Notification;
use App\Models\User;
use App\Models\Batch;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:check-daily-expiry')]
#[Description('Command description')]
class CheckDailyExpiry extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1. Ambil barang yang SUDAH kedaluwarsa (hari ini atau sebelumnya)
        $expiredBatches = Batch::whereDate('expiry_date', '<=', now())->get();

        //2. Ambil barang yang HAMPIR kedaluwarsa (besok s/d 7 hari ke depan)
        $warningBatches = Batch::whereDate('expiry_date', '>', now())
            ->whereDate('expiry_date', '<=', now()->addDays(7))
            ->get();

        $admins = User::all();
        $notifSent = false;

        // --- Eksekusi Notifikasi SUDAH EXPIRED ---
        if ($expiredBatches->count() > 0) {
            foreach ($admins as $admin) {
                Notification::make()
                    ->title('Barang Telah Kedaluwarsa! 🚨')
                    ->body("Terdapat {$expiredBatches->count()} batch renteng/dus yang SUDAH kedaluwarsa. Segera tarik dari rak!")
                    ->danger() // Warna notifikasi merah
                    ->sendToDatabase($admin);
            }
            // Munculkan teks merah di terminal
            $this->error("Notifikasi {$expiredBatches->count()} barang EXPIRED terkirim.");
            $notifSent = true;
        }

        // --- Eksekusi Notifikasi HAMPIR EXPIRED (WARNING) ---
        if ($warningBatches->count() > 0) {
            foreach ($admins as $admin) {
                Notification::make()
                    ->title('Peringatan Hampir Kedaluwarsa ⚠️')
                    ->body("Terdapat {$warningBatches->count()} batch barang yang akan kedaluwarsa dalam 7 hari ke depan.")
                    ->warning() // Warna notifikasi oranye/kuning
                    ->sendToDatabase($admin);
            }
            // Munculkan teks kuning di terminal
            $this->warn("Notifikasi {$warningBatches->count()} barang WARNING terkirim.");
            $notifSent = true;
        }

        // --- Jika Semuanya Aman ---
        if (!$notifSent) {
            $this->info('Aman! Tidak ada barang expired atau hampir expired hari ini.');
        }
    }
    // public function handle()
    // {
    //     $expiredBatches = Batch::whereDate('expiry_date', '<=', now())->get();

    //     $admins = User::all();

    //     // KITA SURUH TERMINAL JUJUR BERAPA DATANYA
    //     $this->info("CEK DATA: Ditemukan " . $expiredBatches->count() . " barang expired.");
    //     $this->info("CEK DATA: Ditemukan " . $admins->count() . " akun user.");

    //     if ($expiredBatches->count() > 0) {
    //         if ($admins->count() > 0) {
    //             foreach ($admins as $admin) {
    //                 Notification::make()
    //                     ->title('Peringatan Kedaluwarsa!')
    //                     ->body("Ada {$expiredBatches->count()} batch barang kedaluwarsa.")
    //                     ->danger()
    //                     ->sendToDatabase($admin);
    //             }
    //             $this->info('STATUS: Sukses masuk ke tabel notifications!');
    //         } else {
    //             $this->error('STATUS: GAGAL! Tidak ada akun User ditemukan di database.');
    //         }
    //     } else {
    //         $this->info('STATUS: Aman. Tidak ada barang expired.');
    //     }
    // }
}
