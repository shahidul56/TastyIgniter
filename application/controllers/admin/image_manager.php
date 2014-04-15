<?php
class Image_manager extends CI_Controller {

	public function __construct() {
		parent::__construct(); //  calls the constructor
		$this->load->library('user');
	}

	public function index() {
		if (!file_exists(APPPATH .'views/admin/image_manager.php')) {
			show_404();
		}
			
		if (!$this->user->islogged()) {  
  			redirect('admin/login');
		}

    	if (!$this->user->hasPermissions('access', 'admin/image_manager')) {
  			redirect('admin/permission');
		}
		
		if ($this->session->flashdata('alert')) {
			$data['alert'] = $this->session->flashdata('alert');
		} else {
			$data['alert'] = '';
		}

		$data['title'] = 'Image Manager';
		$setting = $this->config->item('image_tool');

		$data['uploads'] = (isset($setting['uploads'])) ? TRUE : FALSE;
		$data['new_folder'] = (isset($setting['new_folder'])) ? TRUE : FALSE;
		$data['rename'] = (isset($setting['rename'])) ? TRUE : FALSE;
		$data['delete'] = (isset($setting['delete'])) ? TRUE : FALSE;		

		if (strpos($setting['root_folder'], '/') !== 0 OR strpos($setting['root_folder'], './') === FALSE) {
			$root_folder = $setting['root_folder'] .'/';
		} else {
			$root_folder = 'data/';
		}

		if ($this->input->get('sub_folder') AND strpos($this->input->get('sub_folder'),'../') === FALSE AND strpos($this->input->get('sub_folder'),'./') === FALSE) {
			$sub_folder = urldecode(trim(strip_tags($this->input->get('sub_folder')), '/') .'/');
			$remember_days = $setting['remember_days'];
			$this->input->set_cookie('last_sub_folder', $sub_folder, 86400 * (int)$remember_days);
		} else {
			$sub_folder = '';
		}

		if ($sub_folder === '') {
		 	if ($this->input->cookie('last_sub_folder')) {
				$sub_folder = $this->input->cookie('last_sub_folder');
			}
		}

		if ($sub_folder === "/") {
			$sub_folder = '';
		}

		$image_path 		= IMAGEPATH . $root_folder . $sub_folder;
		$image_base 		= base_url() .'assets/img/'. $root_folder;
		$thumbs_path 		= IMAGEPATH . 'thumbs/' . $sub_folder;
		$thumbs_base 		= base_url() .'assets/img/thumbs/';
		$parent 			= $sub_folder;

		$data['test_check'] = '';
		if ( ! is_dir($thumbs_path)) {
			$this->_createFolder($thumbs_path);
		}
				
		$popup = $data['popup'] = ($this->input->get('popup')) ? $this->_fixGetParams($this->input->get('popup')) : '';
		$field_id = $data['field_id'] = ($this->input->get('field_id')) ? $this->_fixGetParams($this->input->get('field_id')) : '';
		$filter = $data['filter'] = ($this->input->get('filter')) ? $this->_fixGetParams($this->input->get('filter')) : '';
		$sort_by = $data['sort_by'] = ($this->input->get('sort_by')) ? $this->_fixGetParams($this->input->get('sort_by')) : '';

		$get_params = http_build_query(array(
			'popup'    		=> $popup,
			'field_id'  	=> $field_id,
			'sort_by'  		=> $sort_by,
			'sub_folder'	=> ''
		));

		$data['refresh_url'] = current_url() .'?'. $get_params . $sub_folder .'&'. uniqid();
		$data['link'] = current_url() .'?'. $get_params;

		$sub_folder_array = explode('/', $sub_folder);

		$data['breadcrumbs'] = array();
		if (!empty($sub_folder_array)) {
			$tmp_path = '';
			$data['breadcrumbs'][] = array('name' => 'root', 'link' => $data['link']);
			foreach ($sub_folder_array as $key => $p_dir) { 
				$tmp_path .= $p_dir .'/';
				if ($p_dir != '') {
					$data['breadcrumbs'][] = array('name' => $p_dir, 'link' => $data['link'] . $tmp_path);
				}
			}
		}
		
		if (is_dir($image_path)) {
			$files = $this->_files($image_path);
			$data['folder_size'] = $this->_makeSize($this->_folderSize($image_path));
			$data['total_files'] = count($files) - 1;
		} else {
			$files = array();
			$data['folder_size'] = '';
			$data['total_files'] = 0;
		}
		
		$data['files'] = array();
		foreach($files as $k => $file) {
			if ($file['name'] == '..' AND $sub_folder == '') {
				continue;
			}
	
			$file_name = $file['name'];
			$file_type = $file['type'];

			$file_date = (!empty($file['date'])) ? mdate('%d %M %y', $file['date']) : '';
			$file_size = (!empty($file['size'])) ? $this->_makeSize($file['size']) : '0 B';
			$file_ext = (!empty($file['ext'])) ? $file['ext'] : '';
			$file_perms = substr(substr(sprintf('%o', fileperms($image_path . $file_name)), -4), 0, 2);

			$new_name = $this->_fixFileName($file_name);
			$human_name = $file_url = '';
		
			if ($file_name != '..' AND $file_name != $new_name) {
				$file_name = $new_name;
			}

			if ($file_type === 'back') {
				if (trim($sub_folder) != '') {
					$src = explode('/', $sub_folder);
					unset($src[count($src) - 2]);
					$src = implode('/', $src);
					if ($src == '') {
						$src = '/';
					}
					$file_url = current_url() .'?'. $get_params . rawurlencode($src) .'&'. uniqid();
				}
				$thumb_type = 'back';
				$html_class = 'back';
				$thumb_url = base_url() .'assets/img/manager_ico/folder_back.png';
			}

			if ($file_type === 'dir') {
				$human_name = $file_name;
				$thumb_type = 'dir';
				$html_class = 'directory';
				$thumb_url = base_url() .'assets/img/manager_ico/folder.png';
				$src = $sub_folder . $file_name . '/';
				$file_url = current_url() .'?'. $get_params . rawurlencode($src) .'&'. uniqid();
				if ( ! is_dir($thumbs_path . $file_name)) {
					$this->_createFolder($thumbs_path . $file_name);
				}
			}
	
			$img_dimension = $img_url = '';
			if ($file_type === 'img' OR $file_type === 'file') {
				$human_name = (isset($setting['show_ext'])) ? $file_name : substr($file_name, 0, '-' . (strlen($file_ext) + 1));
				$img_url = $image_base . $sub_folder . $file_name;
				$html_class = 'ff-item-type-1 file';
				$thumb_url = '';
			
				if ($file_type === 'img') {
					$html_class = 'ff-item-type-2 file';
					list($img_width, $img_height, $img_type, $attr) = getimagesize($image_path . $file_name);
					$img_dimension = $img_width .' x '. $img_height;
					$thumb_width = (isset($setting['thumb_width_mini'])) ? $setting['thumb_width_mini'] : 128;		
					$thumb_height = (isset($setting['thumb_height_mini'])) ? $setting['thumb_height_mini'] : 128;		
				
					if ($img_width < $thumb_width AND $img_height < $thumb_height) { 
						$thumb_type = 'original';
						$thumb_url = $image_base . $sub_folder . $file_name;
					} else {
						$img_path = $sub_folder . $file_name;
						$thumb_type = 'thumb';
						$this->load->model('Image_tool_model');
						$thumb_url = $this->Image_tool_model->resize($img_path, $thumb_width, $thumb_height);
					}
				}
	
				if ($thumb_url == '') {
					$thumb_type = 'icon';
					$thumb_url = base_url() .'assets/img/manager_ico/default.png';
				}
			}
		
			$data['files'][] = array(
				'name'					=> $file_name,
				'human_name'			=> $human_name,
				'type'					=> $file_type,
				'date'					=> $file_date,
				'size'					=> $file_size,
				'url'					=> $file_url,
				'ext'					=> $file_ext,
				'perms'					=> $file_perms,
				'path'					=> $root_folder . $sub_folder,
				'data_path'				=> $sub_folder . $file_name,
				'img_url'				=> $img_url,
				'thumb_type'			=> $thumb_type,
				'thumb_url'				=> $thumb_url,
				'img_dimension'			=> $img_dimension,
				'html_class'			=> $html_class,
				'test_check'			=> ''
			);
		}
				
		$folders_list = $this->_recursiveFolders(IMAGEPATH . $root_folder);
		$data['folders_list'] = array();
		$data['folders_list'][] = $root_folder;
		foreach($folders_list as $key => $value) {
			$data['folders_list'][] = substr($value, strpos($value, $root_folder)) .'/';
		}
		
		$data['root_folder'] = $setting['root_folder'];
		$data['sub_folder'] = $sub_folder;
		$data['max_size_upload'] = $setting['max_size'];
		$data['allowed_ext'] = $setting['allowed_ext'];

		if ($popup == 'iframe') {
			$this->output->enable_profiler(FALSE);
		}
		
		$this->load->view('admin/image_manager', $data);
	}

