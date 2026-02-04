<?php
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Facades\Artisan;
    use Illuminate\Support\Facades\Cache;

Route::get('generate-admin', 'api\loginController@generateAccount');
Route::post('login', 'api\loginController@login');
Route::post('refresh-token', 'api\loginController@refreshToken');
Route::post('logout', 'api\loginController@logout')->middleware('jwt.auth');
Route::get('user', 'api\userController@getUserDetil')->middleware('jwt.auth');

Route::get('generate-account/{id_banding}', 'api\userController@generateUserSystem');
Route::get('generate-admin-badilum', 'api\userController@generateUserBadilum')->middleware('jwt.auth');

Route::middleware(['jwt.auth', 'superadmin'])->group(function(){
    //role
    Route::get('list-role/{page?}', 'api\configController@listRole');
    Route::post('save-role', 'api\configController@saveRole');
    Route::get('get-role/{id}', 'api\configController@getRoleById');
    Route::post('update-role', 'api\configController@updateRole');
    Route::post('delete-role', 'api\configController@deleteRole');
});

Route::middleware(['jwt.auth', 'superadminbadilum'])->group(function () {
    Route::get('list-satker-banding', 'api\userController@getSatkerBanding');
    Route::get('list-satker-pertama/{id_banding}', 'api\userController@getSatkerTKPertama');
    Route::get('get-user/{page?}', 'api\userController@getAllUser');//tambahkan middleware access admin 1 / 2
    Route::post('save-user-default', 'api\userController@saveAdminUser');
    Route::get('get-user-detil/{id}', 'api\userController@getUserById');
    Route::post('update-user', 'api\userController@updateDataUser');
    Route::get('list-all-satker', 'api\zonasiController@getAllSatkerAll');
    Route::post('generate-user', 'api\userController@generateUserAdminSatker');
});

Route::middleware(['jwt.auth', 'isAdminSatker'])->group(function(){
    Route::get('dashboard-satker', 'api\dashboardController@dashboardAdminSatker');
    Route::get('profile', 'api\userController@getDetilUserSatker');
    Route::post('get-pegawai-sikep', 'api\userController@getDataPegawaiByNIP');
    Route::post('send-wa-token-admin', 'api\userController@sendTokenWaNewAdmin')->middleware('checkSign');
    Route::post('confirm-token', 'api\userController@confirmToken')->middleware('checkSign');
    Route::post('save-admin-satker', 'api\userController@saveAdminSatker')->middleware('checkSign');
    Route::get('list-jabatan-kosong-satker/{id_zonasi_satker}', 'api\zonasiSatkerController@getJabatanKosongSatker');
    Route::get('detil-jabatan-kosong-satker/{token_jabatan_kosong}', 'api\zonasiSatkerController@detilJabatanKosongSatker');
    Route::post('get-pegawai-local', 'api\zonasiSatkerController@getPegawaiLocalByNIP');
    Route::post('save-jabatan-kosong', 'api\zonasiSatkerController@saveJabatanKosongSatker')->middleware('checkSign');
    Route::post('confirm-jabatan-kosong', 'api\zonasiSatkerController@sendConfirmJabatanKosong')->middleware('checkSign');
    Route::get('monitoring-satker/{id_zonasi_satker}/{page}/{refresh?}', 'api\zonasiSatkerController@montoringZonasiSatker');
    Route::post('send-to-badilum', 'api\zonasiSatkerController@sendPenilaianToBadilum')->middleware('checkSign');

    // Route::get('detil-zonasi-satker',)
    Route::get('list-zonasi-satker/{page}', 'api\zonasiSatkerController@listZonasiSatker');
    Route::get('detil-zonasi-satker/{id}', 'api\zonasiSatkerController@detilZonasiSatker');
    // Route::get('get-kpt/{id_zonmasi}', 'api\zonasiController@getKPT');

});

