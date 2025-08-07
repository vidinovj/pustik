<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KebijakanTikKemlu;
use Illuminate\Http\Request;

class KebijakanTikKemluController extends Controller
{
   public function index()
   {
       $kebijakan = KebijakanTikKemlu::latest()->paginate(10);
       return view('kebijakan-kemlu.index', compact('kebijakan'));
   }

  
   public function create()
   {
       return view('kebijakan-kemlu.create');
   }

  
   public function store(Request $request)
   {
       $request->validate([
           'jenis_kebijakan' => 'required',
           'nomor_kebijakan' => 'required',
           'tahun_penerbitan' => 'required|numeric|min:1900|max:' . (date('Y') + 1),
           'perihal_kebijakan' => 'required',
           'tautan' => 'nullable|url'
       ]);

       KebijakanTikKemlu::create($request->all());

       return redirect()->route('admin.kebijakan-kemlu.index')
           ->with('success', 'Kebijakan TIK Kemlu berhasil ditambahkan.');
   }

  
   public function show(string $id)
   {
       $kebijakan = KebijakanTikKemlu::findOrFail($id);
       return view('kebijakan-kemlu.show', compact('kebijakan'));
   }

  
   public function edit(string $id)
   {
       $kebijakan = KebijakanTikKemlu::findOrFail($id);
       return view('kebijakan-kemlu.edit', compact('kebijakan')); 
   }

   
   public function update(Request $request, string $id)
   {
       $request->validate([
           'jenis_kebijakan' => 'required',
           'nomor_kebijakan' => 'required', 
           'tahun_penerbitan' => 'required|numeric|min:1900|max:' . (date('Y') + 1),
           'perihal_kebijakan' => 'required',
           'tautan' => 'nullable|url'
       ]);

       $kebijakan = KebijakanTikKemlu::findOrFail($id);
       $kebijakan->update($request->all());

       return redirect()->route('admin.kebijakan-kemlu.index')
           ->with('success', 'Kebijakan TIK Kemlu berhasil diperbarui.');
   }

   
   public function destroy(string $id)
   {
       $kebijakan = KebijakanTikKemlu::findOrFail($id);
       $kebijakan->delete();

       return redirect()->route('admin.kebijakan-kemlu.index')
           ->with('success', 'Kebijakan TIK Kemlu berhasil dihapus.');
   }

  
   public function search(Request $request)
   {
       $search = $request->get('search');
       
       $kebijakan = KebijakanTikKemlu::where('jenis_kebijakan', 'like', '%'.$search.'%')
           ->orWhere('nomor_kebijakan', 'like', '%'.$search.'%')
           ->orWhere('perihal_kebijakan', 'like', '%'.$search.'%')
           ->paginate(10);

       return view('kebijakan-kemlu.index', compact('kebijakan'));
   }

   
   public function filter(Request $request)
   {
       $year = $request->get('year');
       
       $kebijakan = KebijakanTikKemlu::where('tahun_penerbitan', $year)
           ->paginate(10);

       return view('kebijakan-kemlu.index', compact('kebijakan'));
   }

   public function guestIndex()
{
    $kebijakan = KebijakanTikKemlu::latest()->paginate(10);
    $title = 'Kebijakan TIK by Kemlu';

    return view('ktbk', compact('kebijakan', 'title'));
}


}