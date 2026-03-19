<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'user_id',
        'employee_number',
        'phone',
        'curp',
        'nss',
        'rfc',
        'address',
        'position',
        'department',
        'join_date',
        'initial_salary',
        'current_salary',
        'status',
        'notes',
    ];

    protected $casts = [
        'join_date' => 'date',
        'initial_salary' => 'decimal:2',
        'current_salary' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function salaryHistories()
    {
        return $this->hasMany(SalaryHistory::class);
    }

    public function payrollReceipts()
    {
        return $this->hasMany(PayrollReceipt::class);
    }
}
