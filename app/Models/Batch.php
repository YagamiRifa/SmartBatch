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
    public function sendExpiryNotification()
    {
        // Cek jika statusnya bukan SAFE
        if ($this->status === 'SAFE') return;

        $color = $this->status === 'EXPIRED' ? 'danger' : 'warning';
        $title = $this->status === 'EXPIRED' ? 'Barang Kedaluwarsa!' : 'Barang Mendekati Kedaluwarsa';

        // Ambil semua user admin
        $admins = User::all();

        Notification::make()
            ->title($title)
            ->body("Barang: **{$this->item->nama_barang}**\nBatch: {$this->batch_number}")
            ->icon($this->status === 'EXPIRED' ? 'heroicon-o-x-circle' : 'heroicon-o-exclamation-triangle')
            ->color($color)
            // Tambahkan tombol untuk langsung melihat barangnya
            ->actions([
                Action::make('view')
                    ->label('Lihat Barang')
                    ->url(fn() => ItemResource::getUrl('index', ['search' => $this->batch_number]))
            ])
            ->sendToDatabase($admins)
            ->broadcast($admins);
    }
}
