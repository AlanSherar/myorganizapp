<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'status',
        'priority',
        'is_routine',
        'routine_type',
        'routine_day',
        'routine_time',
        'start_date',
        'expected_end_date',
        'due_date',
    ];

    protected $casts = [
        'is_routine' => 'boolean',
        'routine_time' => 'datetime:H:i', // Format only time when accessing
        'start_date' => 'datetime',
        'expected_end_date' => 'datetime',
        'due_date' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
