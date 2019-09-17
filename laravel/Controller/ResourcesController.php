<?php
namespace JobProgress\Resources\Controller;
use JobProgress\Resources\ResourceServices;
use Resource;
use JobProgress\Transformers\ResourcesTransformer;
use JobProgress\Resources\Exceptions\DirExistsExceptions;
use JobProgress\Resources\Exceptions\InvalidResourcePathException;
use JobProgress\Resources\Exceptions\DirNotEmptyExceptions;
use JobProgress\Resources\Exceptions\FileNotFoundExceptions;
use JobProgress\Resources\Exceptions\InvalidFileException;
use JobProgress\Resources\Exceptions\LockedDirException;
use Sorskod\Larasponse\Larasponse;
use ApiResponse;
use Input;
use Validator;
use JobProgress\Contexts\Context;
use Carbon\Carbon;
use Job;

class ResourcesController extends \ApiController {

	protected $response;
	/**
	 * JobProgress\Resources\ResourceServices;
	 */
	protected $resourceService;
	protected $scope;

	public function __construct(ResourceServices $resourceService, Larasponse $response,Context $scope){
		$this->resourceService = $resourceService;
		$this->response = $response;
		$this->scope = $scope;
		parent::__construct();

		if(Input::get('includes')) {
			$this->response->parseIncludes(Input::get('includes'));	
		}
	}

	public function resources(){

		$input = Input::all();
		
		$parent_id = (Input::has('parent_id')) ? (int)$input['parent_id'] : null;
		$recursive = (Input::has('recursive')) ? (bool)$input['recursive'] : null;
		$limit = isset($input['limit']) ? $input['limit'] : 0;

		$validator = Validator::make($input,Resource::getReourcesRules());
		if( $validator->fails() ){
			return ApiResponse::validation($validator);
		}

		// Company files (recursive fetch) stopped for Chandler subscriber..
		if(($parent_id == '497275') && ($recursive == 1)) {

			return ApiResponse::errorGeneral('Company file(s) temporary disabled for mobile app.');
		}

		try{

			if($recursive) {
				$resources = $this->resourceService->getResourceRecursive($parent_id, $input);
				return $resources;
			}

			$resources = $this->resourceService->getResources($parent_id, $input);
			$params['params'] = $input;

			if(!$limit) {
				$resources = $resources->get();
				$data = $this->response->collection($resources, new ResourcesTransformer);
				return ApiResponse::success(array_merge($data, $params));
				
			}
			$resources = $resources->paginate($limit);
			$data = $this->response->paginatedCollection($resources, new ResourcesTransformer);

			return ApiResponse::success(array_merge($data, $params));

		}catch(InvalidResourcePathException $e){
			return ApiResponse::errorNotFound($e->getMessage());
		}
	}

	public function recursiveSearch() 
	{
		$input = Input::all();
		
		$validator = Validator::make($input, [
			'root_id' 	=> 'required',
			'parent_id' => 'required',
			'keyword'	=> 'required',
		]);

		if( $validator->fails() ){
			return ApiResponse::validation($validator);
		}
		
		try{
			
			$resources = $this->resourceService->recursiveSearch($input['parent_id'], $input);

			$limit = ine($input,'limit') ? $input['limit'] : config('jp.pagination_limit');

			if(!$limit) {
				$resources = $resources->get();

				return ApiResponse::success($this->response->collection($resources, new ResourcesTransformer));
				
			}
			$resources = $resources->paginate($limit);

			return ApiResponse::success($this->response->paginatedCollection($resources, new ResourcesTransformer));

		}catch(InvalidResourcePathException $e){

			return ApiResponse::errorNotFound($e->getMessage());
		}	
	}

	// resources/recent
	public function recent_document(){
		$input = Input::all();
		
		$parent_id = (Input::has('parent_id')) ? (int)$input['parent_id'] : null;
		
		$limit = (Input::has('limit')) ? (int)$input['limit'] : 2;

		$validator = Validator::make($input,Resource::getReourcesRules());
		if( $validator->fails() ){
			return ApiResponse::validation($validator);
		}
		
		try{
			$resources = $this->resourceService->getRecentResourceFiles($parent_id,$limit);
			return $resources;
		}catch(InvalidResourcePathException $e){
			return ApiResponse::errorNotFound($e->getMessage());
		}
		
	}
	public function create_dir(){
		$input = Input::only('name','parent_id');

		$validator = Validator::make($input,Resource::getCreateDirRules());
		if( $validator->fails() ){
			return ApiResponse::validation($validator);
		}

		try{	
			$ret = $this->resourceService->createDir($input['name'], $input['parent_id']);
			return ApiResponse::success([
				'message' => \Lang::get('response.success.resource_dir_created'),
				'resources' => $this->response->item($ret, new ResourcesTransformer)
			]);
		}catch(DirExistsExceptions $e){
			return ApiResponse::errorGeneral($e->getMessage());
		}catch(InvalidResourcePathException $e){
			return ApiResponse::errorNotFound($e->getMessage());
		}
		
		return ApiResponse::errorInternal();
	}

