<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Airport;
use Illuminate\Http\Request;

class AirportController extends Controller
{
    public function index(Request $request)
    {
        $query = Airport::query();
        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%$search%")
                  ->orWhere('code', 'like', "%$search%")
                  ->orWhere('city', 'like', "%$search%")
                  ->orWhere('country', 'like', "%$search%") ;
        }
        $airports = $query->orderBy('name')->paginate(20);
        return view('admin.airports.index', compact('airports', 'search'));
    }

    public function create()
    {
        return view('admin.airports.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:10',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
        ]);
        Airport::create($data);
        return redirect()->route('admin.airports.index')->with('success', 'تمت إضافة المطار بنجاح');
    }

    public function edit(Airport $airport)
    {
        return view('admin.airports.edit', compact('airport'));
    }

    public function update(Request $request, Airport $airport)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:10',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
        ]);
        $airport->update($data);
        return redirect()->route('admin.airports.index')->with('success', 'تم تعديل بيانات المطار بنجاح');
    }

    public function destroy(Airport $airport)
    {
        $airport->delete();
        return redirect()->route('admin.airports.index')->with('success', 'تم حذف المطار بنجاح');
    }
}
