<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'لوحة الإدارة')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8fafc;
        }

        .admin-layout {
            min-height: 100vh;
            display: flex;
            gap: 1rem;
            padding: 1rem;
        }

        .admin-sidebar {
            width: 250px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1rem;
            height: fit-content;
            position: sticky;
            top: 1rem;
        }

        .admin-content {
            flex: 1;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1.25rem;
        }

        .sidebar-menu {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 0.5rem;
        }

        .sidebar-menu a {
            display: block;
            padding: 0.6rem 0.75rem;
            border-radius: 0.5rem;
            text-decoration: none;
            color: #1f2937;
            background: #f8fafc;
        }

        .sidebar-menu a:hover {
            background: #e2e8f0;
        }

        @media (max-width: 992px) {
            .admin-layout {
                flex-direction: column;
            }

            .admin-sidebar {
                width: 100%;
                position: static;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            @include('admin.sidebar')
        </aside>

        <main class="admin-content">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
