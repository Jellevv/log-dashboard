<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedConnection extends Model
{
    protected $fillable = [
        'project_name',
        'ssh_host',
        'logs_path',
    ];
}
