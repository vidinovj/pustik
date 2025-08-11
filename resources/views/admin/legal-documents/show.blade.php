@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Legal Document Details</h1>

    <div class="bg-white shadow-md rounded-lg p-6 mb-6">
        <div class="mb-4">
            <p class="text-gray-700 text-sm font-bold">Title:</p>
            <p class="text-gray-900 text-lg">{{ $document->title }}</p>
        </div>

        <div class="mb-4">
            <p class="text-gray-700 text-sm font-bold">Document Type:</p>
            <p class="text-gray-900">{{ $document->document_type }}</p>
        </div>

        <div class="mb-4">
            <p class="text-gray-700 text-sm font-bold">Document Number:</p>
            <p class="text-gray-900">{{ $document->document_number ?? 'N/A' }}</p>
        </div>

        <div class="mb-4">
            <p class="text-gray-700 text-sm font-bold">Issue Date:</p>
            <p class="text-gray-900">{{ $document->issue_date ? $document->issue_date->format('Y-m-d') : 'N/A' }}</p>
        </div>

        <div class="mb-4">
            <p class="text-gray-700 text-sm font-bold">Source URL:</p>
            <p class="text-gray-900"><a href="{{ $document->source_url }}" target="_blank" class="text-blue-500 hover:underline">{{ $document->source_url ?? 'N/A' }}</a></p>
        </div>

        <div class="mb-4">
            <p class="text-gray-700 text-sm font-bold">Status:</p>
            <p class="text-gray-900">{{ ucfirst($document->status) }}</p>
        </div>

        <div class="mb-4">
            <p class="text-gray-700 text-sm font-bold">Full Text:</p>
            <div class="bg-gray-100 p-4 rounded-md max-h-64 overflow-y-auto">
                <p class="text-gray-800 whitespace-pre-wrap">{{ $document->full_text ?? 'N/A' }}</p>
            </div>
        </div>

        <div class="mb-4">
            <p class="text-gray-700 text-sm font-bold">Metadata:</p>
            <div class="bg-gray-100 p-4 rounded-md max-h-64 overflow-y-auto">
                <pre class="text-gray-800 text-sm">{{ json_encode($document->metadata, JSON_PRETTY_PRINT) }}</pre>
            </div>
        </div>

        <div class="flex justify-end mt-6">
            <a href="{{ route('admin.legal-documents.edit', $document->id) }}" class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded mr-2">
                Edit
            </a>
            <form action="{{ route('admin.legal-documents.destroy', $document->id) }}" method="POST" class="inline-block">
                @csrf
                @method('DELETE')
                <button type="submit" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded" onclick="return confirm('Are you sure you want to delete this document?');">
                    Delete
                </button>
            </form>
        </div>
    </div>

    <div class="flex justify-start">
        <a href="{{ route('admin.legal-documents.index') }}" class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800">
            Back to List
        </a>
    </div>
</div>
@endsection
