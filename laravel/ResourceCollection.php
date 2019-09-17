<?php 
namespace JobProgress\Resources;

use Illuminate\Database\Eloquent\Collection;

class ResourceCollection extends Collection{

    public function toTree()
    {
        $items = $this->items;

        return new static($this->makeTree($items));
    }
	
    /**
     * Performs actual tree building.
     *
     * @param array $items
     * @return array
     */
    protected function makeTree(array &$items)
    {
        $result = [];
        $tops = [];

        foreach($items as $item)
        {
            if($item->is_dir) {
                $item->no_of_child = $item->children()->count();
            }

            $result[$item->getKey()] = $item;
        }
        
        $relation = 'children';
        foreach($items as $item)
        {
            $parentId = $item->parent_id;

            if (array_key_exists($parentId, $result))
            {
                if ( ! array_key_exists($relation, $result[$parentId]->getRelations()))
                {
                    $result[$parentId]->setRelation($relation, new Collection([$item]));
                }
                else
                {
                    $result[$parentId]->getRelation($relation)->add($item);
                }
            }
            else
            {
                $tops[] = $item;
            }
        }

        return $tops;
    }
}