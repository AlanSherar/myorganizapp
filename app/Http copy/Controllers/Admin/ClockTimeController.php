<?php

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Site;
use App\Models\BreakTime;
use App\Models\ClockTime;
use Illuminate\Http\Request;
use App\Services\BreakTimeService;
use App\Services\ClockTimeService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class ClockTimeController extends Controller
{
    protected $clockTimeService;
    protected $breakTimeService;

    public function __construct(ClockTimeService $_clockTimeService, BreakTimeService $_breakTimeService)
    {
        $this->clockTimeService = $_clockTimeService;
        $this->breakTimeService = $_breakTimeService;
    }

    public function clockTimeEmployees(Request $request)
    {
        try {
            $site_id = $request->site_id ?? null;
            $site_selected = null;
            $users = [];
            $sites = [];
            //$latestClockTimes = ClockTime::select(DB::raw('MAX(id) as id'))->groupBy('user_id')->get();

            if (Auth::user()->role_id == 4) {
                $sites = [];
                $site_id = Auth::user()->site_id;

                $site_selected = Site::find($site_id);

                $users = User::where('site_id', $site_id)
                    ->whereExists(
                        function ($query) use ($site_id) {
                            $query->select(DB::raw(1))
                                ->from('clock_times')
                                ->whereColumn('clock_times.user_id', 'users.id');
                        }
                    )->paginate(10);

                return view('clock-time.employees-list', compact('users', 'sites', 'site_selected'));
            }

            $sites = Site::all();

            if ($site_id == 'all' || !$site_id) {
                $users = User::whereExists(
                    function ($query) {
                        $query->select(DB::raw(1))
                            ->from('clock_times')
                            ->whereColumn('clock_times.user_id', 'users.id');
                    }
                )->paginate(10);
            } else {
                $users = User::where('site_id', $site_id)
                    ->whereExists(
                        function ($query) use ($site_id) {
                            $query->select(DB::raw(1))
                                ->from('clock_times')
                                ->whereColumn('clock_times.user_id', 'users.id');
                        }
                    )->paginate(10);

                $site_selected = Site::find($site_id);
            }

            return view('clock-time.employees-list', compact('users', 'sites', 'site_selected'));
        } catch (\Throwable $th) {
            return redirect()->route('dashboard')->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    }

    public function checkClockStatus($secret_pin)
    {
        try {
            $user = [];

            if ($secret_pin) {
                $user = User::with('site')
                    ->where('secret_pin', $secret_pin)
                    ->whereNotNull('site_id')->first();
            } elseif (Auth::check()) {
                $user_id = Auth::user()->id;
                $user = User::with('site')->find($user_id);
            }

            if (!$user) {
                return response()->json([
                    'status'    => 'error',
                    'message'    => __('messages/controller.admin.clock_time.error.user_not_found'),
                ]);
            }

            $action = $this->clockTimeService->getClockStatus($user);

            if ($action) {
                return response()->json([
                    'user'      => $user->name,
                    'action'    => $action
                ]);
            }

            return response()->json([
                'status'        => 'error',
                'message'       =>  __('messages/controller.admin.clock_time.error.contact_support')
            ]);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()]);
        }
    }

    public function clockActionPin(Request $request)
    {
        $secret_pin = $request->input('secret_pin');
        $action = $request->input('action_type');

        $obj = $this->clockTimeService->buildClockObj($secret_pin);

        if ($action === 'clock_in') {
            return $this->clockTimeService->handleClockInTime($obj);
        } elseif ($action === 'clock_out') {
            return $this->clockTimeService->handleClockOutTime($obj);
        } elseif ($action === 'break_in') {
            return $this->breakTimeService->handleBreakInTime($obj);
        } elseif ($action === 'break_out') {
            return $this->breakTimeService->handleBreakOutTime($obj);
        } else {
            return back()->with('error', __('messages/controller.admin.clock_time.error.contact_support'));
        }
    }

    public function clockInTime()
    {
        try {
            $obj = $this->clockTimeService->buildClockObj();

            return $this->clockTimeService->handleClockInTime($obj);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages/controller.admin.clock_time.error.unexpected'),
                'debug' => $e->getMessage() // à enlever en production
            ], 500);
        }
    }

    public function clockOutTime()
    {
        try {
            $obj = $this->clockTimeService->buildClockObj();

            return $this->clockTimeService->handleClockOutTime($obj);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages/controller.admin.clock_time.error.unexpected'),
                'debug' => $e->getMessage()
            ], 500);
        }
    }

    public function breakInTime()
    {
        try {
            $obj = $this->clockTimeService->buildClockObj();

            return $this->breakTimeService->handleBreakInTime($obj);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages/controller.admin.clock_time.error.unexpected'),
                'debug' => $e->getMessage() // à enlever en production
            ], 500);
        }
    }

    public function breakOutTime()
    {
        try {
            $obj = $this->clockTimeService->buildClockObj();

            return $this->breakTimeService->handleBreakOutTime($obj);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages/controller.admin.clock_time.error.unexpected'),
                'debug' => $e->getMessage()
            ], 500);
        }
    }

    public function clockTimeDetails($user_id)
    {
        try {
            $user = User::with('site')->with('role')->where('id', $user_id)->first();
            $sites = Site::where('active', 1)->get();

            $time_worked_result = ClockTime::with('user.site')
                ->where('user_id', $user_id)
                ->orderBy('clock_in', 'desc')
                ->paginate(10);

            $data = $this->clockTimeService->handleTimeWorkedList($time_worked_result);

            return view('clock-time.employees-details', compact('data', 'user', 'sites'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    }

    public function clockTimeEdit($id)
    {
        try {
            $break_times = [];
            $clock_time = null;
            $clock_time_id = $id;

            $clock_time = ClockTime::find($clock_time_id);

            if (!$clock_time) {
                return redirect()->back()->with('error', __('messages/controller.admin.clock_time.error.timestamp_not_found'));
            }

            $user = User::with('site')->find($clock_time->user_id);

            if (!$user) {
                return redirect()->back()->with('error', __('messages/controller.admin.clock_time.error.user_not_found'));
            }

            $date = Carbon::parse($clock_time->clock_in)->format('Y-m-d');

            $valueStart = $date . ' 00:00:00';
            $valueEnd = $date . ' 23:59:59';

            $break_times = BreakTime::where('user_id', (string) $user->id)
                ->whereBetween('break_in', [$valueStart, $valueEnd])
                ->whereNotNull('break_out')
                ->get();

            return view('clock-time.clock-time-edit', compact('clock_time', 'user', 'clock_time_id', 'break_times'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    }

    public function clockTimeUpdate(Request $request)
    {
        try {
            $request->validate([
                'date'        => 'required|date_format:m-d-Y',
                "start_time"  => "required|date_format:H:i",
                "end_time"    => "required|date_format:H:i",
                "user_id"     => "required|integer"
            ]);
            
            $user_id            = $request->user_id;
            $date               = $request->date;
            $start_time         = $request->start_time;
            $end_time           = $request->end_time;
            $clock_time_id      = $request->clock_time_id;
            $break_times        = $request->break_times;

            $date_formatted = Carbon::createFromFormat('m-d-Y', $date)->format('Y-m-d');

            $clock_in  = $date_formatted . ' ' . $start_time;
            $clock_out = $date_formatted . ' ' . $end_time;  

            if (strtotime($clock_in) >= strtotime($clock_out)) {
                return redirect()->back()->withInput()->withErrors([
                    'end_time' => __('messages/controller.admin.clock_time.error.end_time_after_start_time'),
                ]);
            }

            $user = User::with("site")->where("id", $user_id)->first();
            $timezone = $user->site->timezone;
            //$now = Carbon::now($user->site->timezone);

            $clockIn = Carbon::parse($clock_in, $timezone);
            $clockOut = Carbon::parse($end_time, $timezone);
            $seconds = $clockIn->diffInSeconds($clockOut);

            ClockTime::where('id', $clock_time_id)->update([
                'clock_in'      => $clock_in,
                'clock_out'     => $clock_out,
                'total'         => (int) $seconds
            ]);

            if ($break_times) {
                foreach ($break_times as $index => $break) {
                    if (empty($break['break_in']) || empty($break['break_out'])) {
                        continue;
                    }
                    
                    $break_in  = $date_formatted . ' ' . $break['break_in'];
                    $break_out = $date_formatted . ' ' . $break['break_out'];

                    $breakIn = Carbon::parse($break_in, $timezone);
                    $breakOut = Carbon::parse($break_out, $timezone);
                    $seconds = $breakIn->diffInSeconds($breakOut);

                    if (strtotime($break_in) >= strtotime($break_out)) {
                        return redirect()->back()->withInput()->withErrors([
                            'end_time' => __('messages/controller.admin.clock_time.error.break_out_time_after_break_in_time'),
                        ]);
                    }

                    if(is_numeric($index)){
                        BreakTime::where('id', $index)
                        ->update([
                            'break_in'  => $break_in,
                            'break_out' => $break_out,
                            'total'     => $seconds
                        ]);
                    }

                    if ($index === "new") {
                        BreakTime::create([
                            'user_id'       => $user_id,
                            'break_in'      => $break_in,
                            'break_out'     => $break_out,
                            'site_id'       => $user->site_id,
                            'total'         => $seconds
                        ]);
                    }
                }
            }

            return redirect()
                ->route('clockTimeDetails', ['user_id' => $user_id])
                ->with('success', __('messages/controller.admin.clock_time.update.success'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    }

    public function clockTimeDelete(Request $request)
    {
        try {
            $clock_id = $request->clock_id;

            if (!$clock_id) {
                return redirect()->back()->with('error', __('messages/controller.admin.clock_time.error.id_missing_deletion'));
            }

            $clockTime = ClockTime::find($clock_id);

            if (!$clockTime) {
                return redirect()->back()->with('error', __('messages/controller.admin.clock_time.error.reference_not_found'));
            }

            ClockTime::where('id', $clock_id)->delete();

            return redirect()->back()->with('success', __('messages/controller.admin.clock_time.delete.success'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    }

    public function clockTime()
    {
        try {
            if(Auth::check()){
                Auth::logout();
            }

            return view('layouts.clock-time');
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages/controller.admin.clock_time.error.unexpected'),
                'debug' => $e->getMessage() // à enlever en production
            ], 500);
        }
    }

    public function clockTimeReport(Request $request)
    {
        try {
            $response = $this->clockTimeService->handleClockTimeReport($request);
            //return $response;
            $records = collect($response['data'])
                //->where('status', 'work')
                ->map(function ($row) {
                    $clockIn = $row['clock_in'] ? \Carbon\Carbon::parse($row['clock_in']) : null;

                    return [
                        'name'         => $row['user_name'],
                        'clock_in'     => $row['clock_in'] ? \Carbon\Carbon::parse($row['clock_in'])->format('h:i A') : '-',
                        'break_in'     => $row['break_in'] ? \Carbon\Carbon::parse($row['break_in'])->format('h:i A') : '-',
                        'break_out'    => $row['break_out'] ? \Carbon\Carbon::parse($row['break_out'])->format('h:i A') : '-',
                        'clock_out'    => $row['clock_out'] ? \Carbon\Carbon::parse($row['clock_out'])->format('h:i A') : '-',
                        'worked_daily' => $row['worked_hours'] 
                                            ? str_replace(':', '.', substr($row['worked_hours'], 0, 5)) 
                                            : '-',
                        'breaked_daily' => $row['break_hours'] 
                                            ? str_replace(':', '.', substr($row['break_hours'], 0, 5)) 
                                            : '-',

                                            
                        'date' => $row['clock_in'] 
                            ? \Carbon\Carbon::parse($row['clock_in'])->locale('en')->isoFormat('dddd D MMMM') 
                            : null,
                        'date_raw'      => $clockIn,
                    ];
                })
                // 1. trier d’abord par nom puis par date puis par heure d’entrée
                ->sortBy([
                    ['name', 'asc'],
                    ['date_raw', 'asc'],
                    ['clock_in', 'asc'],
                ])
                ->values();

            $callback = function() use ($records) {
                $file = fopen('php://output', 'w');

                $lastUser = null;
                $totalWorked = 0; // Somme hebdo pour l'utilisateur

                foreach ($records as $r) {
                    // Quand on change d'utilisateur
                    if ($lastUser !== $r['name']) {
                        // Avant de passer au suivant → écrire la ligne du total
                        if ($lastUser !== null) {
                            fputcsv($file, []); 
                            fputcsv($file, ['', '', '', '', '', 'Total Worked Hours (Weekly)', number_format($totalWorked, 2, '.', '')]);
                            fputcsv($file, []); 
                        }

                        // Réinitialiser le total pour le nouvel utilisateur
                        $totalWorked = 0;

                        // Écrire le header avec le nom de l’utilisateur
                        fputcsv($file, [$r['name'], 'Clock-In', 'Break-In', 'Break-Out', 'Clock-Out', 'Total Break', 'Total Worked (Daily)']);
                    }

                    // Ajouter la valeur du jour au total (si non vide)
                    if (!empty($r['worked_daily'])) {
                        $totalWorked += (float) str_replace(',', '.', $r['worked_daily']);
                    }

                    // Écrire la ligne de données
                    fputcsv($file, [
                        $r['date'],
                        $r['clock_in'] ?: '-',
                        $r['break_in'] ?: '-',
                        $r['break_out'] ?: '-',
                        $r['clock_out'] ?: '-',
                        $r['breaked_daily'] ?: '-',
                        $r['worked_daily'] ?: '-',
                    ]);

                    $lastUser = $r['name'];
                }

                // ⚡ Écrire le total du dernier utilisateur
                if ($lastUser !== null) {
                    fputcsv($file, []); 
                    fputcsv($file, ['', '', '', '', '', 'Total Worked Hours (Weekly)', number_format($totalWorked, 2, '.', '')]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=clock_time_report.csv"
            ]);
        } catch (\Throwable $th) {
            Log::error('Error exporting ClockTime PDF: ' . $th->getMessage());
            return redirect()->back()->with('error', 'Error exporting ClockTime report.');
        }
    }

    public function getBreakTimes(Request $request)
    {
        $userId = $request->query('user_id');
        $date   = $request->query('clock_in');

        $breaks = BreakTime::where('user_id', $userId)
            ->whereDate('break_in', $date)
            ->get();

        $totalMinutes = 0;

        $items = $breaks->map(function ($break) use (&$totalMinutes) {
            $in  = \Carbon\Carbon::parse($break->break_in);
            $out = $break->break_out ? \Carbon\Carbon::parse($break->break_out) : null;

            $duration = '-';
            if ($out) {
                $diff = $in->diffInMinutes($out);   // minutes entières
                $totalMinutes += $diff;

                $h = intdiv($diff, 60);
                $m = $diff % 60;
                $duration = $h > 0 ? "{$h}h {$m} min" : "{$m} min";
            }

            return [
                'break_in'   => $in->format('g:i A'),               // ex: 3:31 PM
                'break_out'  => $out ? $out->format('g:i A') : null,
                'duration'   => $duration,
                // optionnel: valeurs brutes si besoin côté front
                'raw_break_in'  => $break->break_in,
                'raw_break_out' => $break->break_out,
            ];
        });

        $totalH = intdiv($totalMinutes, 60);
        $totalM = $totalMinutes % 60;
        $totalFormatted = ($totalH > 0 ? "{$totalH}h " : "") . "{$totalM} min";

        return response()->json([
            'items' => $items,
            'total' => [
                'minutes'   => $totalMinutes,
                'hours'     => $totalH,
                'mins'      => $totalM,
                'formatted' => $totalFormatted,
            ],
        ]);
    }

}
