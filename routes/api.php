<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
   dd($request->user());
});
Route::prefix('v1')->group(function () {
    $directoryIterator = new \RecursiveDirectoryIterator(__DIR__ . '/../routes/v1');
    $iteratorIterator = new \RecursiveIteratorIterator($directoryIterator);

    foreach ($iteratorIterator as $file) {
        if ($file->isFile() && $file->isReadable() && $file->getExtension() === 'php') {
            require $file->getPathname();
        }
    }

 });
