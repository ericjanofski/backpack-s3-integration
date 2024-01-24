Route::post('/custom-media/image/new', [MediaController::class, 'storeImage']);
Route::post('/custom-media/media/new', [MediaController::class, 'storeMedia']);
Route::post('/custom-media/delete', [MediaController::class, 'removeFromS3']);
