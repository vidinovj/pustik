<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DocumentFilterService
{
    public function apply(Request $request, Builder $query): Builder
    {
        // General Filters
        if ($request->filled('jenis_kebijakan')) {
            $query->where('document_type', 'like', '%'.$request->jenis_kebijakan.'%');
        }
        if ($request->filled('nomor_kebijakan')) {
            $query->where('document_number', 'like', '%'.$request->nomor_kebijakan.'%');
        }
        if ($request->filled('perihal_kebijakan')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%'.$request->perihal_kebijakan.'%')
                    ->orWhere('full_text', 'like', '%'.$request->perihal_kebijakan.'%');
            });
        }
        if ($request->filled('instansi')) {
            $query->where('metadata->agency', 'like', '%'.$request->instansi.'%');
        }

        // Year Range Filters
        if ($request->filled('tahun_penerbitan_min')) {
            $query->where('issue_year', '>=', $request->tahun_penerbitan_min);
        }
        if ($request->filled('tahun_penerbitan_max')) {
            $query->where('issue_year', '<=', $request->tahun_penerbitan_max);
        }
        if ($request->filled('tahun_penerbitan_from') && $request->filled('tahun_penerbitan_to')) {
            $query->whereBetween('issue_year', [
                $request->tahun_penerbitan_from,
                $request->tahun_penerbitan_to,
            ]);
        }
        if ($request->filled('tahun_penerbitan')) {
            $query->where('issue_year', $request->tahun_penerbitan);
        }

        // Pusdatin Specific Filters
        if ($request->filled('jenis_dokumen')) {
            $query->where('document_type', 'like', '%'.$request->jenis_dokumen.'%');
        }
        if ($request->filled('perihal_dokumen')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%'.$request->perihal_dokumen.'%')
                    ->orWhere('full_text', 'like', '%'.$request->perihal_dokumen.'%');
            });
        }
        if ($request->filled('satker_kemlu_terkait')) {
            $query->where('metadata->satker_kemlu_terkait', 'like', '%'.$request->satker_kemlu_terkait.'%');
        }
        if ($request->filled('kl_external_terkait')) {
            $query->where('metadata->kl_external_terkait', 'like', '%'.$request->kl_external_terkait.'%');
        }
        if ($request->filled('start_date_disahkan') && $request->filled('end_date_disahkan')) {
            $query->whereBetween('issue_year', [
                $request->start_date_disahkan,
                $request->end_date_disahkan,
            ]);
        }
        if ($request->filled('start_date_berakhir') && $request->filled('end_date_berakhir')) {
            $query->whereBetween('metadata->tanggal_berakhir', [
                $request->start_date_berakhir,
                $request->end_date_berakhir,
            ]);
        }

        // Sorting
        if ($request->filled('sort_by')) {
            $sortOrder = $request->input('sort_order', 'asc');
            $sortBy = $request->input('sort_by');

            switch ($sortBy) {
                case 'nomor_kebijakan':
                    $query->orderBy('document_number', $sortOrder);
                    break;
                case 'tahun_penerbitan':
                    $query->orderBy('issue_year', $sortOrder);
                    break;
                case 'tanggal_disahkan_asc':
                    $query->orderBy('issue_year', 'asc');
                    break;
                case 'tanggal_disahkan_desc':
                    $query->orderBy('issue_year', 'desc');
                    break;
                case 'tanggal_berakhir_asc':
                    $query->orderBy('metadata->tanggal_berakhir', 'asc');
                    break;
                case 'tanggal_berakhir_desc':
                    $query->orderBy('metadata->tanggal_berakhir', 'desc');
                    break;
                case 'upload_terbaru':
                    $query->orderBy('uploaded_at', 'desc')->orderBy('updated_at', 'desc');
                    break;
            }
        }

        return $query;
    }
}
