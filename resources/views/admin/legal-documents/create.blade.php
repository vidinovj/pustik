{{-- resources/views/admin/legal-documents/create.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Create New Document</h1>

    @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- File Upload Form -->
    <form action="{{ route('admin.legal-documents.store') }}" method="POST" enctype="multipart/form-data" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
        @csrf

        {{-- Document Source Selection --}}
        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="document_source_id">
                Document Source:
            </label>
            <select name="document_source_id" id="document_source_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:shadow-outline" required>
                <option value="">-- Select Source --</option>
                @foreach($documentSources as $source)
                    <option value="{{ $source->id }}" {{ old('document_source_id') == $source->id ? 'selected' : '' }}>
                        {{ $source->name }} ({{ ucfirst($source->type) }})
                    </option>
                @endforeach
            </select>
        </div>

        {{-- File Upload Section --}}
        <div class="mb-6 p-4 border-2 border-dashed border-gray-300 rounded-lg">
            <h3 class="text-lg font-semibold mb-3">Upload Document File</h3>
            
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="document_file">
                    Choose File (PDF, DOC, DOCX):
                </label>
                <input type="file" name="document_file" id="document_file" 
                       accept=".pdf,.doc,.docx" 
                       class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                <p class="text-xs text-gray-500 mt-1">Maximum file size: 10MB. Supported formats: PDF, DOC, DOCX</p>
            </div>

            <div class="text-center text-gray-500">
                <p class="mb-2">OR</p>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="source_url">
                    External URL (Optional):
                </label>
                <input type="url" name="source_url" id="source_url" value="{{ old('source_url') }}" 
                       class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:shadow-outline"
                       placeholder="https://example.com/document.pdf">
            </div>
        </div>

        {{-- Document Metadata --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="title">
                    Title: *
                </label>
                <input type="text" name="title" id="title" value="{{ old('title') }}" required
                       class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:shadow-outline">
            </div>

            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="document_type">
                    Document Type: *
                </label>
                <select name="document_type" id="document_type" required
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:shadow-outline">
                    <option value="">-- Select Type --</option>
                    
                    {{-- Pusdatin Internal Document Types --}}
                    <optgroup label="Pusdatin Internal Documents">
                        <option value="Nota Kesepahaman - MOU" {{ old('document_type') == 'Nota Kesepahaman - MOU' ? 'selected' : '' }}>Nota Kesepahaman - MOU</option>
                        <option value="Nota Kesepahaman - PKS" {{ old('document_type') == 'Nota Kesepahaman - PKS' ? 'selected' : '' }}>Nota Kesepahaman - PKS</option>
                        <option value="Dokumen Lainnya" {{ old('document_type') == 'Dokumen Lainnya' ? 'selected' : '' }}>Dokumen Lainnya</option>
                        <option value="MoU" {{ old('document_type') == 'MoU' ? 'selected' : '' }}>MoU</option>
                        <option value="PKS" {{ old('document_type') == 'PKS' ? 'selected' : '' }}>PKS</option>
                        <option value="Agreement" {{ old('document_type') == 'Agreement' ? 'selected' : '' }}>Agreement</option>
                        <option value="Surat Edaran" {{ old('document_type') == 'Surat Edaran' ? 'selected' : '' }}>Surat Edaran</option>
                        <option value="Pedoman" {{ old('document_type') == 'Pedoman' ? 'selected' : '' }}>Pedoman</option>
                        <option value="SOP" {{ old('document_type') == 'SOP' ? 'selected' : '' }}>Standard Operating Procedure</option>
                    </optgroup>
                    
                    {{-- External Policy Types --}}
                    <optgroup label="External Policy Documents">
                        <option value="Undang-Undang" {{ old('document_type') == 'Undang-Undang' ? 'selected' : '' }}>Undang-Undang</option>
                        <option value="Peraturan Pemerintah" {{ old('document_type') == 'Peraturan Pemerintah' ? 'selected' : '' }}>Peraturan Pemerintah</option>
                        <option value="Peraturan Presiden" {{ old('document_type') == 'Peraturan Presiden' ? 'selected' : '' }}>Peraturan Presiden</option>
                        <option value="Peraturan Menteri" {{ old('document_type') == 'Peraturan Menteri' ? 'selected' : '' }}>Peraturan Menteri</option>
                    </optgroup>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="document_number">
                    Document Number:
                </label>
                <input type="text" name="document_number" id="document_number" value="{{ old('document_number') }}"
                       class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:shadow-outline">
            </div>

            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="issue_year">
                    Issue Year:
                </label>
                <input type="number" name="issue_year" id="issue_year" value="{{ old('issue_year', date('Y')) }}" 
                       min="1900" max="{{ date('Y') + 1 }}"
                       class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:shadow-outline">
            </div>
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="status">
                Status:
            </label>
            <select name="status" id="status" class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:shadow-outline">
                <option value="draft" {{ old('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                <option value="pending" {{ old('status') == 'pending' ? 'selected' : '' }}>Pending</option>
            </select>
        </div>

        {{-- Optional Text Content --}}
        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="full_text">
                Document Summary/Notes (Optional):
            </label>
            <textarea name="full_text" id="full_text" rows="4" 
                      class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:shadow-outline"
                      placeholder="Enter document summary, key points, or notes...">{{ old('full_text') }}</textarea>
        </div>

        <div class="flex items-center justify-between">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Create Document
            </button>
            <a href="{{ route('admin.legal-documents.index') }}" class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800">
                Cancel
            </a>
        </div>
    </form>
</div>

<script>
// Auto-populate title from filename
document.getElementById('document_file').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const titleField = document.getElementById('title');
    
    if (file && !titleField.value) {
        // Remove file extension and clean up filename
        let filename = file.name.replace(/\.[^/.]+$/, "");
        filename = filename.replace(/[-_]/g, ' ');
        filename = filename.replace(/\b\w/g, l => l.toUpperCase());
        titleField.value = filename;
    }
});
</script>
@endsection