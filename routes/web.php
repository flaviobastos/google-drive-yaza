<?php

use App\Http\Controllers\GoogleController;
use Illuminate\Support\Facades\Route;

Route::get('/arquivos', [GoogleController::class, 'index'])->name('arquivos.index');
Route::get('/arquivos/download/{path}', [GoogleController::class, 'download'])->where('path', '.*');
Route::get('/arquivos/download-pasta/{path}', [GoogleController::class, 'downloadFolder'])->where('path', '.*')->name('arquivos.download-pasta');
Route::post('/arquivos/renomear', [GoogleController::class, 'rename']);
Route::post('/arquivos/renomear-pasta', [GoogleController::class, 'renameFolder'])->name('arquivos.renomear-pasta');
Route::post('/arquivos/mover', [GoogleController::class, 'move']);
Route::post('/arquivos/mover-pasta', [GoogleController::class, 'moveFolder'])->name('arquivos.mover-pasta');
Route::post('/arquivos/excluir', [GoogleController::class, 'delete']);
Route::post('/arquivos/excluir-pasta', [GoogleController::class, 'deleteFolder'])->name('arquivos.excluir-pasta');
Route::post('/arquivos/criar-pasta', [GoogleController::class, 'createFolder'])->name('arquivos.criar-pasta');
Route::post('/arquivos/upload', [GoogleController::class, 'upload'])->name('arquivos.upload');
