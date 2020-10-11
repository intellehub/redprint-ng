<?php

Route::namespace('Shahnewaz\RedprintNg\Http\Controllers')->middleware('web')->group(function () {
	
	Route::middleware(['auth', 'role:su', 'redprint', 'licensed:redprint_license'])->prefix('redprint')->group(function () {
		  // Builder
	    Route::get('/dashboard', 'DashboardController@index')->name('redprint.dashboard');
	    // API Tester
	    Route::get('/api-tester', 'DashboardController@apiTester')->name('redprint.api-tester');

	    Route::get('/builder', 'BuilderController@index')->name('redprint.builder');
	    Route::get('/builder/{id}', 'BuilderController@edit')->name('redprint.builder.edit');
	    Route::post('/builder', 'BuilderController@build')->name('redprint.builder.post');
	    Route::post('/edit-crud', 'BuilderController@postEditCrud')->name('redprint.builder.postEdit');
	    
	    Route::get('/builder-verbose', 'BuilderController@buildVerbose')->name('redprint.builder.verbose');

	    // Generated
	    Route::get('/generated', 'DashboardController@getGenerated')->name('redprint.generated');
	    Route::get('/cruds', 'DashboardController@getCruds')->name('redprint.cruds');
	    Route::post('/rollback', 'BuilderController@rollback')->name('redprint.builder.rollback');

	    // Relationship
	    Route::get(
	    	'/relationships',
	    	'RelationshipController@index'
	    )->name('redprint.relationships');
	    Route::post('/relationships', 'RelationshipController@postNew')->name('redprint.relationship.new');

	    // Make tools
	    Route::get('/make-tools', 'MakeController@index')->name('redprint.make-tools');
	    Route::post('/make-tools', 'MakeController@post')->name('redprint.make-tools.post');

	    // File manager Sidebar
	    Route::get('/code-editor/get-sidebar-file-list', 'CodeEditorController@getSidebarFileList')->name('redprint.code-editor.get-sidebar-file-list');
	    // File Editor

	    Route::get(
	    	'/code-editor/get-file-list',
	    	'CodeEditorController@getFileList'
	    )->name('redprint.code-editor.get-file-list');
	    Route::post(
	    	'/code-editor/get-file-content',
	    	'CodeEditorController@getFileContent'
	    )->name('redprint.code-editor.get-file-content');
	    Route::post(
	    	'/code-editor/put-file-content',
	    	'CodeEditorController@putFileContent'
	    )->name('redprint.code-editor.put-file-content');
	    Route::post(
	    	'/code-editor/delete-file',
	    	'CodeEditorController@deleteFile'
	    )->name('redprint.code-editor.delete-file');
	    Route::post(
	    	'/code-editor/create-file',
	    	'CodeEditorController@createNewFile'
	    )->name('redprint.code-editor.create-file');


	    // Settings Manager
	    Route::get(
	    	'/settings-manager/get-settings',
	    	'SettingsController@getSettings'
	    )->name('redprint.settings-manager.get-settings');

	    // ENV
	    Route::get('/settings-manager/get-env-content', 'SettingsController@getEnvContent')->name('redprint.settings-manager.get-env-content');
	    Route::post('/settings-manager/set-env-content', 'SettingsController@setEnvContent')->name('redprint.settings-manager.set-env-content');


	    // Permissible
	    Route::get('/settings-manager/get-permissible-config', 'SettingsController@getPermissibleConfig')->name('redprint.settings-manager.get-permissible-config');
	    Route::post('/settings-manager/set-permissible-config', 'SettingsController@setPermissibleConfig')->name('redprint.settings-manager.set-permissible-config');

	    // Redprint
	    Route::get('/settings-manager/get-redprint-config', 'SettingsController@getRedprintConfig')->name('redprint.settings-manager.get-redprint-config');
	    Route::post('/settings-manager/set-redprint-config', 'SettingsController@setRedprintConfig')->name('redprint.settings-manager.set-redprint-config');

	    // Theme
	    Route::get(
	    	'/settings-manager/get-theme-config',
	    	'SettingsController@getThemeConfig'
	    )->name('redprint.settings-manager.get-theme-config');
	    Route::post(
	    	'/settings-manager/set-theme-config',
	    	'SettingsController@setThemeConfig'
	    )->name('redprint.settings-manager.set-theme-config');

	    // Menu
	    Route::get(
	    	'/settings-manager/get-redprint-menu',
	    	'SettingsController@getRedprintMenu'
	    )->name('redprint.settings-manager.get-redprint-menu');
	    Route::get(
	    	'/settings-manager/set-redprint-menu',
	    	'SettingsController@setRedprintMenu'
	    )->name('redprint.settings-manager.set-redprint-menu');
	    
	});
});
