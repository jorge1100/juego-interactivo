<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Invitado · Batalla Naval 🚢</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gradient-to-br from-amber-950 via-orange-950 to-slate-900 text-white font-sans">
    @include('partials.battleship-board', ['role' => 'guest', 'fleet' => \App\Http\Controllers\BattleshipController::FLEET])
</body>
</html>