	public function resize() {
		$this->load->model('Image_tool_model');
		
		if ($this->input->get('image')) {
			$image_url = $this->Image_tool_model->resize(html_entity_decode($this->input->get('image'), ENT_QUOTES, 'UTF-8'), 120, 120);
			$this->output->set_output(json_encode($image_url));
		}
	}
	
	public function new_folder() {
		$json = array();
    	if (!$this->user->hasPermissions('modify', 'admin/image_tool')) {
			$json['alert'] = '<span class="error">Warning: You do not have the right permission to create new folder!</span>';
		}
				
		$setting = $this->config->item('image_tool');
		if ($this->input->post('sub_folder') AND $this->input->post('name')) {	
			if (!isset($setting['new_folder'])) {
				$json['alert'] = 'Creating new folder is disabled, check administration settings.';
			}
			
			$root_folder = 'data/';
			if (strpos($setting['root_folder'], '/') !== 0 OR strpos($setting['root_folder'], './') === FALSE) {
				$root_folder = $setting['root_folder'] .'/';
			}

			$sub_folder = $this->input->post('sub_folder');
			if (strpos($this->input->post('sub_folder'), '/') === 0 OR strpos($this->input->post('sub_folder'), './') !== FALSE) {
				$sub_folder = '';
			}
			
			$folder_name = $this->_fixFileName($this->input->post('name'));
			if (strpos($this->input->post('name'), '/') !== FALSE) {
				$json['alert'] = '<span class="error">Invalid file/folder name</span>';
			}
		
			if (!is_writable(IMAGEPATH . $root_folder . $sub_folder)) {
				$json['alert'] = '<span class="error">Pemission denied</span>';
			}
			
			$file_path = IMAGEPATH . $root_folder . $sub_folder . $folder_name;
			if (file_exists($file_path)) {
				$json['alert'] = '<span class="success">Folder already exists</span>';
			}
		} else {
			$json['alert'] = 'Please enter your new folder name.';
		}
		
		if (!isset($json['alert'])) {
			$this->_createFolder($file_path);
			$json['alert'] = '<span class="success">Folder created sucessfully</span>';
		}

		$this->output->set_output(json_encode($json));
	}

