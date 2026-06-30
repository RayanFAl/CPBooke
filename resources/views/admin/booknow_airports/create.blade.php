@extends('admin.layout')
@section('content')
<div class="container">
    <h2>إضافة مطار جديد</h2>
    <form method="POST" action="{{ route('admin.booknow_airports.store') }}">
        @csrf
        <div class="mb-3">
            <label>اسم المطار (إنجليزي)</label>
            <input type="text" name="name_en" class="form-control" required value="{{ old('name_en') }}">
        </div>
        <div class="mb-3">
            <label>الكود (IATA)</label>
            <input type="text" name="iata_code" class="form-control" value="{{ old('iata_code') }}">
        </div>
        <div class="mb-3">
            <label>المدينة (إنجليزي)</label>
            <input type="text" name="city_en" class="form-control" value="{{ old('city_en') }}">
        </div>
        <div class="mb-3">
            <label>الدولة (إنجليزي)</label>
            <input type="text" name="country_name_en" class="form-control" value="{{ old('country_name_en') }}">
        </div>
        <button type="submit" class="btn btn-success">حفظ</button>
        <a href="{{ route('admin.booknow_airports.index') }}" class="btn btn-secondary">إلغاء</a>
    </form>
</div>
@endsection
