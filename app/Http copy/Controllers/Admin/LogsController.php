<?php

namespace App\Http\Controllers\Admin;

use App\Models\Log;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\SessionService;
use App\Services\LogReportService;
use App\Http\Controllers\Controller;

class LogsController extends Controller
{
    protected $logModel;
    protected $logReportService;
    protected $sessionService;

    public function __construct(
        Log $logModel, 
        LogReportService $logReportService,
        SessionService $sessionService)
    {
        $this->logModel = $logModel;
        $this->logReportService = $logReportService;
        $this->sessionService = $sessionService;
    }
    
    public function logsActionsList(Request $request)
    {
        $users = User::all();
        $_entities = Log::select('entity')->distinct()->pluck('entity')->toArray();
        $entities = array_filter($_entities);
        $_actions = Log::select('action')->distinct()->pluck('action')->toArray();
        $actions = array_filter($_actions);
        
        $obj = (object) [
            "user_id"           => $request->input('user_id'),
            "date_from"         => $request->input('date_from'),
            "date_to"           => $request->input('date_to'),
            "order_number"      => $request->input('order_number'),
            "action"            => $request->input('action'),
            "per_page"          => $request->input('per_page', 10),
            "entity"            => $request->input('entity'),
        ];
        
        $query = Log::query();
        $logs = $this->logModel->handleActionLogFilters($query, $obj);

        $logs = $query->with('user')->orderBy('created_at', 'desc')
                  ->paginate($obj->per_page)
                  ->appends($request->except('page'));

        return view('logs.actions-list', compact('logs', 'users', 'entities', 'actions'));
    }

    public function downloadActionsLogsReport(Request $request)
    {
        try {
            $obj = (object) [
                "user_id"       => $request->query('user_id'),
                "date_from"     => $request->query('date_from'),
                "date_to"       => $request->query('date_to'),
                "order_number"  => $request->query('order_number'),
                "action"        => $request->query('action')
            ];

            return $this->logReportService->exportActionLogToCsv($obj);
        } catch (\Exception $e) {
            return redirect()->route('logsActionsList')->with('error', __('messages/controller.main.error', ['message' => $e->getMessage()]));
        }
    }

    public function logsConnectionsList(Request $request)
    {
        $users = User::all();

        $obj = (object) [
            "user_id"       => $request->input('user_id'),
            "date_from"     => $request->input('date_from'),
            "date_to"       => $request->input('date_to'),
            "order_number"  => $request->input('order_number'),
            "action"        => $request->input('action')
        ];

        $this->sessionService->saveFiltersToSession('logs_connections_filters', $request, $obj);

        $logs = $this->logModel->handleConnectionLogFilters($obj);

        return view('logs.connections-list', compact('logs', 'users'));
    }

    public function downloadConnectionsLogsReport(Request $request)
    {
        try {
            $obj = (object) [
                "user_id"       => $request->query('user_id'),
                "date_from"     => $request->query('date_from'),
                "date_to"       => $request->query('date_to'),
                "action"        => $request->query('action')
            ];

            return $this->logReportService->exportConnectionLogToCsv($obj);
        } catch (\Exception $e) {
            return redirect()->route('logsActionsList')->with('error', __('messages/controller.main.error', ['message' => $e->getMessage()]));
        }
    }

}