	public function copy() {
		$json = array();
		
		$setting = $this->config->item('image_tool');
		if ($this->input->post('to_folder') AND $this->input->post('copy_files')) {
			if (!isset($setting['copy'])) {
				$json['alert'] = 'Copying file/folder is disabled, check administration settings.';
			}
			
			$root_folder = 'data/';
			if (strpos($setting['root_folder'], '/') !== 0 OR strpos($setting['root_folder'], './') === FALSE) {
				$root_folder = $setting['root_folder'] .'/';
			}

			$to_folder = $this->input->post('to_folder');
			if (strpos($this->input->post('to_folder'), $root_folder) === 0) {
				$to_folder = str_replace($root_folder, '', $this->input->post('to_folder'));
			}

			$from_folder = $this->input->post('from_folder');
			if (strpos($this->input->post('from_folder'), $root_folder) === 0) {
				$from_folder = str_replace($root_folder, '', $this->input->post('from_folder'));
			}

			$from_path = IMAGEPATH . $root_folder . $from_folder;
			$to_path = IMAGEPATH . $root_folder . $to_folder;
			if (strpos($this->input->post('from_folder'), '/') === 0 OR strpos($this->input->post('from_folder'), './') !== FALSE
			OR strpos($this->input->post('to_folder'), '/') === 0 OR strpos($this->input->post('to_folder'), './') !== FALSE) {
				$from_path = '';
				$to_path = '';
			}

			$copy_files = json_decode($this->input->post('copy_files'));
			if (!is_array($copy_files) AND empty($copy_files)) {
				$json['alert'] = '<span class="error">Please select the file/folder you want to move.</span>';		//die
			}
			
			if (!is_writable($to_path)) {
				$json['alert'] = '<span class="error">Pemission denied</span>';
			}
		} else {
			$json['alert'] = 'Please select the destination, the source and the file/folder you wants to move.';
		}
		
		if (!isset($json['alert'])) {
			foreach ($copy_files as $copy_file) {
				$copy_file = $this->_fixFileName($copy_file);
				if (file_exists($to_path . $copy_file)) {
					$json['alert'] = '<span class="success">File/Folder already exist in destination folder</span>';
				} else {
					$this->_copy($from_path . $copy_file, $to_path . $copy_file);
					$json['alert'] = '<span class="success">File/Folder copied sucessfully</span>';
				}
			}
		}

		$this->output->set_output(json_encode($json));
	}

