<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\SalaryHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Setting;
use App\Models\PayrollReceipt;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class EmployeeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:Ver RRHH', only: ['index', 'show', 'printContract']),
            new Middleware('can:Gestionar Empleados', only: ['create', 'store', 'edit', 'update', 'destroy', 'settings', 'updateSettings']),
            new Middleware('can:Gestionar Salarios', only: ['updateSalary']),
            new Middleware('can:Gestionar Documentos RRHH', only: ['storeDocument', 'destroyDocument', 'storePayroll']),
        ];
    }
    public function index()
    {
        $employees = Employee::with('user')->get();
        return Inertia::render('HR/Index', [
            'employees' => $employees
        ]);
    }

    public function create()
    {
        $users = User::whereDoesntHave('employee')->whereDoesntHave('roles', function ($q) {
            $q->where('name', 'Cliente');
        })->get();

        return Inertia::render('HR/Create', [
            'users' => $users
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id|unique:employees,user_id',
            'employee_number' => 'required|string|unique:employees,employee_number',
            'phone' => 'nullable|string',
            'curp' => 'nullable|string',
            'nss' => 'nullable|string',
            'rfc' => 'nullable|string',
            'address' => 'nullable|string',
            'position' => 'nullable|string',
            'department' => 'nullable|string',
            'join_date' => 'nullable|date',
            'initial_salary' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $validated['current_salary'] = $validated['initial_salary'];

        $employee = Employee::create($validated);

        // Record initial salary history
        SalaryHistory::create([
            'employee_id' => $employee->id,
            'salary' => $employee->initial_salary,
            'change_date' => $employee->join_date ?? now(),
            'notes' => 'Salario inicial'
        ]);

        return redirect()->route('employees.show', $employee->id)->with('message', 'Empleado registrado exitosamente.');
    }

    public function show(Employee $employee)
    {
        $employee->load([
            'user',
            'documents',
            'salaryHistories' => function ($q) {
                $q->latest('change_date');
            },
            'payrollReceipts' => function ($q) {
                $q->latest('payment_date');
            }
        ]);

        return Inertia::render('HR/Show', [
            'employee' => $employee
        ]);
    }

    public function edit(Employee $employee)
    {
        return Inertia::render('HR/Edit', [
            'employee' => $employee
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'employee_number' => 'required|string|unique:employees,employee_number,' . $employee->id,
            'phone' => 'nullable|string',
            'curp' => 'nullable|string',
            'nss' => 'nullable|string',
            'rfc' => 'nullable|string',
            'address' => 'nullable|string',
            'position' => 'nullable|string',
            'department' => 'nullable|string',
            'join_date' => 'nullable|date',
            'status' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $employee->update($validated);

        return redirect()->route('employees.show', $employee->id)->with('message', 'Datos actualizados.');
    }

    public function storeDocument(Request $request, Employee $employee)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB
            'document_type' => 'required|string',
        ]);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $fileName = $request->document_type . '_' . time() . '.' . $extension;
        $path = $file->storeAs('employees/' . $employee->employee_number, $fileName, 'public');

        EmployeeDocument::create([
            'employee_id' => $employee->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'document_type' => $request->document_type,
        ]);

        return redirect()->back()->with('message', 'Documento guardado.');
    }

    public function destroyDocument(EmployeeDocument $document)
    {
        Storage::disk('public')->delete($document->file_path);
        $document->delete();
        return redirect()->back()->with('message', 'Documento eliminado.');
    }

    public function updateSalary(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'salary' => 'required|numeric|min:0',
            'change_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        SalaryHistory::create([
            'employee_id' => $employee->id,
            'salary' => $validated['salary'],
            'change_date' => $validated['change_date'],
            'notes' => $validated['notes']
        ]);

        $employee->update(['current_salary' => $validated['salary']]);

        return redirect()->back()->with('message', 'Salario actualizado e historial generado.');
    }

    public function storePayroll(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date',
            'payment_date' => 'required|date',
            'base_salary' => 'required|numeric|min:0',
            'bonus' => 'required|numeric|min:0',
            'deductions' => 'required|numeric|min:0',
            'status' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $validated['employee_id'] = $employee->id;
        $validated['created_by'] = auth()->id();
        $validated['net_total'] = $validated['base_salary'] + $validated['bonus'] - $validated['deductions'];

        PayrollReceipt::create($validated);

        return redirect()->back()->with('message', 'Recibo generado exitosamente.');
    }

    public function printPayroll(PayrollReceipt $receipt)
    {
        $receipt->load(['employee.user', 'creator']);

        return Inertia::render('HR/PayrollPrint', [
            'receipt' => $receipt,
            'format' => request('format', 'letter')
        ]);
    }

    public function settings()
    {
        $template = Setting::where('key', 'employee_contract_template')->value('value');
        
        return Inertia::render('HR/Settings', [
            'contract_template' => $template ?? ''
        ]);
    }

    public function updateSettings(Request $request)
    {
        $request->validate(['contract_template' => 'nullable|string']);
        
        Setting::updateOrCreate(
            ['key' => 'employee_contract_template'],
            ['value' => $request->contract_template]
        );
        
        return redirect()->back()->with('message', 'Plantilla actualizada correctamente.');
    }

    public function printContract(Employee $employee)
    {
        $employee->load('user');
        $template = Setting::where('key', 'employee_contract_template')->value('value');
        
        if (!$template) {
            return back()->with('message', 'No hay una plantilla de contrato configurada.');
        }

        $variables = [
            '[nombre_empleado]' => $employee->user->name ?? '',
            '[numero_empleado]' => $employee->employee_number ?? '',
            '[telefono]' => $employee->phone ?? '',
            '[curp]' => $employee->curp ?? '',
            '[nss]' => $employee->nss ?? '',
            '[rfc]' => $employee->rfc ?? '',
            '[direccion]' => $employee->address ?? '',
            '[puesto]' => $employee->position ?? '',
            '[departamento]' => $employee->department ?? '',
            '[fecha_ingreso]' => $employee->join_date ? \Carbon\Carbon::parse($employee->join_date)->format('d/m/Y') : '',
            '[salario_actual]' => '$' . number_format((float) $employee->current_salary, 2, '.', ','),
            '[razon_social]' => Setting::where('key', 'company_legal_name')->value('value') ?? '',
            '[rfc_empresa]' => Setting::where('key', 'company_rfc')->value('value') ?? '',
            '[fecha_actual]' => now()->format('d/m/Y'),
        ];

        $html = str_replace(array_keys($variables), array_values($variables), $template);

        $finalHtml = '
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>Contrato ' . $employee->employee_number . '</title>
            <style>
                body { font-family: "Helvetica", "Arial", sans-serif; font-size: 14px; line-height: 1.6; color: #111; padding: 20px; }
                h1, h2, h3, h4 { color: #000; }
                p { margin-bottom: 15px; text-align: justify; }
            </style>
        </head>
        <body>
            ' . $html . '
        </body>
        </html>';

        return Pdf::loadHTML($finalHtml)->stream('Contrato_' . $employee->employee_number . '.pdf');
    }
}
