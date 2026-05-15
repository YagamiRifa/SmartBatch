<?php

namespace App\Models;

use Carbon\Carbon;
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
}
