@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Edit Legal Document</h1>

    @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Whoops!</strong>
            <span class="block sm:inline">There were some problems with your input.</span>
            <ul class="mt-3 list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.legal-documents.update', $document->id) }}" method="POST" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
        @csrf
        @method('PUT')

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="title">
                Title:
            </label>
            <input type="text" name="title" id="title" value="{{ old('title', $document->title) }}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="document_type">
                Document Type:
            </label>
            <input type="text" name="document_type" id="document_type" value="{{ old('document_type', $document->document_type) }}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="document_number">
                Document Number:
            </label>
            <input type="text" name="document_number" id="document_number" value="{{ old('document_number', $document->document_number) }}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="issue_date">
                Issue Date:
            </label>
            <input type="date" name="issue_date" id="issue_date" value="{{ old('issue_date', $document->issue_date ? $document->issue_date->format('Y-m-d') : '') }}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="source_url">
                Source URL:
            </label>
            <input type="url" name="source_url" id="source_url" value="{{ old('source_url', $document->source_url) }}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="full_text">
                Full Text:
            </label>
            <textarea name="full_text" id="full_text" rows="5" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">{{ old('full_text', $document->full_text) }}</textarea>
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="status">
                Status:
            </label>
            <select name="status" id="status" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <option value="active" {{ old('status', $document->status) == 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ old('status', $document->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                <option value="pending" {{ old('status', $document->status) == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="draft" {{ old('status', $document->status) == 'draft' ? 'selected' : '' }}>Draft</option>
            </select>
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="metadata">
                Metadata (JSON):
            </label>
            <textarea name="metadata" id="metadata" rows="5" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">{{ old('metadata', json_encode($document->metadata, JSON_PRETTY_PRINT)) }}</textarea>
            <p class="text-xs text-gray-500 mt-1">Enter metadata as a valid JSON string (e.g., {"agency": "Example Agency"}).</p>
        </div>

        <div class="flex items-center justify-between">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Update Document
            </button>
            <a href="{{ route('admin.legal-documents.index') }}" class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