	public function move() {
		$json = array();
		
		$setting = $this->config->item('image_tool');
		if ($this->input->post('to_folder') AND $this->input->post('move_files')) {
			if (!isset($setting['move'])) {
				$json['alert'] = 'Moving file/folder is disabled, check administration settings.';
			}
			
			$root_folder = 'data/';
			if (strpos($setting['root_folder'], '/') !== 0 OR strpos($setting['root_folder'], './') === FALSE) {
				$root_folder = $setting['root_folder'] .'/';
			}

			$to_folder = $this->input->post('to_folder');
			if (strpos($this->input->post('to_folder'), $root_folder) === 0) {
				$to_folder = str_replace($root_folder, '', $this->input->post('to_folder'));
			}

			$from_folder = $this->input->post('from_folder');
			if (strpos($this->input->post('from_folder'), $root_folder) === 0) {
				$from_folder = str_replace($root_folder, '', $this->input->post('from_folder'));
			}

			$from_path = IMAGEPATH . $root_folder . $from_folder;
			$to_path = IMAGEPATH . $root_folder . $to_folder;
			if (strpos($this->input->post('from_folder'), '/') === 0 OR strpos($this->input->post('from_folder'), './') !== FALSE
			OR strpos($this->input->post('to_folder'), '/') === 0 OR strpos($this->input->post('to_folder'), './') !== FALSE) {
				$from_path = '';
				$to_path = '';
			}

			$move_files = json_decode($this->input->post('move_files'));
			if (!is_array($move_files) AND empty($move_files)) {
				$json['alert'] = '<span class="error">Please select the file/folder you want to move.</span>';		//die
			}
			
			if (!is_writable($to_path)) {
				$json['alert'] = '<span class="error">Pemission denied</span>';
			}
		} else {
			$json['alert'] = 'Please select the destination, the source and the file/folder you wants to move.';
		}
		
		if (!isset($json['alert'])) {
			foreach ($move_files as $move_file) {
				$move_file = $this->_fixFileName($move_file);
				if (file_exists($to_path . $move_file)) {
					$json['alert'] = '<span class="success">File/Folder already exist in destination folder</span>';
				} else if (file_exists($from_path . $move_file)) {
					rename($from_path . $move_file, $to_path . $move_file);
					$json['alert'] = '<span class="success">File/Folder moved sucessfully</span>';
				}
			}
		}

		$this->output->set_output(json_encode($json));
	}

	public function rename() {
		$json = array();
		
		$setting = $this->config->item('image_tool');
		if ($this->input->post('data_path') AND $this->input->post('name')) {
			if (!isset($setting['rename'])) {
				$json['alert'] = 'Renaming file/folder is disabled, check administration settings.';
			}
			
			$root_folder = 'data/';
			if (strpos($setting['root_folder'], '/') !== 0 OR strpos($setting['root_folder'], './') === FALSE) {
				$root_folder = $setting['root_folder'] .'/';
			}

			$data_path = $this->input->post('data_path');
			if (strpos($this->input->post('data_path'), '/') === 0 OR strpos($this->input->post('data_path'), './') !== FALSE) {
				$data_path = '';
			}

			$new_name = $this->_fixFileName($this->input->post('name'));
			if (strpos($this->input->post('name'), '/') !== FALSE) {
				$json['alert'] = '<span class="error">Invalid file/folder name</span>';		//die
			}
		
			if (!is_writable(dirname(IMAGEPATH . $root_folder . $data_path)) OR !is_writable(IMAGEPATH . $root_folder . $data_path)) {
				$json['alert'] = '<span class="error">Pemission denied</span>';
			}
		} else {
			$json['alert'] = 'Please enter your new folder name.';
		}
		
		if (!isset($json['alert'])) {
			if ($this->_rename(IMAGEPATH . $root_folder . $data_path, $new_name)) {
				$json['alert'] = '<span class="success">File/Folder renamed sucessfully</span>';
			} else {
				$json['alert'] = '<span class="error">File/Folder already exists</span>';
			}
		}

		$this->output->set_output(json_encode($json));
	}