	public function remove_dir($id, $force = 0){
		
		try{
			$this->resourceService->removeDir($id,$force);
			return ApiResponse::success(['message' => \Lang::get('response.success.deleted',['attribute' => 'Directory'])]);
		}catch(InvalidResourcePathException $e){
			return ApiResponse::errorNotFound($e->getMessage());
		}catch(DirNotEmptyExceptions $e){
			return ApiResponse::errorGeneral($e->getMessage());
		}catch(LockedDirException $e){
			return ApiResponse::errorGeneral($e->getMessage());
		}catch(\Exceptions $e) {
			return ApiResponse::errorInternal(\Lang::get('response.error.internal'),$e);
		}
	}

	public function remove_file($id){
		try{
			$input = Input::only('job_id');
			$this->resourceService->removeFile($id, $input['job_id']);
			return ApiResponse::success(['message' => \Lang::get('response.success.deleted',['attribute' => 'File'])]);
		}catch(FileNotFoundExceptions $e){
			return ApiResponse::errorNotFound($e->getMessage());
		}
	}

	/**
	 * Remove Multiple Files
	 * @return Response
	 */
	public function removeMultipleFiles()
	{
		$input = Input::only('resource_ids', 'job_id');
		$validator = Validator::make($input, ['resource_ids' => 'required|max_array_size:'.config('jp.image_multi_select_limit')]);
		if( $validator->fails() ){
			return ApiResponse::validation($validator);
		}
		try{
			$this->resourceService->removeFiles((array)$input['resource_ids'], $input['job_id']);
			return ApiResponse::success(['message' => \Lang::get('response.success.deleted',['attribute' => 'File'])]);
		}catch(FileNotFoundExceptions $e){
			return ApiResponse::errorNotFound($e->getMessage());
		}	
	}
	public function rename(){
		$input = Input::only('id','name');

		$validator = Validator::make($input,Resource::getRenameRules());
		if( $validator->fails() ){
			return ApiResponse::validation($validator);
		}

		try{
			$resource = $this->resourceService->rename($input['id'],$input['name']);
			
			return ApiResponse::success([
				'message'  => \Lang::get('response.success.rename',['attribute' => 'Resource']),
				'resource' => $this->response->item($resource, new ResourcesTransformer)
			]);
		}catch(InvalidResourcePathException $e){
			return ApiResponse::errorNotFound($e->getMessage());
		}
	}

	public function upload_file(){
		$input = Input::only('file', 'parent_id', 'image_base_64', 'job_id', 'make_pdf', 'rotation_angle', 'name');

		$validator = Validator::make($input,Resource::uploadFileRules());
		if( $validator->fails() ){
			return ApiResponse::validation($validator);
		}

		$job = Job::find($input['job_id']);

		if(!$input['image_base_64'] && !(Input::hasFile('file'))){
			return ApiResponse::errorGeneral(\Lang::get('response.error.invalid', ['attribute' => 'file']));
		}

		try{
			$file = $this->resourceService->uploadFile(
				$input['parent_id'], 
				$input['file'],
				$input['image_base_64'],
				$input['job_id'],
				$input
			);	
			return ApiResponse::success([
				'message' => \Lang::get('response.success.file_uploaded'),
				'file' => $file
			]);
		}catch(InvalidResourcePathException $e){
			return ApiResponse::errorNotFound($e->getMessage());
		}catch(\Exception $e) {
			return ApiResponse::errorInternal(\Lang::get('response.error.internal'),$e);
		}
	}

	public function get_file(){
		$input = Input::only('id','download','base64_encoded');

		$validator = Validator::make($input,Resource::getFileRules());
		if( $validator->fails() ){
			return ApiResponse::validation($validator);
		}

		try{
			$file = $this->resourceService->getFile(
				$input['id'],
				(bool)$input['download'],
				(bool)$input['base64_encoded']
			);
		}catch(InvalidResourcePathException $e){
			return ApiResponse::errorNotFound($e->getMessage());
		}catch(InvalidFileException $e){
			return ApiResponse::errorGeneral($e->getMessage());
		}catch(\Exception $e) {
			return ApiResponse::errorInternal(\Lang::get('response.error.internal'),$e);
		}
		if((bool)$input['base64_encoded']) {
			return ApiResponse::success(['data' => $file]);
		}
		return $file;
	}

	public function get_thumb() {
		$input = Input::only('id');

		$validator = Validator::make($input,Resource::getFileRules());
		if( $validator->fails() ){
			return ApiResponse::validation($validator);
		}

		try{
			$file = $this->resourceService->getThumb($input['id']);
			return $file;
		}catch(InvalidResourcePathException $e){
			return ApiResponse::errorNotFound($e->getMessage());
		}
	}

