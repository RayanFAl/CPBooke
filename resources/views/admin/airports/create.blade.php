@extends('admin.layout')
@section('content')
<div class="container">
    <h2>إضافة مطار جديد</h2>
    <form method="POST" action="{{ route('admin.airports.store') }}">
        @csrf
        <div class="mb-3">
            <label>اسم المطار</label>
            <input type="text" name="name" class="form-control" required value="{{ old('name') }}">
        </div>
        <div class="mb-3">
            <label>الكود</label>
            <input type="text" name="code" class="form-control" value="{{ old('code') }}">
        </div>
        <div class="mb-3">
            <label>المدينة</label>
            <input type="text" name="city" class="form-control" value="{{ old('city') }}">
        </div>
        <div class="mb-3">
            <label>الدولة</label>
            <input type="text" name="country" class="form-control" value="{{ old('country') }}">
        </div>
        <button type="submit" class="btn btn-success">حفظ</button>
        <a href="{{ route('admin.airports.index') }}" class="btn btn-secondary">إلغاء</a>
    </form>
</div>
@endsection
