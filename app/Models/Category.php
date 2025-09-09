<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name'];
    protected $casts = [
        'tags' => 'array',
    ];
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
