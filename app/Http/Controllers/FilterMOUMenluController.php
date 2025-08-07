<?php

namespace App\Http\Controllers;

use App\Models\NotaMOU;
use Illuminate\Http\Request;

class FilterMOUMenluController extends Controller
{
    public function index(Request $request)
    {
        $query = NotaMOU::query();

        // Filter berdasarkan jenis dokumen
        if ($request->filled('jenis_dokumen')) {
            $query->where('jenis_dokumen', 'like', '%' . $request->jenis_dokumen . '%');
        }

        // Filter berdasarkan perihal dokumen
        if ($request->filled('perihal_dokumen')) {
            $query->where('perihal_dokumen', 'like', '%' . $request->perihal_dokumen . '%');
        }

        // Filter berdasarkan satker kemlu terkait
        if ($request->filled('satker_kemlu_terkait')) {
            $query->where('satker_kemlu_terkait', 'like', '%' . $request->satker_kemlu_terkait . '%');
        }

        // Filter berdasarkan K/L/I external terkait
        if ($request->filled('kl_external_terkait')) {
            $query->where('kl_external_terkait', 'like', '%' . $request->kl_external_terkait . '%');
        }

        // Filter berdasarkan rentang tanggal disahkan
        if ($request->filled('start_date_disahkan') && $request->filled('end_date_disahkan')) {
            $query->whereBetween('tanggal_disahkan', [
                $request->start_date_disahkan,
                $request->end_date_disahkan
            ]);
        }

        // Filter berdasarkan rentang tanggal berakhir
        if ($request->filled('start_date_berakhir') && $request->filled('end_date_berakhir')) {
            $query->whereBetween('tanggal_berakhir', [
                $request->start_date_berakhir,
                $request->end_date_berakhir
            ]);
        }

        // Sortir berdasarkan kolom tanggal
        if ($request->filled('sort_by')) {
            switch ($request->sort_by) {
                case 'tanggal_disahkan_asc':
                    $query->orderBy('tanggal_disahkan', 'asc');
                    break;
                case 'tanggal_disahkan_desc':
                    $query->orderBy('tanggal_disahkan', 'desc');
                    break;
                case 'tanggal_berakhir_asc':
                    $query->orderBy('tanggal_berakhir', 'asc');
                    break;
                case 'tanggal_berakhir_desc':
                    $query->orderBy('tanggal_berakhir', 'desc');
                    break;
            }
        }

        // Pagination
        $notaKesepahaman = $query->paginate(10);

        return view('nkmdp', [
            'title' => 'Nota Kesepahaman dan PKS',
            'notaKesepahaman' => $notaKesepahaman,
        ]);
    }
}