	public function edit_image_file($id) {
		$input = Input::only('base64_string','rotation_angle');
		$validator = Validator::make($input,['base64_string' => 'required|string']);
		if( $validator->fails() ){
			return ApiResponse::validation($validator);
		}
		
		try{
			$file = $this->resourceService->editImage($id, $input['base64_string'], $input);
			return $file;
		}catch(InvalidFileException $e){
			return ApiResponse::errorGeneral($e->getMessage());
		}catch(FileNotFoundExceptions $e){
			return ApiResponse::errorNotFound($e->getMessage());
		}catch(\Exceptions $e){
			return ApiResponse::errorInternal(\Lang::get('response.error.internal'),$e);
		} 
	}

	public function rotate_image_file($id){
		$input = Input::only('rotation_angle');
		$validator = Validator::make($input,['rotation_angle' => 'numeric'] );
		if ($validator->fails()) {
			return ApiResponse::validation($validator);
		}

		if($input['rotation_angle'] == '') {
			return ApiResponse::errorGeneral(trans('response.error.roation_angle_required'));
		}

		try{
			$resource = $this->resourceService->rotateImage($id, $input['rotation_angle']);
			
			return ApiResponse::success([
				'message'  => trans('response.success.rotated', ['attribute' => 'Image']),
				'resource' => $this->response->item($resource, new ResourcesTransformer)
			]);
		} catch(InvalidFileException $e){
			return ApiResponse::errorGeneral($e->getMessage());
		}catch(FileNotFoundExceptions $e){
			return ApiResponse::errorNotFound($e->getMessage());
		}catch(\Exceptions $e){
			return ApiResponse::errorInternal(\Lang::get('response.error.internal'),$e);
		}
	}	

	public function show($id){
		try{
			$resourceRepo = \App::make('JobProgress\Repositories\ResourcesRepository');
		 	$resource = $resourceRepo->getFile($id);
		 	return ApiResponse::json(['data' => $this->response->item($resource, new ResourcesTransformer) ]);
		}catch(\Exceptions $e){
			return ApiResponse::errorInternal(\Lang::get('response.error.internal'),$e);
		}
	}

	/**
	 * Post /resources/move
	 * [move file or directroy to another directory]
	 * @return [Response] [description]
	 */
	public function move()
	{
		$input = Input::only('resource_id', 'move_to', 'resource_ids', 'move_to_job_id', 'move_from_job_id');
		$validator = Validator::make($input, Resource::getMoveRules());
		if ($validator->fails()) {
			return ApiResponse::validation($validator);
		}
		$resourceIds = $input['resource_id'];

		try {
			
			if(!$resourceIds) {
				$resourceIds = $input['resource_ids'];
			}

			$this->resourceService->move($resourceIds, $input['move_to'], $input);

			return ApiResponse::success([
				'message' => trans('response.success.resource_moved')
			]);

		} catch(InvalidResourcePathException $e) {

			return ApiResponse::errorNotFound($e->getMessage());
		} catch(FileNotFoundExceptions $e){
			return ApiResponse::errorNotFound($e->getMessage());
		} catch (Exceptions $e) {

			return ApiResponse::errorInternal(trans('response.error.internal'), $e);
		}
	}

	/**
	 * Resouce Copy
	 * POST resources/copy
	 * @return Json Response
	 */
	public function copy()
	{
		$input = Input::only('resource_ids', 'copy_to');

		$validator = Validator::make($input, Resource::getCopyRules());
		if ($validator->fails()) {

			return ApiResponse::validation($validator);
		}

		try {
			$this->resourceService->resouceCopy($input['copy_to'], (array)$input['resource_ids']);

			return ApiResponse::success([
				'message' => trans('response.success.copied', ['attribute' => 'File'])
			]);

		} catch(InvalidResourcePathException $e) {

			return ApiResponse::errorNotFound($e->getMessage());
		} catch (Exceptions $e) {

			return ApiResponse::errorInternal(trans('response.error.internal'), $e);
		}
	}

	/**
	 * Resource Share on home owner page
	 * @param  int $id 		Proposal Id
	 * @return Response
	 */
	public function shareOnHomeOwnerPage($id)
	{
		$input = Input::only('share');
		$resource = $this->resourceService->getByid($id);

		if ($resource->isGoogleDriveLink()) {
			return ApiResponse::errorGeneral("This file can't be shared on customer web page");
		}
		
		$resource->share_on_hop = ($input['share']);
		$resource->share_on_hop_at = ($input['share']) ? Carbon::now() : null;
		$resource->update();

		return ApiResponse::success([
			'message' => trans('response.success.updated', ['attribute' => 'Customer Web Page'])
		]);
		
	}