Route::middleware(['jwt.auth', 'isAdminBadilum'])->group(function(){
    //zonasi
    Route::get('dashboard-badilum', 'api\dashboardController@dashboardAdminBadilum');
    Route::get('list-zonasi/{page?}', 'api\zonasiController@getListZonasi');
    Route::post('save-zonasi', 'api\zonasiController@saveZonasi');
    Route::post('save-satker-zonasi', 'api\zonasiController@addSatkerToZonasi');
    Route::get('detil-zonasi/{id}', 'api\zonasiController@getZonasiById');
    Route::get('list-satker-zonasi/{id}', 'api\zonasiController@getSatkerZonasi');
    Route::get('list-peserta-periode/{id_tahun_penilaian}', 'api\zonasiController@getPesertaSIKEPByPeriode');
    Route::post('remove-satker-zonasi', 'api\zonasiController@removeExistedSatkerZonasi');
    Route::post('run-queue', 'api\zonasiController@runQueue')->middleware('checkSign');
    Route::post('run-queue-peserta', 'api\zonasiSatkerController@sendNotificationPeserta')->middleware('checkSign');
    Route::post('regenerate-peserta', 'api\zonasiController@regeneratePeserta')->middleware('checkSign');
    Route::get('progress-jobs-peserta/{id}', 'api\zonasiController@getProgressLog');
    Route::get('progress-jobs-notif/{id}', 'api\zonasiSatkerController@progressJobsNotif');
    Route::get('jabatan-kosong/{page}/{id_zonasi}', 'api\zonasiController@getJabatanKosong');
    Route::get('list-peserta-zonasi-satker/{id}', 'api\zonasiController@getPesertaZonasiSatker');

    //periode
    Route::get('list-periode/{page?}', 'api\periodeController@listPeriode');
    Route::post('save-periode', 'api\periodeController@savePeriode');
    Route::get('detil-periode/{id}', 'api\periodeController@getPeriodeById');
    Route::post('update-periode', 'api\periodeController@updatePeriode');
    Route::post('delete-periode', 'api\periodeController@deletePeriode');
    Route::get('list-active-periode', 'api\periodeController@listActivePeriode');

    //Periode - Detil
    Route::get('bobot-penilaian-periode/{id}', 'api\periodeController@getBobotPenilaianPeriode');
    Route::post('remove-bobot-periode', 'api\periodeController@removeBobotPenilaianPeriode')->middleware('checkSign');
    Route::post('regenerate-bobot-periode', 'api\periodeController@regenerateBobotPenilaianPeriode')->middleware('checkSign');
    Route::get('pertanyaan-periode/{id}', 'api\periodeController@getPertanyaanPeriode');
    Route::post('remove-pertanyaan-periode', 'api\periodeController@removePertanyaanPeriode')->middleware('checkSign');
    Route::post('regenerate-pertanyaan-periode', 'api\periodeController@regeneratePertanyaanPeriode')->middleware('checkSign');
    Route::get('mapping-jabatan-periode/{id}', 'api\periodeController@getMappingJabatanPeriode');
    Route::post('remove-mapping-jabatan-periode', 'api\periodeController@removeMappingJabatanPeriode')->middleware('checkSign');
    Route::post('regenerate-mapping-jabatan-periode', 'api\periodeController@regenerateMappingJabatanPeriode')->middleware('checkSign');
    
    
    //mapping kelompok jabatan
    Route::get('list-kelompok-jabatan-sikep', 'api\configController@getKelompokJabatanSIKEP');
    Route::get('list-jabatan-peserta/{page}', 'api\configController@getDataKelompokJabatan');
    Route::get('kelompok-jabatan-detil/{id}', 'api\configController@getKelompokJabatanDetil');
    Route::post('gabungkan-jabatan', 'api\configController@gabungkanKelompokJabatan');
    Route::post('save-jabatan-peserta', 'api\configController@saveDataKelompokJabatan');
    Route::post('update-jabatan-peserta', 'api\configController@updateKelompokJabatan')->middleware('checkSign');

    //mapping jabatan
    Route::get('list-mapping-observee', 'api\configController@listMappingObservee');
    Route::post('save-mapping-jabatan', 'api\configController@saveMappingJabatan');
    Route::post('update-mapping-jabatan', 'api\configController@updateMappingJabatan');
    Route::get('detil-mapping-jabatan/{id_jabatan_peserta}', 'api\configController@getMappingJabatan');

    //bobot penilaian
    Route::get('list-bobot/{page}', 'api\configController@getAllBobot');
    // Route::post('save-bobot', 'api\configController@saveNewBobot');
    Route::get('detil-bobot/{id}', 'api\configController@getDetilBobot');
    Route::post('update-bobot', 'api\configController@updateBobot');

    //variable pertanyaan
    Route::get('list-variable-pertanyaan/{page}', 'api\configController@getListVariable');
    Route::get('list-all-variable', 'api\configController@getAllVariable');
    Route::post('save-variable-pertanyaan', 'api\configController@saveVariablePertanyaan');
    Route::get('detil-variable-pertanyaan/{id}', 'api\configController@getVaribleById');
    Route::post('update-variable-pertanyaan', 'api\configController@updateVariablePertanyaan');

    //Bundle Jawaban
    Route::get('list-bundle-jawaban/{page}', 'api\configController@getJawabanBundle');
    Route::get('list-all-bundle-jawaban', 'api\configController@getAllBundleJawaban');
    Route::post('save-bundle-jawaban', 'api\configController@saveJawabanBundle');
    Route::get('bundle-jawaban-detil/{id}', 'api\configController@getJawabanBundleDetil');
    Route::post('update-bundle-jawaban', 'api\configController@updateBundleJawaban')->middleware('checkSign');

    //pertanyaan
    Route::get('list-pertanyaan/{page}', 'api\configController@getListPertanyaan');
    Route::post('save-pertanyaan', 'api\configController@savePertanyaan');
    Route::get('pertanyaan-detil/{id}', 'api\configController@getPertanyaanDetil');
    Route::post('update-pertanyaan', 'api\configController@updatePertanyaan')->middleware('checkSign');

    //monitoring
    Route::get('monitoring-badilum/{id}/{page}/{refresh?}', 'api\zonasiController@monitoringBadilum');
    
    //Report
    Route::post('report-satker', 'api\reportController@reportSatker');
    Route::get('all-periode', 'api\reportController@getListPeriode');
    Route::get('zonasi-periode/{id_periode}', 'api\reportController@getZonasiPeriode');
    Route::get('zonasi-satker/{id_periode}/{id_zona}', 'api\reportController@getZonasiSatkerService');

});

Route::post('validate-params', 'api\penilaianController@validateParams');
Route::middleware(['checkSign'])->group(function(){
    Route::post('list-pertanyaan', 'api\penilaianController@listPertanyaanPenilaian');//->middleware('throttleSurvey')
    Route::post('penilaian', 'api\penilaianController@penilaian'); 
    Route::post('save-jawaban', 'api\penilaianController@saveJawaban')->middleware('throttleSurvey');
    Route::post('lock-jawaban', 'api\penilaianController@lockJawaban')->middleware('throttleSurvey');
});


Route::get('list-observee/{id_zonasi}', 'api\zonasiController@observer');
Route::get('encdec/{method}/{string}', 'api\zonasiController@enc');
<<<<<<< HEAD
Route::get('test', function(){
    return dd(Cache::getPrefix());
});
=======
// Route::get('test', function(){
//     return dd(Cache::getPrefix());
// });
>>>>>>> 2096da6 (initial commit)