	public function delete() {
		$json = array();
		
		$setting = $this->config->item('image_tool');
		if ($this->input->post('data_path')) {
			if (!isset($setting['delete'])) {
				$json['alert'] = ' Deleting file/folder is disabled, check administration settings.';
			}
			
			$root_folder = 'data/';
			if (strpos($setting['root_folder'], '/') !== 0 OR strpos($setting['root_folder'], './') === FALSE) {
				$root_folder = $setting['root_folder'] .'/';
			}

			$data_path = $this->input->post('data_path');
			if (strpos($this->input->post('data_path'), '/') === 0 OR strpos($this->input->post('data_path'), './') !== FALSE) {
				$data_path = '';
			}

			if (!is_writable(dirname(IMAGEPATH . $root_folder . $data_path)) OR !is_writable(IMAGEPATH . $root_folder . $data_path)) {
				$json['alert'] = '<span class="error">Pemission denied</span>';
			}
		} else {
			$json['alert'] = 'Please enter your new folder name.';
		}
		
		if (!isset($json['alert'])) {
			$this->_delete(IMAGEPATH . $root_folder . $data_path);
			$json['alert'] = '<span class="success">File/Folder deleted sucessfully</span>';
		}

		$this->output->set_output(json_encode($json));
	}

	public function upload() {
		$json = array();
		$setting = $this->config->item('image_tool');
		if ($this->input->post('sub_folder')) {
			if (!isset($setting['uploads'])) {
				$json['alert'] = '<span class="error">Uploading is disabled</span>';		//die
			}
		
			$root_folder = 'data/';
			if (strpos($setting['root_folder'], '/') !== 0 OR strpos($setting['root_folder'], './') === FALSE) {
				$root_folder = $setting['root_folder'] .'/';
			}

			$sub_folder = $this->input->post('sub_folder');
			if (strpos($this->input->post('sub_folder'), '/') === 0 OR strpos($this->input->post('sub_folder'), './') !== FALSE) {
				$sub_folder = '';
			}

			$upload_path = IMAGEPATH . $root_folder . $sub_folder;
			if (strpos($sub_folder, '/') === 0 OR strpos($sub_folder, './') !== FALSE) {
				$upload_path = '';
			}

			if (!file_exists($upload_path) AND !is_writable($upload_path)) {
				$json['alert'] = 'Pemission denied';
			}
		} else {
			$json['alert'] = 'Invalid upload path';
		}

		if (!isset($json['alert'])) {
			$ext_img = array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'svg'); //Images

			$this->load->library('upload');
			$this->upload->set_upload_path($upload_path);
			$this->upload->set_allowed_types(implode('|', $ext_img));
			$this->upload->set_max_filesize($setting['max_size']);

			if ( ! $this->upload->do_upload('file')) {
				$json['alert'] = $this->upload->display_errors('', '');
			} else {
				$data = $this->upload->data();
				if (!$data) {
					unlink($data['full_path']);
					$json['alert'] = "Something went wrong when saving the file, please try again.";
				}
			}@unlink($_FILES[$field_name]);
		}

