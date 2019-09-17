<?php 
namespace JobProgress\Resources;

interface ResourceModelInterface 
{
	/**
     * Makes model a root with given position.
     *
     * @param int $postion 
     * @return ResourceModelInterface
     */
	public function makeRoot($position);

	/**
     * Appends a child to the model.
     *
     * @param EntityInterface $parent
     * @return ResourceModelInterface
     */
    public function makeChildOf($parent);

    /**
     * Retrieves all descendants of a model.
     *
     * @return ResourceModelInterface
     */
    public function descendants();

    /**
     *  Indicates whether a model has children.
     *
     * @return bool
     */
	public function isLeaf();

    /**
     * Get direct children of node
     *
     * @return resources
     */
    // public function children();

    /**
     * Get direct children count
     *
     * @return resources
     */
    public function countChildren();
	
     /**
     * Makes the model a child or a root with given position.
     * 
     * @param Instance Resource
     * @return resource
     */
    public function moveTo($movedResource);
}