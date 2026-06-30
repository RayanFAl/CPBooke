<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BooknowAirport;
use Illuminate\Http\Request;

class BooknowAirportController extends Controller
{
    public function index(Request $request)
    {
        $query = BooknowAirport::query();
        if ($search = $request->input('search')) {
            $query->where('name_en', 'like', "%$search%")
                  ->orWhere('iata_code', 'like', "%$search%")
                  ->orWhere('city_en', 'like', "%$search%")
                  ->orWhere('country_name_en', 'like', "%$search%") ;
        }
        $airports = $query->orderBy('name_en')->paginate(20);
        return view('admin.booknow_airports.index', compact('airports', 'search'));
    }

    public function create()
    {
        return view('admin.booknow_airports.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name_en' => 'required|string|max:255',
            'iata_code' => 'nullable|string|max:10',
            'city_en' => 'nullable|string|max:255',
            'country_name_en' => 'nullable|string|max:255',
        ]);
        BooknowAirport::create($data);
        return redirect()->route('admin.booknow_airports.index')->with('success', 'تمت إضافة المطار بنجاح');
    }

    public function edit(BooknowAirport $booknow_airport)
    {
        return view('admin.booknow_airports.edit', ['airport' => $booknow_airport]);
    }

    public function update(Request $request, BooknowAirport $booknow_airport)
    {
        $data = $request->validate([
            'name_en' => 'required|string|max:255',
            'iata_code' => 'nullable|string|max:10',
            'city_en' => 'nullable|string|max:255',
            'country_name_en' => 'nullable|string|max:255',
        ]);
        $booknow_airport->update($data);
        return redirect()->route('admin.booknow_airports.index')->with('success', 'تم تعديل بيانات المطار بنجاح');
    }

    public function destroy(BooknowAirport $booknow_airport)
    {
        $booknow_airport->delete();
        return redirect()->route('admin.booknow_airports.index')->with('success', 'تم حذف المطار بنجاح');
    }
}
