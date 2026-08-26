<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(): Response
    {
        $this->authorize('journal.view');

        $employees = Employee::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Employee $employee) => [
                'id'              => $employee->id,
                'employee_number' => $employee->employee_number,
                'name'            => $employee->name,
                'nric'            => $employee->nric,
                'epf_number'      => $employee->epf_number,
                'tax_category'    => $employee->tax_category,
                'basic_salary'    => (float) $employee->basic_salary,
                'is_active'       => $employee->is_active,
            ]);

        return Inertia::render('Payroll/Employees', [
            'employees'     => $employees,
            'taxCategories' => $this->taxCategoryOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('journal.create');

        $validated = $this->validateEmployee($request);
        Employee::create($validated);

        return redirect()->route('payroll.employees.index')->with('success', 'Employee added.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->authorize('journal.edit');

        $employee = Employee::findOrFail($id);
        $employee->update($this->validateEmployee($request));

        return redirect()->route('payroll.employees.index')->with('success', 'Employee updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->authorize('journal.edit');

        $employee = Employee::findOrFail($id);
        if ($employee->payrollLines()->exists()) {
            return redirect()->route('payroll.employees.index')
                ->with('error', 'Cannot delete an employee who appears on a payroll run.');
        }

        $employee->delete();

        return redirect()->route('payroll.employees.index')->with('success', 'Employee removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateEmployee(Request $request): array
    {
        return $request->validate([
            'employee_number' => 'nullable|string|max:50',
            'name'            => 'required|string|max:150',
            'nric'            => 'nullable|string|max:20',
            'epf_number'      => 'nullable|string|max:20',
            'tax_category'    => 'required|string|max:10',
            'basic_salary'    => 'nullable|numeric|min:0',
            'is_active'       => 'boolean',
        ]);
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    private function taxCategoryOptions(): array
    {
        return [
            ['value' => '1', 'label' => '1 — Single'],
            ['value' => '2', 'label' => '2 — Married, spouse not working'],
            ['value' => '3', 'label' => '3 — Married, spouse working'],
        ];
    }
}
