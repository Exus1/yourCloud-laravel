<?php

namespace App;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Mockery\Exception;

class File extends Model
{
    protected $fillable = [
        'type',
        'parent_id',
        'users_id',
        'name',
        'path',
        'mime_type',
        'size',
    ];

    protected $hidden = [
        'path',
    ];

//    protected static function boot()
//    {
//        parent::boot();
//
//        // Adding 'favorite' column
//        static::addGlobalScope('favorite_pointer', function (Builder $builder) {
////            $builder->getQuery()->leftJoin('favorite_files', 'favorite_files.files_id', '=', 'files.id');
////
////            $exp = new Expression('`files`.*, IF(`favorite_files`.`files_id` = `files`.`id`, TRUE, FALSE) as `favorite`');
////            $builder->getQuery()->select($exp);
//
//            $builder->addSelect(new Expression('`users_files`.`favorite` as `favorite`'));
//        });
//    }

    public function scopePivot($query) {
        $query->addSelect(new Expression('`users_files`.`favorite` as `favorite`'));
        $query->addSelect(new Expression('`users_files`.`permissions` as `permissions`'));
        return $query->addSelect(new Expression('`files`.*'));
    }

    static private function _getNameIfIsset($fileAttr) {
        $user = User::find($fileAttr['users_id']);

        // Check wheter file with this name does not exist
        $check = $user->files()
            ->where('parent_id', $fileAttr['parent_id'])
            ->where('name', $fileAttr['name'])
            ->get()
            ->toArray();

        if(!empty($check)) {
            // Check wheter files with same name exist
            $check = $user->files()
                ->where('parent_id', $fileAttr['parent_id'])
//                ->where('name', 'REGEXP', '^'. $name .'([[.(.]][[:digit:]]+[[.).]])*[[...]].*$')
                ->where('name', 'REGEXP', '^'. $fileAttr['name'] .' [[.(.]][[:digit:]]+[[.).]]$')
                ->orderBy('name', 'DESC')
                ->first();

            // Generating new name
            if($check != null) {
                preg_match('/^'. $fileAttr['name'] . ' \(([[:digit:]]+)\)$/', $check->name, $matches);

                $fileAttr['name'] .= ' ('. ($matches[1]+1) . ')';
            }else {
                $fileAttr['name'] .= ' (1)';
            }
        }

        return $fileAttr['name'];
    }

    public function user()
    {
        return $this->belongsTo('App\User', 'users_id');
    }

    static public function addFile($fileAttr, User $user = null) {
        if($user == null) {
            $user = User::findOrFail($fileAttr['users_id']);
        }

        $fileAttr['users_id'] = $user->id;

        // File type
        $fileAttr['type'] = 1;

        $fileAttr['name'] = self::_getNameIfIsset($fileAttr);

        return $user->files()->save(new File($fileAttr));
//        return File::create($fileAttr);
    }

    static public function addFolder($fileAttr, User $user = null) {
        if($user == null) {
            $user = User::findOrFail($fileAttr['users_id']);
        }

        $fileAttr['users_id'] = $user->id;

        // Folder type
        $fileAttr['type'] = 0;

        $fileAttr['name'] = self::_getNameIfIsset($fileAttr);

        return $user->files()->save(new File($fileAttr));
//        return File::create($fileAttr);
    }

    public function sendFile() {
        $response = response(Storage::get($this->path));

        $response->withHeaders([
            'Content-Type' => 'application/force-download',
            'Content-Disposition' => 'attachment; filename="'. $this->name .'";',
            'Content-Length' => Storage::size($this->path),
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => Storage::lastModified($this->path),
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache'
        ]);

        return $response;
    }

    public function isFolder() {
        return $this->type == 0;
    }

    public function isFile() {
        return $this->type == 1;
    }

    public function getFolders() {
        return File::where('parent_id', $this->id)
            ->where('type', 0)
            ->get();
    }

    public function getFiles() {
        return File::where('parent_id', $this->id)
            ->where('type', 1)
            ->get();
    }

    public function getAbsolutePath() {
        return storage_path('app'). DIRECTORY_SEPARATOR . $this->path;
    }

    public function delete() {
        if($this->isFolder()) {
            $files = File::where('parent_id', $this->id)
                ->where('users_id', $this->users_id)
                ->orderBy('type', 'DESC')
                ->get();

            foreach ($files as $file) {
                $file->delete();
            }
            Storage::deleteDirectory($this->path);
        }else {
            Storage::delete($this->path);
        }

        return parent::delete();
    }

    public function addToFavorites(User $user = null) {
        if(! $user) {
            $user = Auth::user();
        }

        if($this->pivot->favorite) {
            return true;
        }

        return $user->files()->updateExistingPivot($this->id, ['favorite' => true]);
    }

    public function removeFromFavorites(User $user = null) {
        if(! $user) {
            $user = Auth::user();
        }

        if(! $this->pivot->favorite) {
            return true;
        }

        return $user->files()->updateExistingPivot($this->id, ['favorite' => false]);
    }

    public function tag($tagId, $user = null) {
        if(! $user) {
            $user = Auth::user();
        }

        if($this->pivot['tag_id'] == $tagId) {
            return $user->files()->updateExistingPivot($this->id, ['tag_id' => null]);
        }else {
            return $user->files()->updateExistingPivot($this->id, ['tag_id' => $tagId]);
        }
    }
}
