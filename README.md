# Laravel Model Generator v0.1
Laravel 5 model generator for an existing database schema. 

It connects to existing database and generates model classes with relations based on the existing tables.

# Installation
Add ```"bariscimen/baris-laravel-generator": "dev-master"``` to **"require-dev"** section in your composer.json file.

You'll only want to use these generators for local development, so you don't want to update the production providers array in config/app.php. Instead, add the provider in **register()** metod in **app/Providers/AppServiceProvider.php**, like so:
```php
if ($this->app->environment() == 'local') {
    $this->app->register('Baris\Generator\BarisGeneratorProvider');
}
```

# Running the generator



``php artisan generate:models``

# Examples

## Example Database Schema

![](https://bariscimen.com/content/images/2016/02/example.png)

### Generated Models/Room.php class
```
<?php namespace App;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{

	public $timestamps = true;

	public $fillable = ['name', 'slug', 'building_id'];

	public $guarded = ['id'];
    
    public function announcements()
    {
        return $this->belongsToMany('App\Announcement');
    }

    public function users()
    {
        return $this->belongsToMany('App\User');
    }

    public function building()
    {
        return $this->belongsTo('App\Building');
    }
}
```