<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\NormalizeDocuments;
use App\Jobs\ScrapeDocuments;
use Illuminate\Http\Request;

class JobController extends Controller
{
    public function scrape()
    {
        ScrapeDocuments::dispatch();

        return redirect()->route('admin.legal-documents.index')->with('success', 'Scraping job has been started.');
    }

    public function normalize()
    {
        NormalizeDocuments::dispatch();

        return redirect()->route('admin.legal-documents.index')->with('success', 'Normalization job has been started.');
    }
}