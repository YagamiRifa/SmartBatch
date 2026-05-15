<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $fillable = ['kode_barang', 'barcode', 'nama_barang', 'satuan', 'deskripsi'];

    public function batches()
    {
        return $this->hasMany(Batch::class);
    }
}
