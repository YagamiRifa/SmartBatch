<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Item;
use App\Models\Batch;
use Carbon\Carbon;

class RaspiController extends Controller
{
    public function storeBatch(Request $request)
    {
        $request->validate([
            'barcode' => 'required|string',
            'expiry_date' => 'required|string', // format: DD/MM/YYYY
        ]);

        // Cari item berdasarkan identitas unik barcode
        $item = Item::where('barcode', $request->barcode)->first();

        if (!$item) {
            return response()->json(['message' => 'Barang tidak ditemukan.'], 404);
        }

        try {
            // Parse tanggal dari kamera OCR Raspi (DD/MM/YYYY) ke YYYY-MM-DD
            $parsedDate = Carbon::createFromFormat('d/m/Y', trim($request->expiry_date))->format('Y-m-d');
        } catch (\Exception $e) {
            return response()->json(['message' => 'Format tanggal salah. Gunakan DD/MM/YYYY.'], 400);
        }

        // --- PENGECEKAN BATCH DUPLIKAT ---
        $isBatchExists = Batch::where('item_id', $item->id)
            ->where('expiry_date', $parsedDate)
            ->exists();

        if ($isBatchExists) {
            return response()->json([
                'message' => 'Batch dengan tanggal tersebut sudah terdaftar.'
            ], 409); // 409 Conflict adalah status HTTP yang tepat untuk duplikasi data
        }
        // ---------------------------------

        $batch = Batch::firstOrCreate(
            [
                'item_id' => $item->id,
                'expiry_date' => $parsedDate,
            ]
            // batch_number akan di-generate otomatis oleh model
        );

        return response()->json([
            'message' => 'Data batch berhasil disimpan.',
            'data' => $batch
        ], 201);
    }
}
