<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KebijakanTikNonKemlu;
use Illuminate\Http\Request;

class KebijakanTikNonKemluController extends Controller
{
    public function index()
    {
        $kebijakan = KebijakanTikNonKemlu::latest()->paginate(10); // Ubah ke $kebijakan
        return view('kebijakan-non-kemlu.index', compact('kebijakan')); // Kirim $kebijakan ke view
    }

    public function create()
    {
        return view('kebijakan-non-kemlu.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'jenis_kebijakan' => 'required',
            'nomor_kebijakan' => 'required',
            'tahun_penerbitan' => 'required|numeric|min:1900|max:' . (date('Y') + 1),
            'perihal' => 'required',
            'instansi' => 'required',
            'tautan' => 'nullable|url'
        ]);

        KebijakanTikNonKemlu::create($request->all());

        return redirect()->route('admin.kebijakan-non-kemlu.index')
            ->with('success', 'Kebijakan TIK Non Kemlu berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $kebijakan = KebijakanTikNonKemlu::findOrFail($id);
        return view('kebijakan-non-kemlu.edit', compact('kebijakan'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'jenis_kebijakan' => 'required',
            'nomor_kebijakan' => 'required',
            'tahun_penerbitan' => 'required|numeric|min:1900|max:' . (date('Y') + 1),
            'perihal' => 'required',
            'instansi' => 'required',
            'tautan' => 'nullable|url'
        ]);

        $kebijakan = KebijakanTikNonKemlu::findOrFail($id);
        $kebijakan->update($request->all());

        return redirect()->route('admin.kebijakan-non-kemlu.index')
            ->with('success', 'Kebijakan TIK Non Kemlu berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $kebijakan = KebijakanTikNonKemlu::findOrFail($id);
        $kebijakan->delete();

        return redirect()->route('admin.kebijakan-non-kemlu.index')
            ->with('success', 'Kebijakan TIK Non Kemlu berhasil dihapus.');
    }

    public function guestIndex()
    {
        $kebijakan = KebijakanTikNonKemlu::latest()->paginate(10);
        $title = 'Kebijakan TIK by Non Kemlu';

        return view('ktbnk', compact('kebijakan', 'title'));
    }
}