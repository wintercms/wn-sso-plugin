<?php

namespace Winter\SSO\Models;

use Winter\Storm\Database\Model;

/**
 * Log Model
 */
class Log extends Model
{
    /**
     * @var string The database table used by the model.
     */
    public $table = 'winter_sso_logs';

    /**
    * @var bool Indicates if the model should be timestamped.
    */
    public $timestamps = false;

    /**
     * @var array Attributes that are mass-assignable
     */
    protected $fillable = [
        'provider', 'action', 'user_type', 'user_id', 'provided_id', 'provided_email', 'ip', 'metadata'
    ];

    /**
     * @var array Attributes to be cast to JSON
     */
    protected $jsonable = ['metadata'];

    /**
     * @var array Attributes to be cast to Argon (Carbon) instances
     */
    protected $dates = [
        'created_at',
    ];

    /**
     * @var array Relations
     */
    public $morphTo = [
        'user' => [],
    ];
}
