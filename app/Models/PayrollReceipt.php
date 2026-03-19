<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollReceipt extends Model
{
    protected $fillable = [
        'employee_id',
        'period_start',
        'period_end',
        'payment_date',
        'base_salary',
        'bonus',
        'deductions',
        'net_total',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'payment_date' => 'date',
        'base_salary' => 'decimal:2',
        'bonus' => 'decimal:2',
        'deductions' => 'decimal:2',
        'net_total' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
