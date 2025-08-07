<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotaKesepahaman;
use Illuminate\Http\Request;

class NotaKesepahamanController extends Controller
{
    public function index()
    {
        $notaKesepahaman = NotaKesepahaman::latest()->paginate(10);
        return view('nota-kesepahaman.index', compact('notaKesepahaman'));
    }

    public function create()
    {
        return view('nota-kesepahaman.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'jenis_dokumen' => 'required|in:MOU,PKS',
            'perihal_dokumen' => 'required',
            'satker_kemlu_terkait' => 'required',
            'kl_external_terkait' => 'required',
            'tanggal_disahkan' => 'required|date',
            'tanggal_berakhir' => 'required|date|after:tanggal_disahkan',
            'status' => 'required|in:Aktif,Tidak Aktif,Dalam Perpanjangan',
            'keterangan' => 'nullable'
        ]);

        NotaKesepahaman::create($request->all());

        return redirect()->route('admin.nota-kesepahaman.index')
            ->with('success', 'Nota Kesepahaman berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $notaKesepahaman = NotaKesepahaman::findOrFail($id);
        return view('nota-kesepahaman.edit', compact('notaKesepahaman'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'jenis_dokumen' => 'required|in:MOU,PKS',
            'perihal_dokumen' => 'required',
            'satker_kemlu_terkait' => 'required',
            'kl_external_terkait' => 'required',
            'tanggal_disahkan' => 'required|date',
            'tanggal_berakhir' => 'required|date|after:tanggal_disahkan',
            'status' => 'required|in:Aktif,Tidak Aktif,Dalam Perpanjangan',
            'keterangan' => 'nullable'
        ]);

        $notaKesepahaman = NotaKesepahaman::findOrFail($id);
        $notaKesepahaman->update($request->all());

        return redirect()->route('admin.nota-kesepahaman.index')
            ->with('success', 'Nota Kesepahaman berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $notaKesepahaman = NotaKesepahaman::findOrFail($id);
        $notaKesepahaman->delete();

        return redirect()->route('admin.nota-kesepahaman.index')
            ->with('success', 'Nota Kesepahaman berhasil dihapus.');
    }

    // Fungsi tambahan untuk mencari dokumen yang akan berakhir
    public function checkExpiringDocuments()
    {
        $thirtyDaysFromNow = now()->addDays(30);
        $expiringDocuments = NotaKesepahaman::where('status', 'Aktif')
            ->where('tanggal_berakhir', '<=', $thirtyDaysFromNow)
            ->where('tanggal_berakhir', '>', now())
            ->get();

        return $expiringDocuments;
    }

    // Fungsi untuk memperbarui status otomatis
    public function updateStatus()
    {
        $expiredDocuments = NotaKesepahaman::where('tanggal_berakhir', '<', now())
            ->where('status', 'Aktif')
            ->update(['status' => 'Tidak Aktif']);

        return redirect()->route('admin.nota-kesepahaman.index')
            ->with('success', 'Status dokumen berhasil diperbarui.');
    }

    public function guestIndex()
{
    $notaKesepahaman = NotaKesepahaman::latest()->paginate(10);
    $title = 'Nota Kesepahaman (MoU) dan PKS';

    return view('nkmdp', compact('notaKesepahaman', 'title'));
}

}