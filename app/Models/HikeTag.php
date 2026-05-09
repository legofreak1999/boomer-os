<?php

namespace App\Models;

use Database\Factories\HikeTagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

#[Fillable(['name'])]
class HikeTag extends Model
{
    /** @use HasFactory<HikeTagFactory> */
    use HasFactory;

    public function locations(): MorphToMany
    {
        return $this->morphedByMany(HikeLocation::class, 'taggable', 'hike_taggables', 'hike_tag_id');
    }

    public function trails(): MorphToMany
    {
        return $this->morphedByMany(HikeTrail::class, 'taggable', 'hike_taggables', 'hike_tag_id');
    }
}
