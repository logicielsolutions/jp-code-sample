<?php 
namespace JobProgress\Resources\Models;

use \Carbon\Carbon;
use Input;

class ResourceBaseModel extends \Eloquent {

	/**
     * Makes model a root with given position.
     *
     * @param int $p | position
     * @return root
     */
	public function makeRoot($p=0) 
    {
        $this->last_moved_at = Carbon::now();
        $this->save();

        return $this;
	}

	/**
     * Appends a child to the model.
     *
     * @param EntityInterface $parent
     * @return parent
     */
    public function makeChildOf($parent) 
    {
        $this->parent_id = $parent->id;
        $this->last_moved_at = Carbon::now();
        $this->save();

        return $this;
    }

    /**
     * Retrieves all descendants of a model.
     *
     * @return resources
     */
    public function descendants() 
    {
        $ids = [];
        $ids = $this->getDescendantIds($this->id);
        $ids[] = $this->id;
        return self::whereIn('parent_id', arry_fu($ids));
    }
    /**
     * Retrieves all ancestor of a model.
     *
     * @return resources
     */
    public function ancestors() 
    {
        $ids = [];
        $ids = $this->getAncestorsIds($this->id);

        return self::whereIn('id', $ids);
    }    

    /**
     * Indicates whether a model has children.
     *
     * @return bool
     */
	public function isLeaf() 
    {
		return !($this->childResource()->exists());
	}

    /**
     * Get direct children of node
     *
     * @return resources
     */
    public function immediateDescendants($lastMovedAt = null, $order = 'asc') 
    {
        return $this->children($lastMovedAt, $order);
    }

    /**
     * Get direct children of node
     *
     * @return resources
     */
    public function children($lastMovedAt = null, $order = 'asc') 
    {
        return $this->childResource()->orderBy('last_moved_at', $order);
    }

    /**
     * Get count of children
     * @return children count
     */
    public function countChildren() {
        $children = $this->childResource();

        if(Input::get('dir_with_only_img')) {
            $children->where(function($query) {
                $query->where('is_dir', true)
                    ->orWhereIn('mime_type', config('resources.image_types'));
            });
        }

        return $children->count();
    }

    /**
     * Move to another resource
     * @param  int    $moveTo         moveTo
     * @return boolean
     */
    public function moveTo($moveTo) {
        $this->parent_id = $moveTo;
        $this->last_moved_at = Carbon::now();
        $this->save();

        return $this;
    }

    /**
     * Relationship parent to its children
     * @return [type] [description]
     */
    public function childResource()
    {
        return $this->hasMany('Resource', 'parent_id', 'id');
    }

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    public static function boot()
    {
        parent::boot();

        static::creating(function(self $entity)
        {
            $entity->last_moved_at = $entity->updated_at;
        });

        // static::saved(function(self $entity)
        // {
        //     $original = $entity->getOriginal();
        //     if(ine($original, 'parent_id') 
        //         && ($original['parent_id'] != $entity->parent_id)) {
        //         $entity->reorderSiblings($entity->parent_id, $original['parent_id']);
        //     }
        // });
    }

    /**
     * Reorder Siblings
     * @param  int    $newParentId New Parent Id
     * @param  int    $oldParentId Old Parent Id
     * @return Boolean
     */
    // protected function reorderSiblings($newParentId, $oldParentId)
    // {
    //     self::where('parent_id', $oldParentId)
    //         ->where('id', '>', $this->id)
    //         ->where('position', '>=', $this->position)
    //         ->decrement('position');

    //     self::where('parent_id', $newParentId)
    //         ->where('id', '<>', $this->id)
    //         ->where('position', '>=', $this->position)
    //         ->increment('position');
    // }

    /**
     * Clamp Posotion
     * @return Boolean
     */
    // protected function clampPosition()
    // {
    //     $entity = $this->where('parent_id', '=', $this->parent_id)
    //         ->orderBy('position', 'desc')
    //         ->first();

    //     $position = !is_null($entity) ? (int)$entity->position + 1: null;
    //     $position = max(0, $position);
    //     $this->attributes['position'] = $position;
    // }

    /**
     * Get DescentantIds
     * @param  array  $rootIds     root ids
     * @param  array  $resourceIds resource ids
     * @return child ids
     */
    private function getDescendantIds($rootIds, $resourceIds = []) {
        $ids = self::whereIn('parent_id', (array)$rootIds)
            ->where('is_dir', true)
            ->distinct('parent_id')
            ->lists('id');
        if(empty($ids)) {
            return $resourceIds;
        }
        $resourceIds = array_merge($ids, $resourceIds);

        return $this->getDescendantIds($ids, $resourceIds);
    }

    /**
     * Get ancestorsIds
     * @param  int    $parentId    parent id
     * @param  array  $resourceIds array
     * @return parent ids
     */
    private function getAncestorsIds($parentId, $resourceIds = []) {
        $resource = self::where('id', $parentId)
            ->select('parent_id')
            ->whereNotNull('parent_id')
            ->first();
        if(!$resource) {
            return $resourceIds;
        }
        $resourceIds[] = $resource->parent_id;

        return $this->getAncestorsIds($resource->parent_id, $resourceIds);
    }
}