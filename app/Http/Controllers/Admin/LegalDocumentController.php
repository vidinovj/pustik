<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LegalDocumentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $documents = LegalDocument::latest()->paginate(10);
        return view('admin.legal-documents.index', compact('documents'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.legal-documents.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'document_type' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:255',
            'issue_date' => 'nullable|date',
            'source_url' => 'nullable|url|max:255',
            'full_text' => 'nullable|string',
            'status' => 'required|string|in:active,inactive,pending,draft',
            // Metadata can be a JSON string or an array
            'metadata' => 'nullable|json',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $request->all();
        $data['metadata'] = json_decode($data['metadata'] ?? '{}', true); // Ensure metadata is an array
        $data['checksum'] = md5($data['title'] . $data['document_type'] . $data['document_number'] . $data['issue_date']);

        LegalDocument::create($data);

        return redirect()->route('admin.legal-documents.index')->with('success', 'Legal document created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(LegalDocument $document)
    {
        return view('admin.legal-documents.show', compact('document'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(LegalDocument $document)
    {
        return view('admin.legal-documents.edit', compact('document'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LegalDocument $document)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'document_type' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:255',
            'issue_date' => 'nullable|date',
            'source_url' => 'nullable|url|max:255',
            'full_text' => 'nullable|string',
            'status' => 'required|string|in:active,inactive,pending,draft',
            'metadata' => 'nullable|json',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $request->all();
        $data['metadata'] = json_decode($data['metadata'] ?? '{}', true); // Ensure metadata is an array
        $data['checksum'] = md5($data['title'] . $data['document_type'] . $data['document_number'] . $data['issue_date']);

        $document->update($data);

        return redirect()->route('admin.legal-documents.index')->with('success', 'Legal document updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LegalDocument $document)
    {
        $document->delete();
        return redirect()->route('admin.legal-documents.index')->with('success', 'Legal document deleted successfully.');
    }
}
