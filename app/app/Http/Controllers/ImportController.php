<?php

namespace App\Http\Controllers;

use App\Services\StudentImporter;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function form()
    {
        return view('import');
    }

    public function run(Request $request, StudentImporter $importer)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'year' => 'nullable|integer|min:2020|max:2099',
        ]);

        $path = $request->file('file')->getRealPath();
        $year = (int) ($request->input('year') ?: date('Y'));

        try {
            $stats = $importer->import($path, $year);
        } catch (\Throwable $e) {
            return back()->with('flash', 'فشل الاستيراد: ' . $e->getMessage())->with('flash_type', 'error');
        }

        $msg = sprintf(
            'تم الاستيراد ✓ — طلاب: +%d / محدّث: %d • عائلات: +%d • دفعات: %d • علامات X: %d • أرقام غير صالحة: %d',
            $stats['students_created'],
            $stats['students_updated'],
            $stats['families_created'],
            $stats['payments_created'],
            $stats['markers_created'],
            $stats['phones_invalid'],
        );

        return redirect()->route('home')->with('flash', $msg)->with('flash_type', 'success');
    }
}
