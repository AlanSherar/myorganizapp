<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $tasks = Task::where('user_id', Auth::id())
                     ->orderBy('is_routine', 'desc')
                     ->orderBy('routine_time', 'asc')
                     ->orderBy('due_date', 'asc')
                     ->get();

        return view('tasks.index', compact('tasks'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'priority' => 'required|in:low,medium,high',
            'is_routine' => 'boolean',
            'routine_type' => 'nullable|required_if:is_routine,1|in:daily,monthly',
            'routine_day' => 'nullable|required_if:routine_type,monthly|integer|min:1|max:31',
            'routine_time' => 'nullable|required_if:is_routine,1',
            'due_date' => 'nullable|required_if:is_routine,0|date',
            'start_date' => 'nullable|date',
            'expected_end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $data = $request->all();
        $data['user_id'] = Auth::id();
        $data['is_routine'] = $request->has('is_routine');

        Task::create($data);

        return redirect()->back()->with('success', 'Task created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Task $task)
    {
        if ($task->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'priority' => 'required|in:low,medium,high',
            'is_routine' => 'boolean',
            'routine_type' => 'nullable|required_if:is_routine,1|in:daily,monthly',
            'routine_day' => 'nullable|required_if:routine_type,monthly|integer|min:1|max:31',
            'routine_time' => 'nullable|required_if:is_routine,1',
            'due_date' => 'nullable|required_if:is_routine,0|date',
            'start_date' => 'nullable|date',
            'expected_end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $data = $request->all();
        $data['is_routine'] = $request->has('is_routine');

        $task->update($data);

        return redirect()->back()->with('success', 'Task updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Task $task)
    {
        if ($task->user_id !== Auth::id()) {
            abort(403);
        }

        $task->delete();

        return redirect()->back()->with('success', 'Task deleted successfully.');
    }

    public function toggleStatus(Task $task)
    {
        if ($task->user_id !== Auth::id()) {
            abort(403);
        }

        $task->status = $task->status === 'completed' ? 'pending' : 'completed';
        $task->save();

        return redirect()->back()->with('success', 'Task status updated.');
    }
}
