<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <style>
        body {
            background-image: url('/img/social.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            font-family: Arial, sans-serif;
            color: white;
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .error-container {
            background: rgba(0, 0, 0, 0.7);
            padding: 20px;
            border-radius: 10px;
        }

        .gif-container {
            margin: 20px 0;
        }

        .message {
            margin-top: 20px;
        }
    </style>
</head>
<body>
<div class="error-container">
    <h1>@yield('title')</h1>
    @if(isset($exception) && in_array($exception->getCode(), [403, 404, 419, 422, 429, 500, 503]))
        <div class="message">@yield('message')</div>
    @endif
    <div class="gif-container">
        <img src="{{ asset('img/vin_diesel/vin_diesel' . rand(1, 9) . '.webp') }}" alt="Vin Diesel">
    </div>
</div>
</body>
</html>
