<?php

namespace App\Http\Controllers\Api;

use App\Events\BatchUpdated;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Item;
use App\Models\User;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;

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
        // Tembakkan sinyal ke WebSocket Reverb!
        broadcast(new BatchUpdated());

        if ($batch->status === 'SAFE') {
            // Jika aman (tanggal masih panjang), munculkan notif hijau biasa
            $admins = User::all();
            foreach ($admins as $admin) {
                Notification::make()
                    ->title('Scan Berhasil! 📦')
                    ->body("Item {$item->nama_barang} (Aman) berhasil masuk database.")
                    ->success()
                    ->sendToDatabase($admin)
                    ->broadcast($admin);
            }
        } else {
            // JIKA WARNING ATAU EXPIRED:
            // Panggil fungsi canggih di Model Batch yang memunculkan tombol "Lihat Barang"!
            $batch->sendExpiryNotification(true);
        }

        return response()->json([
            'message' => 'Data batch berhasil disimpan.',
            'data' => $batch
        ], 201);
    }
}
