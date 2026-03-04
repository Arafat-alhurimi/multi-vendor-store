<?php

$config = require base_path('vendor/livewire/livewire/config/livewire.php');

$config['temporary_file_upload']['disk'] = env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK', 'local');
$config['temporary_file_upload']['directory'] = env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DIRECTORY', 'livewire-tmp');
$config['temporary_file_upload']['max_upload_time'] = (int) env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_MAX_UPLOAD_TIME', 30);

return $config;
