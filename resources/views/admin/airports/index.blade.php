@extends('admin.layout')
@section('content')
<div class="container">
    <h1 class="mb-4">المطارات</h1>
    <form method="GET" action="" class="mb-3">
        <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="بحث بالاسم أو الكود أو المدينة أو الدولة" class="form-control" style="max-width:300px;display:inline-block;">
        <button type="submit" class="btn btn-primary">بحث</button>
        <a href="{{ route('admin.airports.create') }}" class="btn btn-success float-end">إضافة مطار جديد</a>
    </form>
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>الاسم</th>
                <th>الكود</th>
                <th>المدينة</th>
                <th>الدولة</th>
                <th>العمليات</th>
            </tr>
        </thead>
        <tbody>
        @foreach($airports as $airport)
            <tr>
                <td>{{ $airport->name }}</td>
                <td>{{ $airport->code }}</td>
                <td>{{ $airport->city }}</td>
                <td>{{ $airport->country }}</td>
                <td>
                    <a href="{{ route('admin.airports.edit', $airport) }}" class="btn btn-sm btn-warning">تعديل</a>
                    <form action="{{ route('admin.airports.destroy', $airport) }}" method="POST" style="display:inline-block;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('تأكيد الحذف؟')">حذف</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{ $airports->links() }}
</div>
@endsection