	/**
	 * Upload Instant Photos
	 * @return Response
	 */
	public function uploadInstantPhotos()
	{
		$input = Input::only('file');

		$validator = Validator::make($input, Resource::getOnlyFileUploadRule());
		if( $validator->fails() ){
			return ApiResponse::validation($validator);
		}
		
		try{
			$rootId = $this->resourceService->getInstantPhotoDirId();
			$file = $this->resourceService->uploadFile(
				$rootId,
				$input['file']
			);

			return ApiResponse::success([
				'message' => trans('response.success.file_uploaded'),
				'file' => $file
			]);
		}catch(InvalidResourcePathException $e){
			return ApiResponse::errorNotFound($e->getMessage());
		}catch(\Exception $e) {
			return ApiResponse::errorInternal(trans('response.error.internal'),$e);
		}
	}

	/**
	 * Get Instant Photos
	 * Get /resources/instant_photos
	 * @return Response
	 */
	public function getInstantPhotos()
	{
		$input = Input::all();
		$limit = isset($input['limit']) ? $input['limit'] : config('jp.pagination_limit');

		$resources = $this->resourceService->getInstantPhotos($input);

		if(!$limit) {
			$resources = $resources->get();

			return ApiResponse::success($this->response->collection($resources, new ResourcesTransformer));
		}
		$resources = $resources->paginate($limit);

		return ApiResponse::success($this->response->paginatedCollection($resources, new ResourcesTransformer));
		

	}

	/*	
	 * Put /resources/share_on_hop
	 * Multiple Files Share On Home Owner Page
	 * @return Response
	 */
	public function multipleShareOnHomeOwnerPage()
	{
		$input = Input::only('resource_ids', 'share');
		$validator = Validator::make($input, ['resource_ids' => 'required|max_array_size:'.config('jp.image_multi_select_limit')]);

		if( $validator->fails() ){
			return ApiResponse::validation($validator);
		}

		try {
			$this->resourceService->shareOnHOP((array)$input['resource_ids'], $input['share']);

			return ApiResponse::success([
				'message' => trans('response.success.updated', ['attribute' => 'Customer Web Page'])
			]);
		} catch(InvalidResourcePathException $e) {

			return ApiResponse::errorNotFound($e->getMessage());
		} catch(FileNotFoundExceptions $e){
			return ApiResponse::errorNotFound($e->getMessage());
		} catch (Exceptions $e) {

			return ApiResponse::errorInternal(trans('response.error.internal'), $e);
		}
	}

	/**
	 * create google drive link for google videos
	 * POST - /resources/create_link
	 * 
	 * @return Response
	 */
	public function createGoogleDriveLink()
	{
		$input = Input::all();

		$rules = [
			'type'		=> 'required|in:google_drive_link',
			'url'		=> 'required',
			'name'		=> 'required',
			'parent_id'	=> 'required',
		];

		$validator = Validator::make($input, $rules);

		if ($validator->fails()) {
			return ApiResponse::validation($validator);
		}

		try {
			$resource = $this->resourceService->createGoogleDriveLink($input);

			return ApiResponse::success([
				'message' => trans('response.success.created', ['attribute' => 'Link']),
				'data' => $this->response->item($resource, new ResourcesTransformer)
			]);

		} catch(InvalidResourcePathException $e) {

			return ApiResponse::errorNotFound($e->getMessage());
		} catch(FileNotFoundExceptions $e){

			return ApiResponse::errorNotFound($e->getMessage());
		} catch (Exception $e) {

			return ApiResponse::errorInternal(trans('response.error.internal'), $e);
		}
	}

	/**
	 * save file from estimation, proposal, material_list, work_order
	 *
	 * POST - /resources/save_file
	 * 
	 * @return response
	 */
	public function saveFile()
	{
		$input = Input::all();

		$rules = [
			'new_file_type' => 'required|in:estimation,proposal,work_order,material_list,resource',
			'file_type'		=> 'required|in:estimation,proposal,work_order,material_list,resource',
			'parent_id'		=> 'required_if:new_file_type,resource',
			'file_id'		=> 'required',
			'job_id'		=> 'required',
		];

		$validator = Validator::make($input, $rules);

		if ($validator->fails()) {
			return ApiResponse::validation($validator);
		}

		$job = \Job::findOrFail($input['job_id']);

		try {
			$resource = $this->resourceService->saveFile(
				$input['file_id'],
				$input['job_id'],
				$input
			);
			
		} catch (InvalidResourcePathException $e) {

			return ApiResponse::errorNotFound($e->getMessage());
		} catch (Exception $e) {

			return ApiResponse::errorInternal(trans('response.error.internal'), $e);
		}

		return ApiResponse::success([
			'message' => trans('response.success.saved', ['attribute' => 'File'])
		]);
	}
}