<?php namespace JobProgress\Repositories;

use Resource;
use JobProgress\Contexts\Context;
use JobProgress\Jobs\Events\DocumentUploaded;
use JobProgress\Jobs\Events\JobDocumentDeleted;
use DB;
use Config;
use Auth;
use JobProgress\Resources\Exceptions\InvalidResourcePathException;

Class ResourcesRepository extends ScopedRepository{

    /**
     * The base eloquent model
     * @var Eloquent
     */
    protected $model;
    protected $scope;

    function __construct(Resource $model, Context $scope){
        
        $this->model = $model;
        $this->scope = $scope;
    }

    /**
     * check if directory with given name exists
     * @param $name String | name of the directory to check
     * @param $parent_id int | Parent directory to check under
     * @return  bool  
     */
    public function isDirExistsWithName($name, $parentId){
        $count = $this->make()->dir()->where(['name' => $name,'parent_id' => $parentId])->count();
        
        return ($count > 0);
    }

    /**
     * @param $name String | name of the directory
     * @param $parentDir node | parent directory for this new directory
     * @return bool
     */
    public function createRootDir( $dirName, $companyId ){
        
        $dir = new Resource(
            [
                'name' => $dirName,
                'company_id' => $companyId,
                'size' => 0,
                'thumb_exists' => false,
                'path' => $dirName,
                'is_dir' => true,
                'mime_type' => null,
                'locked' => true,
                'created_by' => Auth::id()
            ]
        );
        $dir->save();
        
        return $dir->makeRoot();
    }

    /**
     * @param $name String | name of the directory
     * @param $parentDir node | parent directory for this new directory
     * @return bool
     */
    public function createDir($dirName, $parentDir, $locked = false, $name = null, $meta = array()){
        if(!$name) { $name = $dirName; }

        try {

            $resouceData =   [
                'name' => $name,
                'company_id' => $parentDir->company_id,
                'size' => 0,
                'thumb_exists' => false,
                'path' => $parentDir->path.'/'.strtolower($dirName),
                'is_dir' => true,
                'mime_type' => null,
                'locked' => $locked,       
                'created_by' => Auth::id(),
                'admin_only' => ine($meta, 'admin_only'),
                'user_id' => ine($meta, 'user_id') ? $meta['user_id'] : null,
            ];

            if(ine($meta, 'stop_transaction')) {
                $dir = new Resource($resouceData);
                $dir->parent_id = $parentDir->id;
                $dir->save();
                $this->saveResourceMeta($dir->id, $meta);
            } else {
                $dir = safeTransaction(function() use($resouceData, $parentDir, $meta){
                    $dir = new Resource($resouceData);
                    $dir->parent_id = $parentDir->id;
                    $dir->save();
                    $this->saveResourceMeta($dir->id,$meta);
                    return $dir;
                });
            }

        }catch(\Exception $e) {
            throw $e;
        }

        return $dir;
    }

    /**
     * @param $name String | name of the file
     * @param $parentDir node | parent directory for this new directory
     * @param mimeType  | mime type of file
     * @param size | size of file
     * @return bool
     */
    public function createFile($name, $parentDir, $mimeType, $size, $physicalName, $jobId = Null, $meta = array())
    {
        try {
            $fileData = [
                'name' => $name,
                'company_id' => $parentDir->company_id,
                'size' => $size,
                'thumb_exists' => ine($meta, 'thumb_exists'),
                'path' => $parentDir->path.'/'.$physicalName,
                'is_dir' => false,
                'mime_type' => $mimeType,
                'created_by' => \Auth::id(),
                'admin_only' => ine($meta, 'admin_only'),
                'multi_size_image' => false,
            ];

            if(ine($meta, 'stop_transaction')) {
                $file = new Resource($fileData);
                $file->parent_id = $parentDir->id;
                $file->save();
                $this->saveResourceMeta($file->id, $meta);
            } else {
                $file = safeTransaction(function() use($fileData, $parentDir, $meta){
                    $file = new Resource($fileData);
                    $file->parent_id = $parentDir->id;
                    $file->save();
                    $this->saveResourceMeta($file->id, $meta);
                    return $file;
                });
            }

        }catch(\Exception $e) {
            throw $e;
        }

        //fire event..
        $this->fireEventForJobsFilesUpload($jobId,$file);

        //multi size image hide (Temporary fixed for mobile and web)
        $file->setHidden(['multi_size_images']);

        if(in_array($mimeType, config('resources.image_types'))) {
            \Queue::push('JobProgress\Queue\ResourceQueueHandler@createMultiSizeImage', ['id' => $file->id]);
        }

        return $file;
    }

    /**
     * copy files with reference id
     * @param  $parentDir
     * @param  $resource
     * @return $file
     */
    public function copyWithRefrence($parentDir, $resource)
    {
        $file = new Resource(
            [
                'name'          => $resource->name,
                'parent_id'     => $parentDir->id,
                'company_id'    => $parentDir->company_id,
                'size'          => $resource->size,
                'thumb_exists'  => $resource->thumb_exists,
                'path'          => $resource->path,
                'is_dir'        => false,
                'mime_type'     => $resource->mime_type,
                'created_by'    => Auth::id(),
                'reference_id'  => $resource->id,
            ]
        );

        $file->save();

        return $file;
    }

    /**
     * get a directory resource
     * @param $id int | id of the directory
     */
    public function getDir($id){
        $dir = $this->make()->dir()->where('id', $id)->first();

        return $dir;
    }

    /**
     * check if a resource with given id exists
     * @param $id int | id of the resource to check
     * @return $bool
     */
    public function isResourceExists($id)
    {
        $resource = $this->make()->where('id', $id);

        return ($resource->count() > 0);
    }

    /**
     * Check company resources
     * @param  Array  $ids  Resource Ids
     * @return boolean
     */
    public function  hasCompanyResources($ids) 
    {
        $ids = arry_fu((array)$ids);

        return  ($this->make()->whereIn('id', $ids)->count() == count($ids));

    }

    /**
     * check if given is a blank directory
     * @param $id int | id of the directory resource to check
     * @return bool
     */
    public function isEmptyDir($id){

        $dir = $this->make()->dir()->where('id', $id)->first();
        return $dir->isLeaf();
    }

    /**
     * check if locked directory
     * @param $id int | id of the directory resource to check
     * @return bool
     */
    public function isLocked($id){

        $dir = $this->make()->dir()->where('id', $id)->first();
        return $dir->locked;
    }
    
    /**
     * remove a directory resource
     * @param $id int | id of the directory resource to remove
     * @return bool
     */
    public function removeDir($id,$force){

        if($force){
            $ids = [];
            $recursiveDir = $this->getRecursive($id);
            foreach ($recursiveDir as $key => $value) {
                $ids[] = $value->id; 
            }
            if(!empty($ids)){
                Resource::where('company_id', $this->scope->id())
                    ->whereIn('id', $ids)->get()->each(function($resource) {
                        $resource->delete();
                    });
            }
        }

       $dir = Resource::where('company_id', $this->scope->id())->where('id', $id)->first();
       return $dir->delete();
        
    }

    /**
     * get file resource by id
     * @param $id int | id of the file resource 
     * @return Resource node
     */
    public function getFile($id){
        
        $file = $this->make()->file()->where('id', $id)->firstOrFail();
        return $file;
    }

    /**
     * Get Files
     * @param  Array $ids  Array of Ids
     * @return Response
     */
    public function getFiles($ids)
    {
        $files = $this->make()->file()->whereIn('id', (array)$ids);
        if(Auth::user()->isSubContractorPrime()) {
            $files->subOnly(Auth::id());
        }

        return $files->get();
    }
    
    /**
     * remove a file resource
     * @param $id int | id of the file resource to remove
     * @return resource
     */
    public function removeFile($id, $jobId){
        $this->deleteFiles($id, $jobId);

        return true;
    }

    /**
     * Remove Multiple Files
     * @param  array  $ids   Array of resource ids
     * @param  Int    $jobId Job Id
     * @return Boolean
     */
    public function removeFiles($ids = array(), $jobId = null)
    {
        $this->deleteFiles($ids, $jobId);

        return true;
    }
    
    /**
     * get all resources
     * @param $id int | id of the resource
     * @param $recursive boolean | fetch recursive records
     * @param $keyword string | for search
     * @return bool
     */
    public function getResources($id,$recursive,$filters = array()){
        
        $root = $this->make()->where('id',$id)->first();
        if(!$root) return false;// if not root found return false..
        
        $resources = null;
        if($recursive){
            $resources = $root->descendants();
            $resources->orderBy('id', 'desc');
        }else{
            $resources = $root->immediateDescendants(null, 'desc');
        }
        $this->applyFilter($resources,$filters);

        return $resources;
    }

    public function getRecentResourceFiles($id, $limit = null, $filters = [])
    {
        $root = $this->make()
                     ->where('id',$id)
                     ->first();
    
        $query = $root->descendants()
                      ->where('is_dir','=',false)
                      ->whereCompanyId($root->company_id)
                      ->orderBy('created_at','desc')
                      ->take($limit);
        
        $this->applyFilter($query, $filters);

        return $query->get();
        
        // return Resource::descendantFiles($root->id)
        // ->orderBy('created_at','desc')
        // ->take($limit)
        // ->get();
    } 

     /**
     * rename resource
     * @param $id int | id of resource to rename
     * @param $name string | name of resource
     * @return bool
     */
    public function rename($id,$name){
        $resource = $this->make()->where('id',$id)->first();

        if(Auth::user()->isSubContractorPrime() && ($resource && $resource->created_by != Auth::id())) {
            throw new InvalidResourcePathException("You cannot rename this file/directory.");
        }
        $resource->update(array('name'=>$name));
        
        return $resource;
    }

    public function getRecursive($id){

        $dir = $this->getDir($id);
        return $dir->descendants()->get();
    }

    public function saveResourceMeta($resourceId,$metaData) {
        if(!ine($metaData,'key') && !ine($metaData,'value')) return false;

        \ResourceMeta::create([
            'resource_id' => $resourceId,
            'key'         => $metaData['key'],
            'value'       => $metaData['value']
        ]);

        return true;
    }

    /**
     * Get Shared Files
     * @param  array  $filters Filters
     * @return files
     */
    public function getSharedFiles($id, $filters = array())
    {
        $root = $this->make()
                     ->where('id',$id)
                     ->first();

        if(!$root) return false;
        
        $query = $root->descendants()
                      ->where('is_dir','=',false)
                      ->whereCompanyId($root->company_id)
                      ->whereShareOnHop(true)
                      ->orderBy('share_on_hop_at', 'desc');

        $this->applyFilter($query, $filters);

        return $query->get();
    }

    /**
     * Creat Instance Photo Dir
     * Update Admin Only
     * @param  Array  $ids        Resource Ids
     * @param  boolean $adminOnly Boolean
     * @return Resources
     */
    public function updateAdminOnly($ids, $adminOnly)
    {
        $resources = $this->make()->whereIn('id', (array)$ids)
            ->update(['admin_only' => $adminOnly]);

        return $resources;
    }
        
     /* Create Instance Photo Dir
     * @return $dir
     */
    public function createInstancePhotoDir()
    {
        $rootDir = $this->getCompanyRootDir();
        $dir = $this->createDir(Resource::INSTANT_PHOTO_DIR, $rootDir, $locked = true);

        //save Instant Photo Resource Id.
        \CompanyMeta::create([
            'company_id' => $this->scope->id(),
            'key'        => \CompanyMeta::INSTANT_PHOTO_RESOURCE_ID,
            'value'      => $dir->id,
        ]);

        return $dir;
    }

    /**
     * Get Company Root Dir
     * @return $dir
     */
    public function getCompanyRootDir()
    {
        $dir = $this->make()->whereNull('parent_id')->first();

        return $dir;
    }

    /**
     * create google drive video link
     * 
     * @param  object  $parentDir
     * @param  string  $name
     * @param  string  $url
     * @param  string  $type
     * @param  integer $size
     * @param  string  $mimeType
     * @param  string  $thumbUrl
     * @param  array   $input
     * 
     * @return $resource
     */
    public function createLink($parentDir, $name, $url, $type, $size = 0, $mimeType = null, $thumbUrl = null, $input = array())
    {
        $resource = $this->model;

        $resource->company_id = $parentDir->company_id;
        $resource->parent_id  = $parentDir->id;
        $resource->name       = $name;
        $resource->path       = $url;
        $resource->size       = $size;
        $resource->mime_type  = $mimeType;
        $resource->created_by = Auth::id();
        $resource->type       = $type;
        $resource->thumb      = $thumbUrl;
        $resource->save();

        return $resource;
    }
    

    /******************** Private Section *********************/

    private function applyFilter($query, $filters) {

        if(ine($filters,'keyword')) {
            $query->where('name', 'LIKE','%'.$filters['keyword'].'%');
        }

        if(ine($filters,'type')) {
            if($filters['type'] == "file") {
                $query->file();   
            }elseif($filters['type'] == "dir"){
                $query->dir();   
            }
        }

        if(ine($filters,'mime_type') && $filters['mime_type'] == 'images') {
            $query->whereIn('mime_type', Config::get('resources.image_types'));
        }

        if(ine($filters,'mime_type') && $filters['mime_type'] == 'files') {
            $query->whereIn('mime_type', Config::get('resources.docs_types'));
        }
        
        //exclude admin only directory/files for standard user        
        if(Auth::user() && !Auth::user()->isAuthority()) {
            $query->excludeAdminOnlyDirectory();
        }

        //created by
        if(ine($filters, 'created_by')) {
            $query->whereCreatedBy($filters['created_by']);
        }

        //date filter
        if(ine($filters, 'date')) {
            $query->date($filters['date']);
        }

        //start date and end_date
        if((ine($filters, 'start_date') || ine($filters, 'end_date')) && !ine($filters, 'date') ) {
            $startDate = ine($filters, 'start_date') ? $filters['start_date'] : null;
            $endDate   = ine($filters, 'end_date') ? $filters['end_date'] : null;
            $query->dateRange($startDate, $endDate);
        }

        if(ine($filters, 'dir_with_only_img')) {
            $query->where(function($query) {
                $query->where('is_dir', true)
                    ->orWhereIn('mime_type', config('resources.image_types'));
            });
        }
    }

    private function fireEventForJobsFilesUpload($jobId,$file) {
        if(empty($jobId)) return false;
        try{
            $job = \Job::findOrFail($jobId);
            if(!$job) return false;

            \Event::fire('JobProgress.Jobs.Events.DocumentUploaded', new DocumentUploaded($job, $file));
        }catch(\Exception $e) {
            //handle exception..
        }
    }

    /**
     * Delete Files
     * @param  Array $ids    Array of Ids
     * @param  Int   $jobId  Job Id
     * @return Response
     */
    private function deleteFiles($ids, $jobId = null)
    {
        $files = $this->getFiles($ids);

        if(!$files->count()) return true;

        foreach ($files as $file) {
            $references = $this->model->whereReferenceId($file->id);
            $references->delete();
            $file->delete();
        }

        $this->fireEventForJobFilesDeleted($jobId, $files);

        return true;
    }

    private function fireEventForJobFilesDeleted($jobId, $files){

        if(empty($jobId)) return false;
        try{
            $job = \Job::findOrFail($jobId);
            if (!$job) return false;
            
            \Event::fire('JobProgress.Jobs.Events.JobDocumentDeleted', new JobDocumentDeleted($job, $files));

        }catch(\Exception $e){
            //handle exception..
        }
    }

}
