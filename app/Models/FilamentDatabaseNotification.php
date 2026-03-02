<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification as BaseDatabaseNotification;

class FilamentDatabaseNotification extends BaseDatabaseNotification
{
    /**
     * Override the table used by the notification model.
     *
     * @var string
     */
    protected $table = 'filament_notifications';
}
