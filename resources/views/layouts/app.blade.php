<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head')
    </head>
    <body>
        @include('layouts.partials.header')
        @yield('content')
        @include('layouts.partials.scripts')
    </body>
</html>