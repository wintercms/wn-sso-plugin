<?php

namespace Winter\SSO\Models;

use Lang;
use Model;

/**
 * Provider Model
 */
class Provider extends Model
{
    use \Winter\Storm\Database\Traits\Encryptable;
    use \Winter\Storm\Database\Traits\Validation;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'winter_sso_providers';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array List of attribute names which should be encrypted
     */
    protected $encryptable = ['client_secret'];
    /**
     * @var array Fillable fields
     */
    protected $fillable = [];

    /**
     * @var array Validation rules for attributes
     */
    public $rules = [
        'name' => 'required',
        'client_id' => 'required',
    ];

    /**
     * @var array Attributes to be cast to native types
     */
    protected $casts = [];

    /**
     * @var array Attributes to be cast to JSON
     */
    protected $jsonable = [];

    /**
     * @var array Attributes to be appended to the API representation of the model (ex. toArray())
     */
    protected $appends = [];

    /**
     * @var array Attributes to be removed from the API representation of the model (ex. toArray())
     */
    protected $hidden = [];

    /**
     * @var array Attributes to be cast to Argon (Carbon) instances
     */
    protected $dates = [
        'created_at',
        'updated_at',
    ];

    /**
     * @var array Relations
     */
    public $attachOne = [
        'logo' => [\System\Models\File::class, 'delete' => true],
    ];

    public function beforeSave()
    {
        $this->slug = str_slug($this->name);
    }

    public function getNameOptions()
    {
        $values = array_values(Lang::get('winter.sso::lang.providers'));
        return array_combine($values, $values);
    }

    public function scopeIsEnabled($query)
    {
        return $query->where('is_enabled', true);
    }
}
