@extends('admin.layout')
@section('content')
<div class="container">
    <h2>تعديل بيانات المطار</h2>
    <form method="POST" action="{{ route('admin.booknow_airports.update', $airport) }}">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label>اسم المطار (إنجليزي)</label>
            <input type="text" name="name_en" class="form-control" required value="{{ old('name_en', $airport->name_en) }}">
        </div>
        <div class="mb-3">
            <label>الكود (IATA)</label>
            <input type="text" name="iata_code" class="form-control" value="{{ old('iata_code', $airport->iata_code) }}">
        </div>
        <div class="mb-3">
            <label>المدينة (إنجليزي)</label>
            <input type="text" name="city_en" class="form-control" value="{{ old('city_en', $airport->city_en) }}">
        </div>
        <div class="mb-3">
            <label>الدولة (إنجليزي)</label>
            <input type="text" name="country_name_en" class="form-control" value="{{ old('country_name_en', $airport->country_name_en) }}">
        </div>
        <button type="submit" class="btn btn-primary">تحديث</button>
        <a href="{{ route('admin.booknow_airports.index') }}" class="btn btn-secondary">إلغاء</a>
    </form>
</div>
@endsection
