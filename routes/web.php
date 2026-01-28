<?php
use Illuminate\Support\Facades\Route;
Route::get('/up', function () {
    return response()->json([
        'name'    => 'Serverless Response',
        'status'  => 'ok',
        'version' => 'v1',
    ]);
});
