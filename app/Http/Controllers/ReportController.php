<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reports\ExportOutboxRequest;
use App\Http\Requests\Reports\ListOutboxRequest;
use App\Services\ReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Http\Requests\Reports\ExportLogRequest;
use App\Http\Requests\Reports\ListLogRequest;
use App\Http\Requests\Reports\ListSummaryRequest;
use App\Http\Requests\Reports\ListActivityRequest;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    public function outbox(ListOutboxRequest $request): View
    {
        return view('reports.outbox', $this->reports->outboxPage($request->filters()));
    }

    public function outboxExport(ExportOutboxRequest $request): StreamedResponse
    {
        return $this->reports->exportOutbox($request->filters());
    }

        public function log(ListLogRequest $request): View
    {
        return view('reports.log', $this->reports->logPage($request->definition(), $request->filters()));
    }

    public function logExport(ExportLogRequest $request): StreamedResponse
    {
        return $this->reports->exportLog($request->definition(), $request->filters());
    }

        public function summary(ListSummaryRequest $request): View
    {
        return view('reports.summary', $this->reports->summaryPage($request->definition(), $request->filters()));
    }

    public function summaryExport(ListSummaryRequest $request): StreamedResponse
    {
        return $this->reports->exportSummary($request->definition(), $request->filters());
    }
    
        public function activity(ListActivityRequest $request): View
    {
        return view('reports.activity', $this->reports->activityPage($request->filters()));
    }
}