		$this->output->set_output(json_encode($json));
	}

	public function _recursiveFolders($image_path) {
		$folder_paths = array();
		foreach (glob($image_path .'*', GLOB_ONLYDIR) as $filename) {
			$folder_paths[] = $filename;
			$child = glob($filename .'/*', GLOB_ONLYDIR);
			if (is_array($child)) {
				$children = $this->_recursiveFolders($filename .'/*');
				foreach ($children as $childname) {
					$folder_paths[] = $childname;
				}
			}
		}
		return $folder_paths;
	}

	public function _files($image_path) {
		$setting = $this->config->item('image_tool');
		$allowed_ext = (isset($setting['allowed_ext'])) ? explode('|', $setting['allowed_ext']) : array();
		$hidden_files = (isset($setting['hidden_files'])) ? explode('|', $setting['hidden_files']) : array();
		$hidden_folders = (isset($setting['hidden_folders'])) ? explode('|', $setting['hidden_folders']) : array();

		$u_folders = $u_files = array();
		$back[] = array('name' => '..', 'type' => 'back');
		
		$files = glob($image_path . '*');
		foreach ($files as $key => $file_path) {
			$file_name = basename($file_path);

			if (is_dir($file_path) AND !in_array($file_name, $hidden_folders)) {
				$date = filemtime($file_path);
				$size = $this->_folderSize($file_path);
				$u_folders[] = array('name' => $file_name, 'type' => 'dir', 'date' => $date, 'size' => $size, 'ext' => 'dir');
			} else if (is_file($file_path) AND !in_array($file_name, $hidden_files)) {
				$date = filemtime($file_path);
				$size = filesize($file_path);
				$file_ext = substr(strrchr($file_name, '.'), 1);
				$ext_name = $this->_fixFileName($file_ext);
				$ext_lower = strtolower($ext_name);
				$file_type = (in_array($ext_lower, $allowed_ext)) ? 'img' : 'file';
				$u_files[] = array('name' => $file_name, 'type' => $file_type, 'date' => $date, 'size' => $size, 'ext' => $file_ext);
			}
		}

		usort($u_folders, function($x, $y) {
			return $x['name'] >  $y['name'];
		});
		
		usort($u_files, function($x, $y) {
			return $x['name'] >  $y['name'];
		});

		return array_merge($back, $u_folders, $u_files);
	}
	
	public function _copy($from_path, $to_path) {
		if (is_file($from_path)) {
			return copy($from_path, $to_path);
		}
		
		if (is_dir($from_path)) {
			$this->_createFolder($to_path);
			foreach (scandir($from_path) as $item) {
				if ($item != '.' AND $item != '..') {
					if ( ! is_dir($from_path .'/'. $item)) {
						copy($from_path .'/'. $item, $to_path .'/'. $item);
					} else {
						$this->_copy($from_path .'/'. $item, $to_path .'/'. $item);
					}
				}
			}
		}
	}

	public function _rename($old_path, $name) {
		$name = $this->_fixFileName($name);
		if (file_exists($old_path)) {
			if (is_dir($old_path)) {
				$new_path = $this->_fixDirName($old_path) .'/'. $name;
			} else {
				$info = pathinfo($old_path);
				$new_path = $info['dirname'] .'/'. $name;
			}
			
			if (!file_exists($new_path)) {
				return rename($old_path, $new_path);
			}
		}
		return FALSE;
	}

	public function _delete($path) {
		if (file_exists($path) AND is_file($path)) {
			return unlink($path);
		}

		foreach (scandir($path) as $item) {
			if ($item != '.' AND $item != '..') {
				if ( ! is_dir($path .'/'. $item)) {
					unlink($path .'/'. $item);
				} else {
					$this->_delete($path .'/'. $item);
				}
			}
		}
		
		if (is_dir($path)) {
			return rmdir($path);
		}
	}

	public function _makeSize($size) {
	   $units = array('B', 'KB', 'MB', 'GB', 'TB');
	   $u = 0;
		while ((round($size / 1024) > 0) AND ($u < 4)) {
			$size = $size / 1024;
		 	$u++;
	   }
	   return (number_format($size, 0) . " " . $units[$u]);
	}

	public function _folderSize($path) {
		$total_size = 0;
		$files = scandir($path);
		$cleanPath = rtrim($path, '/'). '/';
		foreach($files as $file) {
			if ($file != "." AND $file != "..") {
				$currentFile = $cleanPath . $file;
				if (is_dir($currentFile)) {
					$size = $this->_folderSize($currentFile);
					$total_size += $size;
				} else {
					$size = filesize($currentFile);
					$total_size += $size;
				}
			}   
		}
		return $total_size;
	}

	public function _createFolder($path = FALSE) {
		$oldumask = umask(0);
		if ($path AND !file_exists($path)) {
			mkdir($path, 0777, TRUE); // or even 01777 so you get the sticky bit set 
		}
		umask($oldumask);
	}

	public function _fixGetParams($str) {
		return strip_tags(preg_replace( "/[^a-zA-Z0-9\.\[\]_| -]/", '', $str));
	}

	public function _fixFileName($str, $transliteration = FALSE) {
		$str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
		
		if ($transliteration) {
			$str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
		}
		
		$str = str_replace(array('"', "'", "/", "\\"), "", $str);
		$str = strip_tags($str);
			   
		if (strpos($str, '.') === 0) {
		   $str = 'temp_name'. $str;
		}
		
		return trim($str);
	}

	public function _fixDirName($str){
		return str_replace('~',' ',dirname(str_replace(' ','~',$str)));
	}
}