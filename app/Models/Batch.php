<?php

namespace App\Models;

use App\Filament\Resources\Items\ItemResource;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Batch extends Model
{
    protected $fillable = ['batch_number', 'item_id', 'expiry_date'];
    protected $appends = ['status']; // Menambahkan status sebagai virtual attribute

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            // Auto generate Batch Number: BATCH-YYYYMMDD-Random
            $model->batch_number = 'BTH-' . date('Ymd') . '-' . strtoupper(Str::random(4));
        });
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function getStatusAttribute(): string
    {
        $today = now()->startOfDay();
        $expiry = Carbon::parse($this->expiry_date)->startOfDay();

        if ($expiry->lessThan($today)) {
            return 'EXPIRED';
        }

        // diffInDays mengembalikan selisih hari. Jika <= 7, masuk Warning
        if ($today->diffInDays($expiry, false) <= 7) {
            return 'WARNING';
        }

        return 'SAFE';
    }
    public function sendExpiryNotification($isFromRaspi = false)
    {
        if ($this->status === 'SAFE') return;

        $color = $this->status === 'EXPIRED' ? 'danger' : 'warning';

        // --- LOGIKA PEMBEDA TEKS NOTIFIKASI ---
        if ($isFromRaspi) {
            // Teks jika data dikirim dari Raspberry Pi
            $title = $this->status === 'EXPIRED' ? 'Scanner: Barang Kedaluwarsa!' : 'Scanner: Mendekati Kedaluwarsa';
            $body = "Hasil scan:\nBarang **{$this->item->nama_barang}** (Batch: {$this->batch_number}).";
        } else {
            // Teks jika data diinput manual via Web Filament
            $title = $this->status === 'EXPIRED' ? 'Scanner: Barang Kedaluwarsa!' : 'Scanner: Mendekati Kedaluwarsa';
            $body = "Input Manual:\nBarang **{$this->item->nama_barang}** (Batch: {$this->batch_number}).";
        }

        // Ambil semua user admin
        $admins = \App\Models\User::all();

        foreach ($admins as $admin) {
            Notification::make()
                ->title($title)
                ->body($body)
                ->icon($this->status === 'EXPIRED' ? 'heroicon-o-x-circle' : 'heroicon-o-exclamation-triangle')
                ->color($color)
                ->actions([
                    Action::make('view')
                        ->label('Lihat Barang')
                        ->button()
                        ->url(fn() => ItemResource::getUrl('index', ['search' => $this->batch_number]))
                ])
                ->sendToDatabase($admin)
                ->broadcast($admin);
        }
    }
}
