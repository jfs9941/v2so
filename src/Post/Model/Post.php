<?php

namespace Module\Post\Model;

use App\Model\Post as BasePost;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends BasePost
{
    /**
     * Active (non-deleted) comments only.
     */
    public function activeComments(): HasMany
    {
        return $this->hasMany('App\Model\PostComment')->where('is_deleted', false);
    }
}
