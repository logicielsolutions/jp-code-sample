<?php
namespace JobProgress\Resources\Traits;

trait ClosureTableTrait {

	/**
     * Makes model a root with given position.
     *
     * @param int $p | position
     * @return root
     */
	public function makeRoot($p=0) 
    {
		return parent::makeRoot($p);
	}

	/**
     * Appends a child to the model.
     *
     * @param EntityInterface $parent
     * @return parent
     */
    public function makeChildOf($parent) 
    {
    	$parent->addChild($this);
    	return $this;
    }

    /**
     * Retrieves all descendants of a model.
     *
     * @return resources
     */
    public function descendants() 
    {
        return $this->joinClosureBy('descendant');	
    }

    /**
     * Retrieves all ancestor of a model.
     *
     * @return resources
     */
    public function ancestors() 
    {
        return $this->joinClosureBy('ancestor');  
    }    

    /**
     * Indicates whether a model has children.
     *
     * @return bool
     */
	public function isLeaf() 
    {
		return !($this->hasChildren());
	}

    /**
     * Get direct children of node
     *
     * @return resources
     */
    public function immediateDescendants($position = null, $order = 'asc') 
    {
        return $this->children($position, $order);
    }

    /**
     * Get direct children of node
     *
     * @return resources
     */
    public function children($position = NULL, $order = 'asc') 
    {
        return parent::children($position, $order);
    }
}