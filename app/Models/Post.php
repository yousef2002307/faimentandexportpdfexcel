<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Post extends Model
{
    protected $guarded = [];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    protected $casts = [
        'tags' => 'array',
    ];
    public function authors() : BelongsToMany
    {
        return $this->belongsToMany(User::class,'post_user','post_id','user_id')->withTimestamps()->withPivot('order');
    }
    public function comments() : HasMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}