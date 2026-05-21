@extends('admin.layout')
@section('content')
<div class="container">
    <h2>تعديل بيانات المطار</h2>
    <form method="POST" action="{{ route('admin.airports.update', $airport) }}">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label>اسم المطار</label>
            <input type="text" name="name" class="form-control" required value="{{ old('name', $airport->name) }}">
        </div>
        <div class="mb-3">
            <label>الكود</label>
            <input type="text" name="code" class="form-control" value="{{ old('code', $airport->code) }}">
        </div>
        <div class="mb-3">
            <label>المدينة</label>
            <input type="text" name="city" class="form-control" value="{{ old('city', $airport->city) }}">
        </div>
        <div class="mb-3">
            <label>الدولة</label>
            <input type="text" name="country" class="form-control" value="{{ old('country', $airport->country) }}">
        </div>
        <button type="submit" class="btn btn-primary">تحديث</button>
        <a href="{{ route('admin.airports.index') }}" class="btn btn-secondary">إلغاء</a>
    </form>
</div>
@endsection
